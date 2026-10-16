<?php

namespace App\Enums;

enum Role: string
{
    /** Familienoberhaupt: verwaltet Accounts und sieht das Änderungsprotokoll. */
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
            self::Admin => 'Verwaltet Accounts und Rezepte, plant das Essen und sieht Familien-Übersicht und Änderungsprotokoll. Wünscht sich nichts und erscheint nicht in der Übersicht.',
            self::Adult => 'Darf Rezepte anlegen, bearbeiten und löschen, den Essensplan festlegen und die Familien-Übersicht sehen.',
            self::Child => 'Darf Rezepte ansehen, eigene Favoriten markieren und sich Gerichte wünschen.',
        };
    }

    public function canEditRecipes(): bool
    {
        return $this !== self::Child;
    }

    /**
     * Den Essensplan festlegen dürfen nur Erwachsene (inkl. Admin).
     */
    public function canPlanMeals(): bool
    {
        return $this !== self::Child;
    }

    /**
     * Wünschen dürfen alle Familienmitglieder – nur der Admin-Account nicht,
     * er dient ausschließlich der Verwaltung.
     */
    public function canWish(): bool
    {
        return $this !== self::Admin;
    }
}
