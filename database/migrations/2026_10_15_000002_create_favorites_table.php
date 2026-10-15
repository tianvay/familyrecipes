<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Favoriten gehören ab jetzt zu einer Person statt global zum Rezept.
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'recipe_id']);
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex(['is_favorite']);
            $table->dropColumn('is_favorite');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false)->index();
        });

        Schema::dropIfExists('favorites');
    }
};
