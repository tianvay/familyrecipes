<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('amount', 32)->nullable();
            $table->string('unit', 32)->nullable();
            $table->string('name');
            $table->boolean('is_optional')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
