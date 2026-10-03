<?php

namespace App\Support\Property;

use App\Models\PmInvoice;
use App\Models\PmPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Directory A/c balance filters. Positive billed − paid is arrears (red pill);
 * negative is credit on the account (green CR).
 */
final class TenantDirectoryBalanceFilter
{
    public const ARREARS = 'arrears';

    public const CREDIT = 'credit';

    public const SETTLED = 'settled';

    public const OVERDUE = 'overdue';

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function filterOptions(): array
    {
        return [
            ['value' => self::ARREARS, 'label' => 'In arrears'],
            ['value' => self::CREDIT, 'label' => 'Credit balance'],
            ['value' => self::SETTLED, 'label' => 'Settled (zero)'],
            ['value' => self::OVERDUE, 'label' => 'Overdue invoices'],
        ];
    }

    /**
     * @param  Builder<\App\Models\PmTenant>  $query
     */
    public static function apply(Builder $query, string $balance): void
    {
        $balance = strtolower(trim($balance));
        if ($balance === '') {
            return;
        }

        if ($balance === self::OVERDUE) {
            $query->whereExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('pm_invoices')
                    ->whereColumn('pm_invoices.pm_tenant_id', 'pm_tenants.id')
                    ->whereNull('pm_invoices.deleted_at')
                    ->where('pm_invoices.status', '!=', PmInvoice::STATUS_CANCELLED)
                    ->where('pm_invoices.status', '!=', PmInvoice::STATUS_DRAFT)
                    ->where('pm_invoices.is_past_due', true)
                    ->where('pm_invoices.balance_due', '>', 0.009);
            });

            return;
        }

        if (! in_array($balance, [self::ARREARS, self::CREDIT, self::SETTLED], true)) {
            return;
        }

        $query->whereIn('pm_tenants.id', function ($sub) use ($balance): void {
            $billed = DB::table('pm_invoices as i')
                ->select('i.pm_tenant_id', DB::raw('COALESCE(SUM(i.total_amount), 0) as billed'))
                ->whereNull('i.deleted_at')
                ->whereNotIn('i.status', [PmInvoice::STATUS_CANCELLED, PmInvoice::STATUS_DRAFT])
                ->groupBy('i.pm_tenant_id');

            $paid = DB::table('pm_payments as p')
                ->select('p.pm_tenant_id', DB::raw('COALESCE(SUM(p.amount), 0) as paid'))
                ->where('p.status', PmPayment::STATUS_COMPLETED)
                ->where(function ($inner): void {
                    $inner->whereNull('p.channel')
                        ->orWhere('p.channel', '!=', 'tenant_credit');
                });
            if (Schema::hasColumn('pm_payments', 'reversal_status')) {
                $paid->where(function ($inner): void {
                    $inner->whereNull('p.reversal_status')
                        ->orWhere('p.reversal_status', '<>', PmPayment::REVERSAL_STATUS_REVERSED);
                });
            }
            $paid->groupBy('p.pm_tenant_id');

            $sub->from('pm_tenants as t')
                ->select('t.id')
                ->leftJoinSub($billed, 'tb', 'tb.pm_tenant_id', '=', 't.id')
                ->leftJoinSub($paid, 'tp', 'tp.pm_tenant_id', '=', 't.id');

            $expr = '(COALESCE(tb.billed, 0) - COALESCE(tp.paid, 0))';
            match ($balance) {
                self::ARREARS => $sub->whereRaw($expr.' > 0.009'),
                self::CREDIT => $sub->whereRaw($expr.' < -0.009'),
                self::SETTLED => $sub->whereRaw('ABS('.$expr.') <= 0.009'),
                default => null,
            };
        });
    }
}
