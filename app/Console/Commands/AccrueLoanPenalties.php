<?php

namespace App\Console\Commands;

use App\Models\LoanBookLoan;
use App\Models\LoanBookPenaltyAccrual;
use App\Models\LoanProduct;
use App\Services\LoanBookGlPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AccrueLoanPenalties extends Command
{
    protected $signature = 'loan:accrue-penalties {--date= : As-of date YYYY-MM-DD (default: today)} {--dry-run : Preview only, no writes}';

    protected $description = 'Accrue loan arrears penalties from loan product rules and post immediately to GL.';

    public function handle(): int
    {
        $asOf = (string) ($this->option('date') ?: now()->toDateString());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
            $this->error('Invalid --date. Use YYYY-MM-DD.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $loans = LoanBookLoan::query()
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED])
            ->where('dpd', '>', 0)
            ->where('balance', '>', 0.01)
            ->orderBy('id')
            ->get();

        if ($loans->isEmpty()) {
            $this->info('No eligible arrears loans found.');
            return self::SUCCESS;
        }

        $products = LoanProduct::query()
            ->whereIn('name', $loans->pluck('product_name')->filter()->unique()->values())
            ->get()
            ->keyBy(fn (LoanProduct $p): string => strtolower(trim((string) $p->name)));

        $accrued = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($loans as $loan) {
            try {
                $product = $products->get(strtolower(trim((string) $loan->product_name)));
                if (! $product) {
                    $skipped++;
                    continue;
                }

                $scope = strtolower(trim((string) ($product->arrears_penalty_scope ?? 'none')));
                if (! in_array($scope, ['whole_loan', 'per_installment'], true)) {
                    $skipped++;
                    continue;
                }

                $amountType = strtolower(trim((string) ($product->penalty_amount_type ?? 'fixed')));
                if (! in_array($amountType, ['fixed', 'percent'], true)) {
                    $amountType = 'fixed';
                }
                $configuredAmount = max(0.0, (float) ($product->penalty_amount ?? 0));
                if ($configuredAmount <= 0.0) {
                    $skipped++;
                    continue;
                }

                $intervalDays = $this->resolveInstallmentIntervalDays($loan, $product);
                $overdueInstallments = max(1, (int) ceil(max(1, (int) $loan->dpd) / max(1, $intervalDays)));
                $termValue = max(1, (int) ($loan->term_value ?? $product->default_term_months ?? 1));
                $balance = max(0.0, (float) $loan->balance);
                $periodicArrears = $termValue > 0 ? ($balance / $termValue) : $balance;
                $totalOverdueArrears = round(min($balance, max(0.0, $periodicArrears * $overdueInstallments)), 2);
                if ($totalOverdueArrears <= 0.0) {
                    $skipped++;
                    continue;
                }

                $targetInstallments = $scope === 'whole_loan' ? 1 : $overdueInstallments;
                $existingCount = (int) LoanBookPenaltyAccrual::query()
                    ->where('loan_book_loan_id', $loan->id)
                    ->where('scope', $scope)
                    ->count();
                if ($existingCount >= $targetInstallments) {
                    $skipped++;
                    continue;
                }

                $start = $scope === 'whole_loan' ? 1 : ($existingCount + 1);
                $end = $scope === 'whole_loan' ? 1 : $targetInstallments;
                for ($installmentNo = $start; $installmentNo <= $end; $installmentNo++) {
                    $base = $scope === 'whole_loan'
                        ? $totalOverdueArrears
                        : round(max(0.0, min($periodicArrears, $balance)), 2);
                    if ($base <= 0.0) {
                        continue;
                    }

                    $penalty = $amountType === 'percent'
                        ? round($base * ($configuredAmount / 100), 2)
                        : round($configuredAmount, 2);
                    if ($penalty <= 0.0) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line(sprintf(
                            '[DRY] Loan %s (%d) scope=%s inst=%d base=%.2f penalty=%.2f',
                            (string) $loan->loan_number,
                            (int) $loan->id,
                            $scope,
                            $installmentNo,
                            $base,
                            $penalty
                        ));
                        $accrued++;
                        continue;
                    }

                    DB::transaction(function () use ($loan, $product, $scope, $installmentNo, $asOf, $base, $amountType, $configuredAmount, $penalty, &$accrued): void {
                        $freshLoan = LoanBookLoan::query()->lockForUpdate()->findOrFail($loan->id);
                        $exists = LoanBookPenaltyAccrual::query()
                            ->where('loan_book_loan_id', $freshLoan->id)
                            ->where('scope', $scope)
                            ->where('installment_no', $scope === 'whole_loan' ? 0 : $installmentNo)
                            ->exists();
                        if ($exists) {
                            return;
                        }

                        $reference = 'LNPEN-'.(int) $freshLoan->id.'-'.($scope === 'whole_loan' ? 'W1' : ('I'.$installmentNo));
                        $description = 'Loan penalty accrual '.$reference.' — '.(string) ($freshLoan->loan_number ?? ('#'.$freshLoan->id));
                        $entry = app(LoanBookGlPostingService::class)->postLoanPenaltyAccrual(
                            loan: $freshLoan,
                            amount: $penalty,
                            reference: $reference,
                            description: $description,
                            user: null,
                            entryDate: $asOf
                        );

                        LoanBookPenaltyAccrual::query()->create([
                            'loan_book_loan_id' => (int) $freshLoan->id,
                            'loan_product_id' => (int) $product->id,
                            'scope' => $scope,
                            'installment_no' => $scope === 'whole_loan' ? 0 : $installmentNo,
                            'accrued_on' => $asOf,
                            'arrears_base' => $base,
                            'penalty_amount_type' => $amountType,
                            'penalty_rate' => $amountType === 'percent' ? $configuredAmount : null,
                            'penalty_amount' => $penalty,
                            'reference' => $reference,
                            'accounting_journal_entry_id' => (int) $entry->id,
                            'created_by' => null,
                            'notes' => 'Auto accrued from product penalty rules.',
                        ]);

                        $freshLoan->fees_outstanding = round((float) $freshLoan->fees_outstanding + $penalty, 2);
                        $freshLoan->balance = round(max(0.0, (float) $freshLoan->principal_outstanding + (float) $freshLoan->interest_outstanding + (float) $freshLoan->fees_outstanding), 2);
                        $audit = '[Penalty accrual '.now()->format('Y-m-d H:i').'] '.$scope.' installment #'.($scope === 'whole_loan' ? '1' : (string) $installmentNo)
                            .' base '.number_format($base, 2, '.', '').' penalty '.number_format($penalty, 2, '.', '').'.';
                        $existingNotes = trim((string) ($freshLoan->notes ?? ''));
                        $freshLoan->notes = $existingNotes !== '' ? $existingNotes."\n".$audit : $audit;
                        $freshLoan->save();

                        $accrued++;
                    });
                }
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        $this->info("Loan penalties run complete. accrued={$accrued}, skipped={$skipped}, failed={$failed}".($dryRun ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }

    private function resolveInstallmentIntervalDays(LoanBookLoan $loan, LoanProduct $product): int
    {
        $fromProduct = (int) ($product->payment_interval_days ?? 0);
        if ($fromProduct > 0) {
            return $fromProduct;
        }

        $unit = strtolower(trim((string) ($loan->term_unit ?? $product->default_term_unit ?? 'monthly')));

        return match ($unit) {
            'daily' => 1,
            'weekly' => 7,
            default => 30,
        };
    }
}

