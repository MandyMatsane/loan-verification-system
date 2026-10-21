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
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 4)->nullable();
            $table->decimal('monthly_repayment', 12, 2)->nullable();
            $table->decimal('total_repayable', 12, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn(['interest_rate', 'monthly_repayment', 'total_repayable']);
        });
    }
};
