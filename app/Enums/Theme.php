<?php

namespace App\Enums;

use Illuminate\Http\Request;

/**
 * Farbdesign der Oberfläche, pro Benutzer wählbar unter „Mein Konto“.
 * Die Farben selbst stehen in resources/css/app.css (Selektor [data-theme=…]).
 */
enum Theme: string
{
    /** Das ursprüngliche helle Design in Papier- und Terrakottatönen. */
    case Paper = 'papier';

    /** Dunkles Design nach der Catppuccin-Palette „Mocha“. */
    case Mocha = 'mocha';

    /** Folgt dem Gerät: tagsüber Papier, im Dunkelmodus Mocha. */
    case Auto = 'auto';

    public const COOKIE = 'theme';

    public function label(): string
    {
        return match ($this) {
            self::Paper => 'Papier (hell)',
            self::Mocha => 'Catppuccin Mocha (dunkel)',
            self::Auto => 'Automatisch',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Paper => 'Warme Papier- und Terrakottatöne – das klassische Rezeptbuch.',
            self::Mocha => 'Dunkle, sanfte Pastellfarben. Angenehm abends in der Küche.',
            self::Auto => 'Richtet sich nach dem Gerät: hell bei Tag, Mocha im Dunkelmodus.',
        };
    }

    /**
     * Vorschaufarben für die Auswahl unter „Mein Konto“, je Vorschau:
     * [Hintergrund, Karte, Schrift, Akzent, Kräutergrün]. „Automatisch“ zeigt beide.
     *
     * @return list<list<string>>
     */
    public function previews(): array
    {
        return match ($this) {
            self::Paper => [['#fbf7f1', '#ffffff', '#2b2420', '#c8553d', '#4f7a4a']],
            self::Mocha => [['#181825', '#1e1e2e', '#cdd6f4', '#fab387', '#a6e3a1']],
            self::Auto => [...self::Paper->previews(), ...self::Mocha->previews()],
        };
    }

    /**
     * Design für die aktuelle Anfrage: Einstellung des Benutzers, sonst das zuletzt
     * gewählte Design aus dem Cookie (z. B. auf der Anmeldeseite), sonst Papier.
     */
    public static function current(Request $request): self
    {
        return $request->user()?->theme
            ?? self::tryFrom((string) $request->cookie(self::COOKIE))
            ?? self::Paper;
    }
}
