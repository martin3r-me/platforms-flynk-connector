<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── flynk_containers ────────────────────────────────────
        // Ein Container = die Brücke [Organisations-Knoten] ⇄ [FLYNK-Project].
        Schema::create('flynk_containers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('status')->default('draft');

            // Verortung: der Organisations-Knoten
            $table->unsignedBigInteger('owner_entity_id')->nullable();

            // FLYNK-Zugang (IntegrationConnection aus dem integrations-Service)
            $table->unsignedBigInteger('integration_connection_id')->nullable();

            // Das eine verbundene FLYNK-Project (UUID)
            $table->string('external_id')->nullable();
            $table->string('external_url')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('owner_entity_id')
                ->references('id')->on('organization_entities')
                ->nullOnDelete();

            $table->index(['team_id', 'status']);
            $table->index(['owner_entity_id']);
            $table->index(['external_id']);
        });

        // ── flynk_container_events ──────────────────────────────
        // Audit-/Event-Log je Container (füllt die Activity-Sidebar).
        Schema::create('flynk_container_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flynk_container_id')->constrained('flynk_containers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');           // created | linked | updated | unregistered | error | test
            $table->string('title', 255);
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['flynk_container_id', 'created_at']);
        });

        // ── flynk_sync_states ───────────────────────────────────
        // Mapping lokaler Datensätze ⇄ FLYNK-Objekte (Phase 2: Daten-Abo).
        // In v1 leer — bereits inbound-ready modelliert (direction).
        Schema::create('flynk_sync_states', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('flynk_container_id')->constrained('flynk_containers')->cascadeOnDelete();

            // Polymorpher lokaler Datensatz (z.B. planner_task, change_action …)
            $table->nullableMorphs('syncable');

            // FLYNK-Zielobjekt
            $table->string('external_id')->nullable();
            $table->string('external_type')->nullable();   // project | task | document …

            $table->string('direction')->default('outbound'); // outbound | inbound (inbound-ready)
            $table->string('status')->default('pending');      // pending | synced | failed
            $table->string('payload_hash')->nullable();        // Delta-Erkennung
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamp('last_pulled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index(['flynk_container_id', 'external_type']);
            $table->index(['external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flynk_sync_states');
        Schema::dropIfExists('flynk_container_events');
        Schema::dropIfExists('flynk_containers');
    }
};
