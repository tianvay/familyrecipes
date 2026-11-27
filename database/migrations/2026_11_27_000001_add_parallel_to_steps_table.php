<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schritt läuft gleichzeitig mit dem vorigen Schritt (z. B. „Nudeln kochen“ und „Soße anbraten“).
     */
    public function up(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->boolean('parallel')->default(false)->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->dropColumn('parallel');
        });
    }
};
