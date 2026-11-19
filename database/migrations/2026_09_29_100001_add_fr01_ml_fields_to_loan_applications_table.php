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
            if (!Schema::hasColumn('loan_applications', 'no_of_dependents')) {
                $table->integer('no_of_dependents')->nullable();
            }

            if (!Schema::hasColumn('loan_applications', 'education')) {
                $table->string('education')->nullable();
            }

            if (!Schema::hasColumn('loan_applications', 'loan_term')) {
                $table->integer('loan_term')->nullable();
            }

            if (!Schema::hasColumn('loan_applications', 'cibil_score_band')) {
                $table->string('cibil_score_band')->nullable();
            }

            if (!Schema::hasColumn('loan_applications', 'residential_assets_value')) {
                $table->decimal('residential_assets_value', 12, 2)->nullable();
            }

            if (!Schema::hasColumn('loan_applications', 'commercial_assets_value')) {
                $table->decimal('commercial_assets_value', 12, 2)->nullable();
            }

            if (!Schema::hasColumn('loan_applications', 'luxury_assets_value')) {
                $table->decimal('luxury_assets_value', 12, 2)->nullable();
            }

            if (!Schema::hasColumn('loan_applications', 'bank_asset_value')) {
                $table->decimal('bank_asset_value', 12, 2)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $columns = [
                'no_of_dependents',
                'education',
                'loan_term',
                'cibil_score_band',
                'residential_assets_value',
                'commercial_assets_value',
                'luxury_assets_value',
                'bank_asset_value',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('loan_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
