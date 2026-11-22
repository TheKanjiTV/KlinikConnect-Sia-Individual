<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
        Schema::table('bed_allotments', function (Blueprint $table) {
            $table->date('discharge_time')->nullable()->change();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE patients MODIFY gender ENUM('male', 'female', 'other') NOT NULL");
        }
    }

    public function down(): void
    {
        // Keep existing nullable values and additional genders when rolling back application code.
    }
};
