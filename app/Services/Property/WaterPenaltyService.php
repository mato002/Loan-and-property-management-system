<?php



namespace App\Services\Property;



use App\Models\PmFinanceAuditLog;

use App\Models\PmInvoice;

use App\Models\PmInvoiceEvent;

use App\Models\PmInvoicePenaltyApplication;

use App\Models\PmPenaltyRule;

use App\Models\User;

use App\Models\UtilityAuditLog;

use Illuminate\Database\QueryException;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\DB;



class WaterPenaltyService

{

    public function __construct(
        private readonly PenaltyEngineService $engine,
    ) {
    }



    /**

     * @return array{

     *     rows: Collection<int, array<string, mixed>>,

     *     warnings: list<string>,

     *     total_penalty: float

     * }

     */

    public function simulate(?string $asOfDate = null): array

    {

        $today = $asOfDate ?: now()->toDateString();

        $rules = $this->activeWaterRules();

        $previews = collect();

        $warnings = [];



        foreach ($rules as $rule) {

            $warnings = array_merge($warnings, $this->engine->ruleOperatorWarnings($rule));

            $threshold = now()->parse($today)->subDays((int) ($rule->grace_days ?? 0))->toDateString();



            $this->engine->eligibleInvoiceQuery('water')

                ->whereDate('due_date', '<', $threshold)

                ->orderBy('due_date')

                ->limit(1000)

                ->get()

                ->each(function (PmInvoice $invoice) use ($rule, $threshold, $previews, $today, &$warnings) {

                    if (! $this->engine->isPenaltyEligible($invoice, 'water')) {

                        return;

                    }



                    if ($this->engine->hasBlockingApplication($invoice, $rule, $threshold)) {

                        return;

                    }



                    $invoice = $this->engine->prepareInvoiceForPenalty((int) $invoice->id);

                    if (! $invoice) {

                        return;

                    }



                    $simulation = $this->engine->simulate($rule, $invoice, $today, $threshold);

                    if ($simulation['penalty'] <= 0) {

                        return;

                    }



                    $warnings = array_merge($warnings, $simulation['warnings']);



                    $previews->push([

                        'invoice_id' => (int) $invoice->id,

                        'invoice_no' => (string) $invoice->invoice_no,

                        'base' => $simulation['base'],

                        'penalty' => $simulation['penalty'],

                        'rule' => (string) $rule->name,

                        'rule_id' => (int) $rule->id,

                        'threshold_date' => $threshold,

                        'as_of' => $today,

                        'compounding_mode' => $simulation['compounding_mode'],

                        'days_overdue' => $simulation['days_overdue'],

                        'cumulative_applied' => $simulation['cumulative_applied'],

                        'cumulative_cap' => (float) ($rule->cumulative_cap ?? 0),

                        'warnings' => $simulation['warnings'],

                    ]);

                });

        }



        return [

            'rows' => $previews,

            'warnings' => array_values(array_unique($warnings)),

            'total_penalty' => round((float) $previews->sum('penalty'), 2),

        ];

    }



    /**

     * @return Collection<int, array<string, mixed>>

     */

    public function preview(?string $asOfDate = null): Collection

    {

        return $this->simulate($asOfDate)['rows'];

    }



    /**

     * @return array{applied: int, skipped: int}

     */

    public function apply(?string $asOfDate = null, ?User $actor = null, string $source = 'manual'): array

    {

        $today = $asOfDate ?: now()->toDateString();

        $rules = $this->activeWaterRules();

        $applied = 0;

        $skipped = 0;



        foreach ($rules as $rule) {

            $graceDays = (int) ($rule->grace_days ?? 0);

            $threshold = now()->parse($today)->subDays($graceDays)->toDateString();



            $invoiceIds = $this->engine->eligibleInvoiceQuery('water')

                ->whereDate('due_date', '<', $threshold)

                ->orderBy('due_date')

                ->limit(1000)

                ->pluck('id')

                ->map(fn ($id) => (int) $id)

                ->all();



            foreach ($invoiceIds as $invoiceId) {

                $invoice = PmInvoice::query()->find($invoiceId);

                if (! $invoice || ! $this->engine->isPenaltyEligible($invoice, 'water')) {

                    $skipped++;



                    continue;

                }



                $result = $this->applyPenaltyToInvoice($invoice, $rule, $threshold, $today, $actor, $source);

                if ($result) {

                    $applied++;

                } else {

                    $skipped++;

                }

            }

        }



        return ['applied' => $applied, 'skipped' => $skipped];

    }



    public function reverseApplication(PmInvoicePenaltyApplication $application, ?User $actor = null, ?string $reason = null, ?int $utilityOverrideRequestId = null): bool

    {

        if ($application->reversed_at) {

            return false;

        }



        $application->loadMissing('invoice');

        if ($application->invoice) {

            app(UtilityPeriodGuardService::class)->assertInvoiceMutable(

                $application->invoice,

                UtilityPeriodGuardService::ACTION_REVERSE_PENALTY,

                $actor,

                $utilityOverrideRequestId,

            );

        }



        return DB::transaction(function () use ($application, $actor, $reason) {

            $application = PmInvoicePenaltyApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($application->reversed_at) {

                return false;

            }



            $invoice = $this->engine->prepareInvoiceForPenalty((int) $application->pm_invoice_id);

            if (! $invoice) {

                return false;

            }



            $penaltyAmount = round((float) $application->amount, 2);

            if ($penaltyAmount <= 0) {

                return false;

            }



            $invoice->amount = max(0.0, round((float) $invoice->amount - $penaltyAmount, 2));

            $invoice->total_amount = $invoice->amount;

            $invoice->description = trim(preg_replace('/\s*\|\s*Water penalty[^|]*/', '', (string) $invoice->description) ?: $invoice->description);

            $invoice->save();

            $invoice->syncAmountPaidFromAllocations();



            PropertyAccountingPostingService::reverseWaterPenalty($invoice, $penaltyAmount, (int) $application->id, $actor, $reason);



            $application->update([

                'reversed_at' => now(),

                'reversed_by' => $actor?->id,

                'reversal_reason' => $reason ?: 'Penalty waived/reversed',

            ]);



            PmInvoiceEvent::record(

                (int) $invoice->id,

                PmInvoiceEvent::EVENT_PENALTY_APPLIED,

                $actor?->id,

                'Water penalty reversed: KES '.number_format($penaltyAmount, 2),

                ['penalty_application_id' => (int) $application->id, 'reversed' => true, 'reaccrual_allowed' => true]

            );



            UtilityAuditLog::record('penalty_reversed', 'pm_invoice_penalty_application', (int) $application->id, [

                'pm_invoice_id' => (int) $invoice->id,

                'actor_user_id' => $actor?->id,

                'payload' => ['amount' => $penaltyAmount, 'reason' => $reason, 'reaccrual_allowed' => true],

            ]);



            app(InvoiceStateIntegrityService::class)->assertHealthy($invoice);



            return true;

        });

    }



    private function applyPenaltyToInvoice(

        PmInvoice $invoice,

        PmPenaltyRule $rule,

        string $threshold,

        string $today,

        ?User $actor,

        string $source,

    ): bool {

        if ($this->engine->hasBlockingApplication($invoice, $rule, $threshold)) {

            return false;

        }



        try {

            return DB::transaction(function () use ($invoice, $rule, $threshold, $today, $actor, $source) {

                $invoice = $this->engine->prepareInvoiceForPenalty((int) $invoice->id);

                if (! $invoice || ! $this->engine->isPenaltyEligible($invoice, 'water')) {

                    return false;

                }



                app(UtilityPeriodGuardService::class)->assertInvoiceMutable(

                    $invoice,

                    UtilityPeriodGuardService::ACTION_APPLY_PENALTY,

                    $actor,

                );



                $simulation = $this->engine->simulate($rule, $invoice, $today, $threshold);

                $penalty = (float) $simulation['penalty'];

                $base = (float) $simulation['base'];

                if ($penalty <= 0 || $base <= 0) {

                    return false;

                }



                $application = PmInvoicePenaltyApplication::query()->create([

                    'pm_invoice_id' => (int) $invoice->id,

                    'pm_penalty_rule_id' => (int) $rule->id,

                    'threshold_date' => $threshold,

                    'amount' => round($penalty, 2),

                    'base_amount' => round($base, 2),

                    'compounding_mode' => $simulation['compounding_mode'],

                    'days_overdue' => (int) $simulation['days_overdue'],

                    'applied_at' => now(),

                ]);



                $invoice = PmInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                $invoice->syncAmountPaidFromAllocations();

                $invoice->amount = round((float) $invoice->amount + $penalty, 2);

                $invoice->total_amount = $invoice->amount;

                $invoice->description = trim(((string) $invoice->description).' | Water penalty '.$rule->name.' '.$today);

                $invoice->save();

                $invoice->syncAmountPaidFromAllocations();



                PropertyAccountingPostingService::postWaterPenalty($invoice, $penalty, (int) $application->id, $actor);



                PmInvoiceEvent::record(

                    (int) $invoice->id,

                    PmInvoiceEvent::EVENT_PENALTY_APPLIED,

                    $actor?->id,

                    sprintf('Water penalty applied: %s (KES %s)', $rule->name, number_format($penalty, 2)),

                    [

                        'rule_id' => (int) $rule->id,

                        'application_id' => (int) $application->id,

                        'source' => $source,

                        'threshold_date' => $threshold,

                        'base_amount' => round($base, 2),

                        'compounding_mode' => $simulation['compounding_mode'],

                        'days_overdue' => (int) $simulation['days_overdue'],

                    ]

                );



                UtilityAuditLog::record('penalty_applied', 'pm_invoice_penalty_application', (int) $application->id, [

                    'pm_invoice_id' => (int) $invoice->id,

                    'actor_user_id' => $actor?->id,

                    'payload' => [

                        'amount' => $penalty,

                        'base_amount' => round($base, 2),

                        'rule' => $rule->name,

                        'source' => $source,

                        'compounding_mode' => $simulation['compounding_mode'],

                        'days_overdue' => (int) $simulation['days_overdue'],

                    ],

                ]);



                PmFinanceAuditLog::record(

                    PmFinanceAuditLog::ACTION_PENALTY_APPLIED,

                    'pm_invoice_penalty_application',

                    (int) $application->id,

                    [

                        'pm_invoice_id' => (int) $invoice->id,

                        'actor_user_id' => $actor?->id,

                        'summary' => sprintf('Water penalty applied on %s: KES %s', $invoice->invoice_no, number_format($penalty, 2)),

                        'payload' => [

                            'amount' => round($penalty, 2),

                            'base_amount' => round($base, 2),

                            'rule' => (string) $rule->name,

                            'source' => $source,

                            'threshold_date' => $threshold,

                            'compounding_mode' => $simulation['compounding_mode'],

                            'days_overdue' => (int) $simulation['days_overdue'],

                        ],

                    ]

                );



                app(InvoiceStateIntegrityService::class)->assertHealthy($invoice);



                return true;

            });

        } catch (QueryException $e) {

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {

                return false;

            }

            throw $e;

        }

    }



    /**

     * @return \Illuminate\Support\Collection<int, PmPenaltyRule>

     */

    private function activeWaterRules()

    {

        return PmPenaltyRule::query()

            ->where('is_active', true)

            ->where('scope', 'water')

            ->orderBy('id')

            ->get();

    }

}


