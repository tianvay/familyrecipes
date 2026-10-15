<?php

namespace App\Support\RecipeImport;

use App\Support\Quantity;

/**
 * Zerlegt eine Zutatenzeile wie „1/2 TL Salz“ oder „2-3 Zehe(n) Knoblauch“
 * in Menge, Einheit und Name. Was nicht erkannt wird, landet komplett im Namen.
 */
final class IngredientLineParser
{
    /**
     * Bekannte Einheiten (kleingeschrieben, ohne Punkt) => Schreibweise im Rezeptbuch.
     */
    private const UNITS = [
        'g' => 'g', 'gr' => 'g', 'gramm' => 'g', 'kg' => 'kg', 'mg' => 'mg',
        'ml' => 'ml', 'l' => 'l', 'liter' => 'l', 'cl' => 'cl', 'dl' => 'dl',
        'el' => 'EL', 'esslöffel' => 'EL', 'tl' => 'TL', 'teelöffel' => 'TL',
        'msp' => 'Msp.', 'messerspitze' => 'Msp.', 'prise' => 'Prise', 'prisen' => 'Prise',
        'pck' => 'Päckchen', 'päckchen' => 'Päckchen', 'packung' => 'Packung', 'pkg' => 'Packung',
        'dose' => 'Dose', 'dosen' => 'Dose', 'glas' => 'Glas', 'gläser' => 'Glas',
        'becher' => 'Becher', 'bund' => 'Bund', 'stück' => 'Stück', 'stk' => 'Stück', 'st' => 'Stück',
        'zehe' => 'Zehe', 'zehen' => 'Zehe', 'scheibe' => 'Scheibe', 'scheiben' => 'Scheiben',
        'tasse' => 'Tasse', 'tassen' => 'Tasse', 'würfel' => 'Würfel', 'blatt' => 'Blatt', 'blätter' => 'Blatt',
        'zweig' => 'Zweig', 'zweige' => 'Zweig', 'handvoll' => 'Handvoll', 'tropfen' => 'Tropfen',
        'kopf' => 'Kopf', 'köpfe' => 'Kopf', 'stange' => 'Stange', 'stangen' => 'Stange',
        'beutel' => 'Beutel', 'flasche' => 'Flasche', 'schuss' => 'Schuss', 'spritzer' => 'Spritzer',
        'knolle' => 'Knolle', 'knollen' => 'Knolle', 'rolle' => 'Rolle', 'block' => 'Block',
        'stängel' => 'Stängel', 'stiel' => 'Stiel', 'stiele' => 'Stiel',
    ];

    /**
     * @return array{amount: string|null, unit: string|null, name: string}
     */
    public static function parse(string $line): array
    {
        $line = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($line), ENT_QUOTES | ENT_HTML5)));

        $number = '(?:\d+\s+[½¼¾⅓⅔]|\d+(?:[.,]\d+)?(?:\s*\/\s*\d+)?|[½¼¾⅓⅔])';
        $amountPattern = "/^({$number}(?:\s*(?:-|–|bis)\s*{$number})?)\s*(.*)$/u";

        if (! preg_match($amountPattern, $line, $m)) {
            return ['amount' => null, 'unit' => null, 'name' => self::cleanName($line)];
        }

        [$amount, $rest] = [self::normalizeAmount($m[1]), trim($m[2])];
        [$unit, $name] = self::splitUnit($rest);

        if ($name === '') {
            // „2 Eier“ ohne Einheit, aber auch „1 Zitrone“
            return ['amount' => $amount, 'unit' => null, 'name' => self::cleanName($rest)];
        }

        return ['amount' => $amount, 'unit' => $unit, 'name' => self::cleanName($name)];
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private static function splitUnit(string $rest): array
    {
        if (! preg_match('/^([\p{L}]+)\.?(?:\((?:n|e|en|s)\))?\s+(.+)$/u', $rest, $m)) {
            return [null, $rest];
        }

        $unit = self::UNITS[mb_strtolower($m[1])] ?? null;

        return $unit === null ? [null, $rest] : [$unit, $m[2]];
    }

    private static function normalizeAmount(string $amount): string
    {
        // „1 ½“ → 1,5
        $amount = preg_replace_callback('/(\d+)\s+([½¼¾⅓⅔])/u', fn ($m) => (string) ((int) $m[1] + Quantity::parse($m[2])->min), $amount);
        $amount = preg_replace('/\s*bis\s*/u', '-', $amount);

        return Quantity::parse($amount)?->format() ?? trim($amount);
    }

    private static function cleanName(string $name): string
    {
        // „Zwiebel(n)“ → „Zwiebeln“ wäre geraten; die Klammer-Endung wird einfach entfernt
        $name = preg_replace('/(\p{L})\((?:n|e|en|s)\)/u', '$1', $name);

        return trim($name, " \t,;");
    }
}
