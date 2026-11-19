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
            $table->unsignedInteger('no_of_dependents')->nullable()->after('status');
            $table->string('education')->nullable()->after('no_of_dependents');
            $table->string('self_employed')->nullable()->after('education');
            $table->decimal('loan_amount', 12, 2)->nullable()->after('self_employed');
            $table->unsignedInteger('loan_term')->nullable()->after('loan_amount');
            $table->unsignedInteger('cibil_score')->nullable()->after('loan_term');
            $table->decimal('residential_assets_value', 14, 2)->nullable()->after('cibil_score');
            $table->decimal('commercial_assets_value', 14, 2)->nullable()->after('residential_assets_value');
            $table->decimal('luxury_assets_value', 14, 2)->nullable()->after('commercial_assets_value');
            $table->decimal('bank_asset_value', 14, 2)->nullable()->after('luxury_assets_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'no_of_dependents',
                'education',
                'self_employed',
                'loan_amount',
                'loan_term',
                'cibil_score',
                'residential_assets_value',
                'commercial_assets_value',
                'luxury_assets_value',
                'bank_asset_value',
            ]);
        });
    }
};
