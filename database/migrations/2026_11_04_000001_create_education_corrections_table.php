<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. One row per admin correction of an application's education,
     * with the decision as it was before the re-assessment and the status after it.
     */
    public function up(): void
    {
        Schema::create('education_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('loan_applications')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('old_education')->nullable();
            $table->string('new_education');
            $table->text('reason');
            $table->string('previous_status')->nullable();
            $table->string('previous_outcome')->nullable();
            $table->decimal('previous_confidence_score', 5, 2)->nullable();
            $table->string('previous_fraud_risk')->nullable();
            $table->text('previous_reasoning_notes')->nullable();
            $table->string('new_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('education_corrections');
    }
};
