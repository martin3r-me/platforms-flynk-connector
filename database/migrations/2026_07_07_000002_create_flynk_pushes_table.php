<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ein Push = eine gesendete Kontext-Lieferung an FLYNK (mit eigener UUID).
        Schema::create('flynk_pushes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();            // an FLYNK gesendet, Feedback-Key
            $table->foreignId('flynk_container_id')->constrained('flynk_containers')->cascadeOnDelete();

            $table->string('status')->default('pending');
            $table->json('payload')->nullable();       // gesendeter Envelope (Audit)
            $table->string('payload_hash')->nullable();
            $table->json('response')->nullable();       // FLYNK-Feedback: was daraus wurde

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('feedback_at')->nullable();
            $table->timestamps();

            $table->index(['flynk_container_id', 'created_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flynk_pushes');
    }
};
