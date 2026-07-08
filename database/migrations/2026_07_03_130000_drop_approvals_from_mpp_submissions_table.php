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
        Schema::table('mpp_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('mpp_submissions', 'approvals')) {
                $table->dropColumn('approvals');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mpp_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('mpp_submissions', 'approvals')) {
                $table->json('approvals')->nullable()->after('fasilitas_dibutuhkan');
            }
        });
    }
};
