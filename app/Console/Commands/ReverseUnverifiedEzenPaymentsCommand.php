<?php

namespace App\Console\Commands;

use App\Models\PmPayment;
use App\Services\Property\PropertyTransactionReversalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReverseUnverifiedEzenPaymentsCommand extends Command
{
    protected $signature = 'property:reverse-unverified-ezen-payments
                            {--agent-user-id= : Limit to one agent portfolio}
                            {--tenant-id= : Limit to one tenant}
                            {--apply : Reverse the listed payments; default is preview only}
                            {--limit=10000 : Maximum imported payments to inspect}';

    protected $description = 'Preview or reverse completed EZEN-imported tenant payments; bank/M-Pesa transactions remain the trusted payment source';

    public function handle(): int
    {
        $query = PmPayment::withoutGlobalScopes()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('pm_tenant_id', '>', 0)
            ->where(function ($builder): void {
                $builder->whereIn('channel', ['ezen_import', 'ezen_receipt'])
                    ->orWhere('external_ref', 'like', 'EZEN-RC%')
                    ->orWhere('meta->source', 'like', 'ezen\_%');
            })
            ->orderBy('pm_tenant_id')
            ->orderBy('paid_at')
            ->orderBy('id');

        if (is_numeric($this->option('tenant-id'))) {
            $query->where('pm_tenant_id', (int) $this->option('tenant-id'));
        }

        if (is_numeric($this->option('agent-user-id'))) {
            $agentId = (int) $this->option('agent-user-id');
            $query->whereExists(function ($sub) use ($agentId): void {
                $sub->selectRaw('1')->from('pm_tenants as t')
                    ->whereColumn('t.id', 'pm_payments.pm_tenant_id')
                    ->where('t.agent_user_id', $agentId);
            });
        }

        $payments = $query->limit(max(1, (int) $this->option('limit')))->get();
        $apply = (bool) $this->option('apply');
        $reversed = 0;
        $errors = [];

        foreach ($payments as $payment) {
            $this->line(sprintf(
                '%s payment #%d | tenant #%d | %s | KES %s | %s',
                $apply ? 'Reversing' : 'Preview',
                $payment->id,
                $payment->pm_tenant_id,
                $payment->external_ref ?: '(no reference)',
                number_format((float) $payment->amount, 2),
                optional($payment->paid_at)->format('Y-m-d') ?: 'undated',
            ));

            if (! $apply) {
                continue;
            }

            try {
                DB::transaction(function () use ($payment): void {
                    $locked = PmPayment::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
                    if ($locked->status !== PmPayment::STATUS_COMPLETED) {
                        return;
                    }

                    app(PropertyTransactionReversalService::class)->reversePayment(
                        $locked,
                        null,
                        'Unverified EZEN legacy receipt; tenant payment must be supported by bank/M-Pesa source data.',
                    );
                });
                $reversed++;
            } catch (Throwable $exception) {
                $errors[] = 'Payment #'.$payment->id.': '.$exception->getMessage();
            }
        }

        $total = round((float) $payments->sum('amount'), 2);
        $this->newLine();
        $this->info(sprintf(
            '%s %d EZEN-imported payment(s), KES %s total%s',
            $apply ? 'Reversed' : '[PREVIEW] Found',
            $apply ? $reversed : $payments->count(),
            number_format($total, 2),
            $apply ? '' : '. Rerun with --apply to reverse them and release their allocations.',
        ));

        foreach ($errors as $error) {
            $this->error($error);
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
