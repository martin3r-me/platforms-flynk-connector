<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kanal 2 (inbound): Spiegel der FLYNK-Rückfragen (Tasks vom Typ "question"),
 * bei denen wir am Zug sind. Wird per Pull aus FLYNK synchronisiert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flynk_questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('flynk_container_id')->constrained('flynk_containers')->cascadeOnDelete();

            // FLYNK-Task
            $table->string('external_id')->unique();   // FLYNK Task-UUID
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('status')->nullable();       // FLYNK-Task-Status
            $table->string('priority')->nullable();
            $table->string('target_url')->nullable();
            $table->string('assignee')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('flynk_created_at')->nullable();
            $table->timestamp('flynk_updated_at')->nullable();

            // Unsere Antwort
            $table->timestamp('answered_at')->nullable();
            $table->foreignId('answered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('answer_text')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamp('last_pulled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['flynk_container_id']);
            $table->index(['answered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flynk_questions');
    }
};
