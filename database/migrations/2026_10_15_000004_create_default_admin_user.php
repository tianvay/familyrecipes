<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Standard-Zugang für das Familienoberhaupt, damit man sich nach der Einrichtung
     * direkt anmelden kann: Benutzername „Admin“, Passwort „Test“.
     * Das Passwort sollte nach der ersten Anmeldung unter „Mein Konto“ geändert werden.
     *
     * Bewusst ohne User-Model, damit die Migration auch bei späteren Model-Änderungen läuft.
     */
    public function up(): void
    {
        if (DB::table('users')->where('username', 'admin')->exists()) {
            return;
        }

        DB::table('users')->insert([
            'name' => 'Admin',
            'username' => 'admin',
            'password' => Hash::make('Test'),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('username', 'admin')->delete();
    }
};
