<?php

namespace App\Services\Property;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class PropertyStatementUploadService
{
    public const PROVIDER_COOP = 'coop_bank';

    public const PROVIDER_SAFARICOM_C2B = 'safaricom_c2b';

    public const PROVIDER_AUTO = 'auto';

    public function __construct(
        private readonly CoopBankAccountStatementImportService $coopImport,
        private readonly SafaricomC2bCsvStatementParser $safaricomParser,
        private readonly PropertyStatementMissingPaymentRecoveryService $recovery,
    ) {}

    /**
     * @return array{
     *     provider:string,
     *     path:string,
     *     import:array<string, mixed>,
     *     recovery:array{recovered:int, skipped:int, errors:list<string>}|null
     * }
     */
    public function uploadAndImport(
        UploadedFile $file,
        int $agentUserId,
        string $provider = self::PROVIDER_AUTO,
        bool $recoverMissing = true,
    ): array {
        $stored = $file->store('pm-bank-imports/'.now()->format('Y/m'), 'local');
        if ($stored === false) {
            throw new RuntimeException('Could not store the uploaded statement.');
        }

        $absolute = Storage::disk('local')->path($stored);
        $detected = $provider === self::PROVIDER_AUTO
            ? $this->detectProvider($file, $absolute)
            : $provider;

        $import = match ($detected) {
            self::PROVIDER_SAFARICOM_C2B => $this->coopImport->importParsed(
                $this->safaricomParser->parsePath($absolute),
                $agentUserId,
            ),
            self::PROVIDER_COOP => $this->coopImport->importFromPath($absolute, $agentUserId),
            default => throw new RuntimeException('Unsupported statement provider: '.$detected),
        };

        $recovery = null;
        $statementId = (int) ($import['statement_id'] ?? 0);
        if ($recoverMissing && $statementId > 0 && (int) ($import['unmatched'] ?? 0) > 0) {
            $statement = \App\Models\PmBankStatement::query()->find($statementId);
            if ($statement) {
                $recovery = $this->recovery->recoverStatement($statement, $agentUserId);
            }
        }

        return [
            'provider' => $detected,
            'path' => $stored,
            'import' => $import,
            'recovery' => $recovery,
        ];
    }

    private function detectProvider(UploadedFile $file, string $absolute): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $name = strtolower((string) $file->getClientOriginalName());

        if (in_array($ext, ['csv', 'txt'], true)
            && (str_contains($name, 'mpesa') || str_contains($name, 'c2b') || str_contains($name, 'safaricom'))) {
            return self::PROVIDER_SAFARICOM_C2B;
        }

        if ($ext === 'csv') {
            $sample = @file_get_contents($absolute, false, null, 0, 2048) ?: '';
            $lower = strtolower($sample);
            if (str_contains($lower, 'receipt no') || str_contains($lower, 'paid in')) {
                return self::PROVIDER_SAFARICOM_C2B;
            }
        }

        return self::PROVIDER_COOP;
    }
}
