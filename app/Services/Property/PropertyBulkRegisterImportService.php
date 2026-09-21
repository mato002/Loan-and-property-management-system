<?php

namespace App\Services\Property;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Agent-facing dispatcher for legacy register / cutover CSV-PDF imports.
 * Internal parsers may keep historical names; UI labels stay brand-neutral (APP_NAME).
 */
final class PropertyBulkRegisterImportService
{
    public const TYPE_RENT_RECEIPTS = 'rent_receipts';

    public const TYPE_PAYMENT_VOUCHERS = 'payment_vouchers';

    public const TYPE_VENDOR_BILLS = 'vendor_bills';

    public const TYPE_RENTAL_INVOICES = 'rental_invoices';

    public const TYPE_DEPOSITS = 'deposits';

    public const TYPE_BILLING_SCHEDULE = 'billing_schedule';

    public const TYPE_LANDLORD_LEDGER = 'landlord_ledger';

    public const TYPE_STATEMENT_BALANCES = 'statement_balances';

    /**
     * @return array<string, array{label:string, description:string, accept:string, secondary?:bool}>
     */
    public static function catalog(): array
    {
        $app = (string) config('app.name', 'Property ERP');

        return [
            self::TYPE_RENT_RECEIPTS => [
                'label' => 'Rent receipt listing',
                'description' => 'Import tenant receipt listings into '.$app.' (allocate to open invoices or store in the receipt register).',
                'accept' => '.csv,.txt,.pdf',
            ],
            self::TYPE_PAYMENT_VOUCHERS => [
                'label' => 'Payment vouchers (outgoing)',
                'description' => 'Landlord remittances, commissions, tax, and operating expenses from a voucher listing.',
                'accept' => '.csv,.txt,.pdf',
            ],
            self::TYPE_VENDOR_BILLS => [
                'label' => 'Vendor bills (AP)',
                'description' => 'Supplier / contractor bill listings into Accounts payable.',
                'accept' => '.csv,.txt,.pdf',
            ],
            self::TYPE_RENTAL_INVOICES => [
                'label' => 'Rental invoice history',
                'description' => 'Historical rental invoices (amount, paid, due) for matched tenants and units.',
                'accept' => '.csv,.txt,.pdf',
            ],
            self::TYPE_DEPOSITS => [
                'label' => 'Rent & utility deposits',
                'description' => 'Deposit register onto current leases (rent / water / electricity).',
                'accept' => '.csv,.txt',
            ],
            self::TYPE_BILLING_SCHEDULE => [
                'label' => 'Billing schedule (standing charges)',
                'description' => 'Standing charge / utility extras onto current leases (not base rent).',
                'accept' => '.csv,.txt,.pdf',
            ],
            self::TYPE_LANDLORD_LEDGER => [
                'label' => 'Landlord / property ledger',
                'description' => 'Property account statement into take-on balances and landlord ledger lines.',
                'accept' => '.csv,.txt',
            ],
            self::TYPE_STATEMENT_BALANCES => [
                'label' => 'Tenant statement balances (B/F)',
                'description' => 'Opening / carried-forward balances per tenant. Optional second file for landlord take-on.',
                'accept' => '.csv,.txt',
                'secondary' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{type:string, path:string, summary:array<string, mixed>}
     */
    public function importUploaded(
        string $type,
        UploadedFile $file,
        User $actor,
        array $options = [],
        ?UploadedFile $secondaryFile = null,
    ): array {
        if (! array_key_exists($type, self::catalog())) {
            throw new RuntimeException('Unknown import type.');
        }

        $stored = $file->store('pm-register-imports/'.now()->format('Y/m'), 'local');
        if ($stored === false) {
            throw new RuntimeException('Could not store the uploaded file.');
        }
        $path = Storage::disk('local')->path($stored);

        $secondaryPath = null;
        if ($secondaryFile !== null) {
            $sec = $secondaryFile->store('pm-register-imports/'.now()->format('Y/m'), 'local');
            if ($sec === false) {
                throw new RuntimeException('Could not store the secondary file.');
            }
            $secondaryPath = Storage::disk('local')->path($sec);
        }

        $agentUserId = (int) $actor->id;
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $summary = $this->dispatch($type, $path, $agentUserId, $actor, $dryRun, $options, $secondaryPath);

        return [
            'type' => $type,
            'path' => $stored,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function dispatch(
        string $type,
        string $path,
        int $agentUserId,
        User $actor,
        bool $dryRun,
        array $options,
        ?string $secondaryPath,
    ): array {
        $property = isset($options['property']) && trim((string) $options['property']) !== ''
            ? trim((string) $options['property'])
            : null;
        $limit = isset($options['limit']) && is_numeric($options['limit']) ? (int) $options['limit'] : null;

        return match ($type) {
            self::TYPE_RENT_RECEIPTS => app(EzenRentReceiptsImportService::class)->importFromPath(
                $path,
                $agentUserId,
                $actor,
                $dryRun,
                ! (bool) ($options['include_already_paid'] ?? false),
                $property,
                $limit,
                (bool) ($options['enrich_only'] ?? false),
                (bool) ($options['register_only'] ?? false),
            ),
            self::TYPE_PAYMENT_VOUCHERS => app(EzenPaymentVouchersImportService::class)->importFromPath(
                $path,
                $agentUserId,
                $actor,
                $dryRun,
                (bool) ($options['register_only'] ?? false),
                (bool) ($options['remittances_only'] ?? false),
                (bool) ($options['expenses_only'] ?? false),
                (bool) ($options['post_gl'] ?? false),
                $property,
                isset($options['category']) && $options['category'] !== '' ? (string) $options['category'] : null,
                $limit,
            ),
            self::TYPE_VENDOR_BILLS => app(EzenBillsImportService::class)->importFromPath(
                $path,
                $agentUserId,
                $dryRun,
                isset($options['vendor']) && $options['vendor'] !== '' ? (string) $options['vendor'] : null,
                isset($options['status']) && $options['status'] !== '' ? (string) $options['status'] : null,
                $limit,
            ),
            self::TYPE_RENTAL_INVOICES => app(EzenRentalInvoicesImportService::class)->importFromPath(
                $path,
                $agentUserId,
                $actor,
                $dryRun,
                (bool) ($options['include_deposits'] ?? false),
                (bool) ($options['post_gl'] ?? false),
                $property,
                $limit,
            ),
            self::TYPE_DEPOSITS => app(EzenRentDepositImportService::class)->importFromPath(
                $path,
                $agentUserId,
                $dryRun,
                ! (bool) ($options['no_update'] ?? false),
            ),
            self::TYPE_BILLING_SCHEDULE => app(EzenBillingScheduleImportService::class)->importFromPath(
                $path,
                $agentUserId,
                $dryRun,
                ! (bool) ($options['no_update'] ?? false),
            ),
            self::TYPE_LANDLORD_LEDGER => app(EzenLandlordLedgerImportService::class)->importFromPath(
                $path,
                $agentUserId,
                $actor,
                $dryRun,
            ),
            self::TYPE_STATEMENT_BALANCES => $this->importStatementBalances(
                $path,
                $secondaryPath,
                $agentUserId,
                $actor,
                $dryRun,
                (bool) ($options['sync_invoices'] ?? false),
            ),
            default => throw new RuntimeException('Unsupported import type.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function importStatementBalances(
        string $tenantPath,
        ?string $takeonPath,
        int $agentUserId,
        User $actor,
        bool $dryRun,
        bool $syncInvoices,
    ): array {
        $summary = app(EzenStatementBalancesImportService::class)->importTenantBalancesFromPath(
            $tenantPath,
            $agentUserId,
            $dryRun,
            $syncInvoices,
        );

        if ($takeonPath !== null) {
            $takeon = app(PropertyTakeonBalanceService::class)->importFromPath(
                $takeonPath,
                $agentUserId,
                $actor,
                $dryRun,
            );
            $summary['takeon'] = $takeon;
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public static function formatSummaryMessage(string $type, array $summary, bool $dryRun): string
    {
        $label = self::catalog()[$type]['label'] ?? $type;
        $parts = [$dryRun ? 'Dry run' : 'Import complete', $label];

        foreach (['parsed', 'imported', 'register_upserted', 'matched', 'unmatched', 'remittances', 'expenses', 'commissions', 'taxes', 'leases_updated', 'takeon', 'posted', 'vendors', 'payments_posted', 'enriched_existing'] as $key) {
            if (isset($summary[$key]) && ! is_array($summary[$key])) {
                $parts[] = str_replace('_', ' ', $key).': '.$summary[$key];
            }
        }

        $warnings = $summary['warnings'] ?? [];
        if (is_array($warnings) && $warnings !== []) {
            $parts[] = count($warnings).' warning(s)';
        }

        return implode(' · ', $parts).'.';
    }
}
