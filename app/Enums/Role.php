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
            self::Adult => 'Darf Rezepte anlegen, bearbeiten und löschen und den Essensplan festlegen.',
            self::Child => 'Darf Rezepte ansehen, eigene Favoriten markieren und sich Gerichte wünschen.',
        };
    }

    public function canEditRecipes(): bool
    {
        return $this !== self::Child;
    }

    /**
     * Den Essensplan festlegen dürfen nur Erwachsene; wünschen darf jeder.
     */
    public function canPlanMeals(): bool
    {
        return $this !== self::Child;
    }
}
