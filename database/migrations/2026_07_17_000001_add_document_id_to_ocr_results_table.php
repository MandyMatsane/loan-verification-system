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
        Schema::table('ocr_results', function (Blueprint $table) {
            if (!Schema::hasColumn('ocr_results', 'document_id')) {
                $table->foreignId('document_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ocr_results', function (Blueprint $table) {
            if (Schema::hasColumn('ocr_results', 'document_id')) {
                $table->dropConstrainedForeignId('document_id');
            }
        });
    }
};
