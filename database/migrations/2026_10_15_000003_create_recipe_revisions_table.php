<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Änderungsprotokoll für Rezepte. Titel und Name werden als Kopie gespeichert,
     * damit Einträge auch nach dem Löschen von Rezept oder Account lesbar bleiben.
     */
    public function up(): void
    {
        Schema::create('recipe_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipe_title');
            $table->string('user_name')->nullable();
            $table->string('action');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_revisions');
    }
};
