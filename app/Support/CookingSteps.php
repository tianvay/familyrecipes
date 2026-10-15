<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Step;
use Illuminate\Support\Collection;

/**
 * Bereitet die Schritte eines Rezepts für den Kochmodus auf:
 * welche Zutaten im Schritt vorkommen und ob es eine Zeitangabe für einen Timer gibt.
 */
final class CookingSteps
{
    /**
     * Wörter, die in Zutatennamen vorkommen, aber nichts über die Zutat selbst sagen.
     */
    private const STOP_WORDS = [
        'oder', 'und', 'frisch', 'frische', 'frischer', 'frisches', 'optional', 'natur',
        'rote', 'roter', 'rotes', 'grüne', 'grüner', 'gelbe', 'gelber', 'weiße', 'weißer',
        'schwarzer', 'schwarze', 'gemahlen', 'gemahlener', 'getrocknet', 'getrocknete',
        'fettreduziert', 'edelsüß', 'groß', 'große', 'klein', 'kleine', 'mittelgroß',
        'nach', 'geschmack', 'etwas', 'körniger', 'griechischer',
        'vegan', 'vegane', 'veganer', 'veganes', 'veganen',
    ];

    /**
     * @return list<array{number: int, text: string, ingredients: list<string>, timer: array{seconds: int, label: string}|null}>
     */
    public static function for(Recipe $recipe): array
    {
        $recipe->loadMissing(['steps', 'ingredients']);

        return $recipe->steps
            ->values()
            ->map(fn (Step $step, int $index) => [
                'number' => $index + 1,
                'text' => $step->body,
                'ingredients' => self::ingredientsIn($step->body, $recipe->ingredients),
                'timer' => self::timerIn($step->body),
            ])
            ->all();
    }

    /**
     * Zutaten, deren Hauptwörter im Text vorkommen (auch gebeugt: „Zwiebel“ → „Zwiebeln“).
     *
     * @param  Collection<int, Ingredient>  $ingredients
     * @return list<string>
     */
    public static function ingredientsIn(string $text, Collection $ingredients): array
    {
        $text = mb_strtolower($text);
        $textWords = array_filter(
            preg_split('/[^\p{L}]+/u', $text, flags: PREG_SPLIT_NO_EMPTY),
            fn (string $word) => mb_strlen($word) >= 5 && ! in_array($word, self::STOP_WORDS, true),
        );

        return $ingredients
            ->filter(function (Ingredient $ingredient) use ($text, $textWords) {
                foreach (self::keywords($ingredient->name) as $keyword) {
                    // „Zwiebel“ im Namen findet „Zwiebeln“ im Text …
                    if (preg_match('/(?<!\p{L})'.preg_quote($keyword, '/').'/u', $text)) {
                        return true;
                    }

                    // … und „Hähnchen“ im Text findet „Hähnchenbrustfilet“ im Namen
                    foreach ($textWords as $word) {
                        if (str_starts_with($keyword, $word)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->map->label()
            ->values()
            ->all();
    }

    /**
     * Erste Zeitangabe im Text; bei Bereichen („12–15 Minuten“) die längere Zeit.
     *
     * @return array{seconds: int, label: string}|null
     */
    public static function timerIn(string $text): ?array
    {
        $range = '(\d+(?:[.,]\d+)?)(?:\s*(?:[-–]|bis)\s*(\d+(?:[.,]\d+)?))?';

        $units = [
            60 => '(?:Minuten|Minute|Min\.?)(?!\p{L})',
            3600 => '(?:Stunden|Stunde|Std\.?)(?!\p{L})',
        ];

        foreach ($units as $factor => $unit) {
            if (preg_match('/'.$range.'\s*'.$unit.'/iu', $text, $m)) {
                $value = (float) str_replace(',', '.', $m[2] ?? $m[1]);
                $minutes = (int) round($value * $factor / 60);

                if ($minutes > 0) {
                    return [
                        'seconds' => $minutes * 60,
                        'label' => $minutes >= 60 && $minutes % 60 === 0 ? ($minutes / 60).' Std.' : "{$minutes} Min.",
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function keywords(string $name): array
    {
        $words = preg_split('/[\s\-\/(),]+/u', mb_strtolower($name), flags: PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            $words,
            fn (string $word) => mb_strlen($word) >= 4 && ! in_array($word, self::STOP_WORDS, true),
        ));
    }
}
