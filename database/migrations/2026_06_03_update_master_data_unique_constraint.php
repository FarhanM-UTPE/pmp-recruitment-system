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
            // Drop the old unique constraint
            $table->dropUnique('master_data_type_key_unique');
            
            // Add new unique constraint including year and department_id
            $table->unique(['type', 'key', 'year', 'department_id'], 'master_data_type_key_year_dept_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_data', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique('master_data_type_key_year_dept_unique');
            
            // Restore the old unique constraint
            $table->unique(['type', 'key']);
        });
    }
};
