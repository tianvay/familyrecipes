<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Essensplan: Vorschläge pro Woche und genau ein Gericht pro Tag.
     *
     * Beides wird dauerhaft aufbewahrt (Historie für spätere Auswertungen, z. B.
     * „oft vorgeschlagen, selten gekocht“). Deshalb werden Rezept und Person beim Löschen
     * nur entkoppelt (nullOnDelete) und der Titel als Kopie gespeichert.
     */
    public function up(): void
    {
        Schema::create('meal_suggestions', function (Blueprint $table) {
            $table->id();
            $table->date('week_start')->index(); // Montag der Woche
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title'); // Rezepttitel (Kopie) oder Freitext
            $table->unsignedTinyInteger('preferred_day')->nullable(); // 1 = Montag … 7 = Sonntag
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('meal_plan_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique(); // ein Gericht pro Tag
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title'); // Rezepttitel (Kopie) oder Freitext
            $table->foreignId('planned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_entries');
        Schema::dropIfExists('meal_suggestions');
    }
};
