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
        Schema::table('ai_assessments', function (Blueprint $table) {
            $table->decimal('confidence_score', 5, 2)->nullable()->after('eligibility_outcome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_assessments', function (Blueprint $table) {
            $table->dropColumn('confidence_score');
        });
    }
};
