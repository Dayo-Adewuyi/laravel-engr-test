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
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('insurer_id')->constrained()->onDelete('cascade');
            $table->date('batch_date');
            $table->string('batch_identifier')->unique();
            $table->integer('total_claims')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('processing_cost', 12, 2)->default(0);
            $table->boolean('processed')->default(false);
            $table->date('processing_date')->nullable();
            $table->timestamps();
            
            $table->index(['provider_id', 'insurer_id', 'batch_date']);
            $table->index(['insurer_id', 'processed', 'processing_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
