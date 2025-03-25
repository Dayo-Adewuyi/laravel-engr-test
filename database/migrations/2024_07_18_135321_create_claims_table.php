<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('insurer_id')->constrained()->onDelete('cascade');
            $table->foreignId('specialty_id')->constrained()->onDelete('cascade');
            $table->foreignId('batch_id')->nullable()->constrained()->onDelete('set null');
            $table->date('encounter_date');
            $table->date('submission_date');
            $table->unsignedTinyInteger('priority_level');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->boolean('processed')->default(false);
            $table->timestamps();
            
            $table->index(['provider_id', 'insurer_id', 'encounter_date']);
            $table->index(['insurer_id', 'processed', 'submission_date']);
            $table->index(['specialty_id', 'priority_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
}; 