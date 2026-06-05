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
        Schema::table('master_data', function (Blueprint $table) {
            $table->integer('year')->nullable()->after('type');
            $table->unsignedBigInteger('department_id')->nullable()->after('year');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_data', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['year', 'department_id']);
        });
    }
};
