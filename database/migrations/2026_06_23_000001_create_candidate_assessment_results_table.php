<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('candidate_assessment_results', function (Blueprint $table) {
      $table->id();
      $table->string('applicant_id');
      $table->string('provider', 50)->default('astra');
      $table->string('assessment_type', 50)->default('psychotest');
      $table->date('test_date');
      $table->string('norm_grade', 50)->nullable();
      // $table->string('cut_off_name', 100)->nullable();

      // Astra Spark
      $table->decimal('action_score', 5, 2)->nullable();
      $table->decimal('achievement_orientation_score', 5, 2)->nullable();
      $table->decimal('assertiveness_score', 5, 2)->nullable();
      $table->decimal('conscientiousness_score', 5, 2)->nullable();
      $table->decimal('extraversion_score', 5, 2)->nullable();
      $table->decimal('flexibility_score', 5, 2)->nullable();
      $table->decimal('idea_score', 5, 2)->nullable();
      $table->decimal('impact_and_influence_score', 5, 2)->nullable();
      $table->decimal('persistence_score', 5, 2)->nullable();
      $table->decimal('personal_motivation_score', 5, 2)->nullable();
      $table->decimal('teamwork_score', 5, 2)->nullable();
      $table->decimal('working_autonomously_score', 5, 2)->nullable();

      // Astra Ignite
      $table->decimal('reading_comprehension_score', 5, 2)->nullable();
      $table->decimal('quantitative_reasoning_score', 5, 2)->nullable();
      $table->decimal('inductive_reasoning_score', 5, 2)->nullable();
      $table->decimal('working_memory_score', 5, 2)->nullable();
      $table->decimal('perceptual_speed_score', 5, 2)->nullable();
      $table->decimal('deductive_reasoning_score', 5, 2)->nullable();
      $table->decimal('visualization_score', 5, 2)->nullable();

      // Competency
      $table->decimal('competency_aj_score', 5, 2)->nullable();
      $table->decimal('competency_dc_score', 5, 2)->nullable();
      $table->decimal('competency_is_score', 5, 2)->nullable();
      $table->decimal('competency_pda_score', 5, 2)->nullable();
      $table->decimal('competency_tw_score', 5, 2)->nullable();

      // Validity indexes
      $table->decimal('social_desirability_score', 5, 2)->nullable();
      $table->decimal('infrequency_score', 5, 2)->nullable();
      $table->decimal('inconsistency_score', 5, 2)->nullable();

      $table->string('source_file_name')->nullable();
      $table->timestamp('imported_at')->nullable();
      $table->json('raw_identity_snapshot')->nullable();
      $table->timestamps();

      $table->index('applicant_id');
      $table->index('test_date');
      $table->index(['provider', 'assessment_type']);

      $table->unique(
        ['applicant_id', 'test_date', 'provider', 'assessment_type'],
        'car_applicant_date_provider_type_unique'
      );
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('candidate_assessment_results');
  }
};
