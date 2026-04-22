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
        Schema::create('preprocessing_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preprocessing_file_id')->constrained()->onDelete('cascade');
            $table->text('raw_text');
            $table->text('case_folding');
            $table->text('cleansing');
            $table->text('normalisasi');
            $table->text('tokenizing');
            $table->text('filtering');
            $table->text('stemming');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preprocessing_details');
    }
};
