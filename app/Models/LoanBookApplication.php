<?php

namespace App\Models;

use App\Models\Concerns\FallbackPrimaryKeyWhenNoAutoIncrement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanBookApplication extends Model
{
    use FallbackPrimaryKeyWhenNoAutoIncrement;

    public const STAGE_SUBMITTED = 'submitted';

    public const STAGE_CREDIT_REVIEW = 'credit_review';

    public const STAGE_APPROVED = 'approved';

    public const STAGE_DECLINED = 'declined';

    public const STAGE_DISBURSED = 'disbursed';

    protected $fillable = [
        'reference',
        'loan_client_id',
        'product_name',
        'amount_requested',
        'term_months',
        'term_value',
        'term_unit',
        'interest_rate',
        'interest_rate_period',
        'purpose',
        'stage',
        'borrower_category',
        'client_loan_sequence',
        'suggested_limit',
        'risk_flags_json',
        'classification_reason_json',
        'branch',
        'notes',
        'submission_source',
        'submitted_at',
        'form_meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'suggested_limit' => 'decimal:2',
            'submitted_at' => 'datetime',
            'form_meta' => 'array',
            'risk_flags_json' => 'array',
            'classification_reason_json' => 'array',
        ];
    }

    public function loanClient(): BelongsTo
    {
        return $this->belongsTo(LoanClient::class);
    }

    public function loan(): HasOne
    {
        return $this->hasOne(LoanBookLoan::class);
    }

    /**
     * Allocate a new application reference: APP-{year}-{random4}-{sequence}.
     * Sequence comes from a per-year counter row with lockForUpdate (no duplicates
     * under concurrency). Existing application rows are never updated here.
     */
    public static function allocateUniqueReference(): string
    {
        if (! Schema::hasTable('loan_book_application_reference_counters')) {
            return self::allocateUniqueReferenceRandomFallback();
        }

        return DB::transaction(function (): string {
            $year = (int) now()->format('Y');
            $row = DB::table('loan_book_application_reference_counters')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                try {
                    DB::table('loan_book_application_reference_counters')->insert([
                        'year' => $year,
                        'last_sequence' => 0,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Another connection created the row; continue.
                }
                $row = DB::table('loan_book_application_reference_counters')
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();
            }

            if ($row === null) {
                return self::allocateUniqueReferenceRandomFallback();
            }

            $seq = (int) $row->last_sequence + 1;
            DB::table('loan_book_application_reference_counters')
                ->where('year', $year)
                ->update(['last_sequence' => $seq]);

            $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
            $seqPart = str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
            $ref = 'APP-'.$year.'-'.$rand.'-'.$seqPart;
            if (strlen($ref) > 40) {
                $ref = substr($ref, 0, 40);
            }

            return $ref;
        });
    }

    /**
     * Used when the sequence table is missing (e.g. before migrations) or as last resort.
     * Format: APP-{year}-{random hex}; uniqueness is probabilistic plus the DB unique index on reference.
     */
    public static function allocateUniqueReferenceRandomFallback(): string
    {
        $year = now()->format('Y');

        for ($i = 0; $i < 64; $i++) {
            $ref = 'APP-'.$year.'-'.strtoupper(bin2hex(random_bytes(4)));
            if (strlen($ref) > 40) {
                $ref = substr($ref, 0, 40);
            }
            if (! static::query()->where('reference', $ref)->exists()) {
                return $ref;
            }
        }

        throw new \RuntimeException('Unable to allocate a unique loan application reference.');
    }
}
