<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mpp_submission_approval_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mpp_submission_id')->constrained('mpp_submissions')->cascadeOnDelete();
            $table->unsignedInteger('stage_index');
            $table->string('role')->nullable();
            $table->json('required_roles')->nullable();
            $table->string('name')->nullable();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 30)->default('pending');
            $table->boolean('digitally_signed')->default(false);
            $table->string('signature_label')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->unique(['mpp_submission_id', 'stage_index'], 'mpp_submission_stage_unique');
            $table->index(['approver_user_id', 'decision'], 'mpp_stage_approver_decision_idx');
        });

        DB::table('mpp_submissions')
            ->whereNotNull('approvals')
            ->orderBy('id')
            ->chunkById(100, function ($submissions) {
                $rows = [];
                $now = now();

                foreach ($submissions as $submission) {
                    $approvals = $submission->approvals;
                    if (is_string($approvals)) {
                        $approvals = json_decode($approvals, true);
                    }

                    if (!is_array($approvals)) {
                        continue;
                    }

                    foreach (array_values($approvals) as $index => $approval) {
                        if (!is_array($approval)) {
                            continue;
                        }

                        $rows[] = [
                            'mpp_submission_id' => (int) $submission->id,
                            'stage_index' => (int) $index,
                            'role' => data_get($approval, 'role'),
                            'required_roles' => json_encode(array_values((array) data_get($approval, 'required_roles', []))),
                            'name' => data_get($approval, 'name'),
                            'approver_user_id' => data_get($approval, 'approver_user_id'),
                            'decision' => (string) data_get($approval, 'decision', 'pending'),
                            'digitally_signed' => (bool) data_get($approval, 'digitally_signed', false),
                            'signature_label' => data_get($approval, 'signature_label'),
                            'signed_at' => data_get($approval, 'signed_at'),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (!empty($rows)) {
                    DB::table('mpp_submission_approval_stages')->insert($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpp_submission_approval_stages');
    }
};
