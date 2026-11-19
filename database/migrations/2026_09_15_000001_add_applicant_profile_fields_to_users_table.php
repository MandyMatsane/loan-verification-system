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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('email');
            $table->string('id_number')->nullable()->after('phone_number');
            $table->date('date_of_birth')->nullable()->after('id_number');
            $table->text('address')->nullable()->after('date_of_birth');
            $table->string('employment_status')->nullable()->after('address');
            $table->decimal('monthly_income', 12, 2)->nullable()->after('employment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_number',
                'id_number',
                'date_of_birth',
                'address',
                'employment_status',
                'monthly_income',
            ]);
        });
    }
};
