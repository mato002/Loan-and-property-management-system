<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_clients')) {
            return;
        }

        if (! Schema::hasTable('client_leads')) {
            Schema::create('client_leads', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_client_id')->unique()->constrained('loan_clients')->cascadeOnDelete();
                $table->string('lead_source', 32)->default('digital');
                $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('expected_loan_amount', 14, 2)->default(0);
                $table->decimal('approved_amount', 14, 2)->nullable();
                $table->decimal('disbursed_amount', 14, 2)->nullable();
                $table->string('current_stage', 32)->default('new');
                $table->string('pipeline_status', 24)->default('active');
                $table->timestamp('stage_entered_at')->nullable();
                $table->timestamp('first_activity_at')->nullable();
                $table->timestamp('disbursed_at')->nullable();
                $table->timestamps();

                $table->index(['pipeline_status', 'current_stage']);
                $table->index(['assigned_officer_id', 'created_at']);
                $table->index(['lead_source', 'created_at']);
            });
        }

        if (! Schema::hasTable('client_lead_activities')) {
            Schema::create('client_lead_activities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('client_lead_id')->constrained('client_leads')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('activity_type', 24);
                $table->text('notes')->nullable();
                $table->date('next_action_date')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['client_lead_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('client_lead_status_history')) {
            Schema::create('client_lead_status_history', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('client_lead_id')->constrained('client_leads')->cascadeOnDelete();
                $table->string('from_stage', 32)->nullable();
                $table->string('to_stage', 32);
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['client_lead_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('client_lead_loss_reasons')) {
            Schema::create('client_lead_loss_reasons', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('client_lead_id')->constrained('client_leads')->cascadeOnDelete();
                $table->string('reason', 40);
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['client_lead_id', 'created_at']);
            });
        }

        $this->backfillClientLeadsFromLoanClients();
    }

    private function backfillClientLeadsFromLoanClients(): void
    {
        if (! Schema::hasTable('client_leads') || ! Schema::hasTable('loan_clients')) {
            return;
        }

        $leads = DB::table('loan_clients')
            ->where('kind', 'lead')
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('client_leads')
                    ->whereColumn('client_leads.loan_client_id', 'loan_clients.id');
            })
            ->select([
                'loan_clients.id',
                'loan_clients.lead_status',
                'loan_clients.assigned_employee_id',
                'loan_clients.biodata_meta',
                'loan_clients.created_at',
                'loan_clients.updated_at',
            ])
            ->get();

        foreach ($leads as $row) {
            $meta = [];
            if (! empty($row->biodata_meta)) {
                $decoded = json_decode((string) $row->biodata_meta, true);
                $meta = is_array($decoded) ? $decoded : [];
            }
            $lcSource = (string) ($meta['lc_lead_source'] ?? '');
            $leadSource = $this->mapCaptureSourceToPipelineSource($lcSource);

            $stage = $this->mapLegacyLeadStatusToStage((string) ($row->lead_status ?? 'new'));

            $officerId = null;
            if (! empty($row->assigned_employee_id) && Schema::hasTable('employees')) {
                $email = DB::table('employees')->where('id', $row->assigned_employee_id)->value('email');
                $email = $email ? strtolower(trim((string) $email)) : '';
                if ($email !== '' && Schema::hasTable('users')) {
                    $officerId = DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->value('id');
                }
            }

            $now = now();
            $insertedId = DB::table('client_leads')->insertGetId([
                'loan_client_id' => $row->id,
                'lead_source' => $leadSource,
                'assigned_officer_id' => $officerId,
                'expected_loan_amount' => 0,
                'approved_amount' => null,
                'disbursed_amount' => null,
                'current_stage' => $stage,
                'pipeline_status' => $stage === 'dropped' ? 'dropped' : 'active',
                'stage_entered_at' => $row->created_at ?? $now,
                'first_activity_at' => null,
                'disbursed_at' => null,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ]);

            DB::table('client_lead_status_history')->insert([
                'client_lead_id' => $insertedId,
                'from_stage' => null,
                'to_stage' => $stage,
                'changed_by' => null,
                'created_at' => $row->created_at ?? $now,
            ]);
        }
    }

    private function mapCaptureSourceToPipelineSource(string $key): string
    {
        $map = (array) config('lead_intelligence.capture_source_to_pipeline_source', []);

        return (string) ($map[$key] ?? 'digital');
    }

    private function mapLegacyLeadStatusToStage(string $leadStatus): string
    {
        return match (strtolower(trim($leadStatus))) {
            'contacted' => 'contacted',
            'qualified' => 'interested',
            'not_qualified', 'lost' => 'dropped',
            default => 'new',
        };
    }

    public function down(): void
    {
        Schema::dropIfExists('client_lead_loss_reasons');
        Schema::dropIfExists('client_lead_status_history');
        Schema::dropIfExists('client_lead_activities');
        Schema::dropIfExists('client_leads');
    }
};
