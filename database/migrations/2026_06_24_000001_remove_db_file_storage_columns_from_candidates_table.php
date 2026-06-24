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
    Schema::table('candidates', function (Blueprint $table) {
      $table->dropColumn([
        'cv_file_name',
        'cv_mime_type',
        'cv_content',
        'flk_file_name',
        'flk_mime_type',
        'flk_content',
      ]);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('candidates', function (Blueprint $table) {
      $table->string('cv_file_name')->nullable()->after('cv');
      $table->string('cv_mime_type')->nullable()->after('cv_file_name');
      $table->longText('cv_content')->nullable()->after('cv_mime_type');

      $table->string('flk_file_name')->nullable()->after('flk');
      $table->string('flk_mime_type')->nullable()->after('flk_file_name');
      $table->longText('flk_content')->nullable()->after('flk_mime_type');
    });
  }
};
