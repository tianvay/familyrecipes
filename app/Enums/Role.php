<?php

namespace App\Enums;

enum Role: string
{
    /** Familienoberhaupt: verwaltet Accounts und sieht die Familien-Übersicht. */
    case Admin = 'admin';

    case Adult = 'adult';

    /** Kinder dürfen Rezepte nur ansehen und als Favorit markieren. */
    case Child = 'child';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Familienoberhaupt',
            self::Adult => 'Erwachsen',
            self::Child => 'Kind',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Darf alles: Rezepte ändern, Accounts verwalten, Familien-Übersicht und Änderungsprotokoll sehen.',
            self::Adult => 'Darf Rezepte anlegen, bearbeiten und löschen.',
            self::Child => 'Darf Rezepte ansehen und eigene Favoriten markieren.',
        };
    }

    public function canEditRecipes(): bool
    {
        return $this !== self::Child;
    }
}
