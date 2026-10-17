<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rezeptfotos zentral in der Datenbank statt als Datei auf jeder Installation.
     * recipes.image_hash markiert das Foto (und dient als Cache-Schlüssel);
     * recipes.image_path bleibt nur für die Übernahme alter Dateien (recipes:move-images).
     */
    public function up(): void
    {
        Schema::create('recipe_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mime_type', 50);
            $table->unsignedInteger('size');
            $table->binary('data');
            $table->timestamps();
        });

        // BLOB fasst nur 64 KB – MEDIUMBLOB bis 16 MB
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE recipe_images MODIFY data MEDIUMBLOB NOT NULL');
        }

        Schema::table('recipes', function (Blueprint $table) {
            $table->string('image_hash', 40)->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('image_hash');
        });

        Schema::dropIfExists('recipe_images');
    }
};
