<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mpp_submissions', function (Blueprint $table) {
            $table->string('submission_type', 50)->default('planned')->after('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mpp_submissions', function (Blueprint $table) {
            $table->dropColumn('submission_type');
        });
    }
};