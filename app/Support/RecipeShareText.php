<?php

namespace App\Support;

use App\Enums\Difficulty;
use App\Models\Recipe;
use App\Support\RecipeImport\IngredientLineParser;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Rezept als Text zum Teilen – lesbar für alle und wieder einlesbar im Rezeptbuch.
 *
 * Einheitliches Format (Kopfblock ohne Leerzeilen, dann die Abschnitte):
 *
 *     🧀 Omas Käsespätzle
 *     Mit Röstzwiebeln – wie früher
 *     4 Portionen · 20 Min. Vorbereitung · 15 Min. Kochen/Backen · Einfach · 650 kcal · 28 g Protein
 *     Tags: Vegetarisch, Klassiker
 *     Quelle: Oma Hilde
 *
 *     Zutaten:
 *     • 500 g Spätzle
 *     • 1 Bund Schnittlauch (optional)
 *
 *     Zubereitung:
 *     1. Spätzle kochen.
 *     2. (gleichzeitig) Zwiebeln rösten.
 *
 *     Tipps:
 *     • Mit Bergkäse wird es würziger
 *
 * Beim Einlesen ist Text vor und nach dem Rezept (Grüße o. Ä.) egal. Nicht enthalten:
 * Foto, Farbe, Notizen (familienintern), Favoriten, Verlauf.
 */
class RecipeShareText
{
    private const SECTIONS = ['zutaten' => 'ingredients', 'zubereitung' => 'steps', 'tipps' => 'tips'];

    private const OPTIONAL = ' (optional)';

    private const PARALLEL = '(gleichzeitig) ';

    public static function make(Recipe $recipe): string
    {
        $recipe->loadMissing(['ingredients', 'steps', 'tags']);

        $facts = array_filter([
            $recipe->servings ? "{$recipe->servings} ".($recipe->servings === 1 ? 'Portion' : 'Portionen') : null,
            $recipe->prep_minutes ? "{$recipe->prep_minutes} Min. Vorbereitung" : null,
            $recipe->cook_minutes ? "{$recipe->cook_minutes} Min. Kochen/Backen" : null,
            $recipe->difficulty?->label(),
            $recipe->calories ? "{$recipe->calories} kcal" : null,
            $recipe->protein ? "{$recipe->protein} g Protein" : null,
        ]);

        // Der Kopf darf keine Leerzeilen enthalten, sonst zerfällt er beim Einlesen.
        $text = $recipe->description ?: $recipe->summary;
        $header = array_filter([
            trim(($recipe->emoji ?: '🍽️').' '.$recipe->title),
            $text ? self::oneLine($text) : null,
            $facts ? implode(' · ', $facts) : null,
            $recipe->tags->isNotEmpty() ? 'Tags: '.$recipe->tags->pluck('name')->implode(', ') : null,
            $recipe->source ? 'Quelle: '.self::oneLine($recipe->source) : null,
        ]);

        $lines = [...$header, '', 'Zutaten:'];

        foreach ($recipe->ingredients as $ingredient) {
            $line = implode(' ', array_filter([$ingredient->amount, $ingredient->unit, $ingredient->name], 'filled'));
            $lines[] = '• '.self::oneLine($line).($ingredient->is_optional ? self::OPTIONAL : '');
        }

        $lines[] = '';
        $lines[] = 'Zubereitung:';

        foreach ($recipe->steps->values() as $i => $step) {
            $lines[] = ($i + 1).'. '.($i > 0 && $step->parallel ? self::PARALLEL : '').self::oneLine($step->body);
        }

        if ($recipe->tips) {
            $lines[] = '';
            $lines[] = 'Tipps:';

            foreach ($recipe->tips as $tip) {
                $lines[] = '• '.self::oneLine($tip);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Sieht der Text nach einem geteilten Rezept aus?
     */
    public static function looksLikeRecipe(string $text): bool
    {
        return preg_match('/^\s*zutaten:\s*$/miu', $text) === 1
            && preg_match('/^\s*zubereitung:\s*$/miu', $text) === 1;
    }

    /**
     * Liest einen geteilten Rezepttext und liefert Formulareingaben (wie old()) für recipes.create.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException mit einer Meldung für die Nutzerin/den Nutzer
     */
    public static function parse(string $text): array
    {
        $lines = preg_split('/\R/u', str_replace("\u{00A0}", ' ', $text));
        $header = [];
        $lists = ['ingredients' => [], 'steps' => [], 'tips' => []];
        $section = null;
        $block = [];
        $lastBlock = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $heading = self::SECTIONS[mb_strtolower(rtrim($line, ':'))] ?? null;

            if ($heading !== null && str_ends_with($line, ':')) {
                // Der Kopf ist der letzte Absatz vor „Zutaten:“ – davor stehen evtl. Grüße.
                if ($section === null && $heading === 'ingredients') {
                    $header = $block ?: $lastBlock;
                }
                $section = $heading;

                continue;
            }

            if ($line === '') {
                // Leerzeile nach einer Liste beendet sie (danach z. B. „Liebe Grüße“)
                if ($section !== null && $lists[$section] !== []) {
                    $section = 'done';
                }
                $lastBlock = $block ?: $lastBlock;
                $block = [];

                continue;
            }

            if ($section === null) {
                $block[] = $line;
            } elseif ($section !== 'done') {
                $lists[$section][] = $line;
            }
        }

        $ingredients = array_map(self::ingredient(...), $lists['ingredients']);
        $steps = array_map(self::step(...), $lists['steps'], array_keys($lists['steps']));
        $steps = array_values(array_filter($steps, fn (array $step) => $step['body'] !== ''));

        if ($header === [] || $ingredients === [] || $steps === []) {
            throw new InvalidArgumentException('Das Rezept ist unvollständig: Titel, „Zutaten:“ und „Zubereitung:“ werden gebraucht.');
        }

        return [
            ...self::header($header),
            'tips' => array_values(array_filter(array_map(self::stripBullet(...), $lists['tips']))),
            'ingredients' => $ingredients,
            'steps' => $steps,
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, mixed>
     */
    private static function header(array $lines): array
    {
        $data = ['title' => array_shift($lines), 'emoji' => null, 'tags' => [], 'source' => null];

        // „🧀 Omas Käsespätzle“ → Emoji + Titel
        if (preg_match('/^([^\p{L}\p{N}\s]\S{0,15})\s+(.+)$/u', $data['title'], $m)) {
            [$data['emoji'], $data['title']] = [$m[1], $m[2]];
        }

        $description = [];

        foreach ($lines as $line) {
            if (preg_match('/^tags:\s*(.*)$/iu', $line, $m)) {
                $data['tags'] = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
            } elseif (preg_match('/^quelle:\s*(.*)$/iu', $line, $m)) {
                $data['source'] = trim($m[1]) ?: null;
            } elseif (! self::parseFacts($line, $data)) {
                $description[] = $line;
            }
        }

        $description = implode(' ', $description) ?: null;

        return [
            ...$data,
            'summary' => $description ? Str::limit($description, 250) : null,
            'description' => $description,
        ];
    }

    /**
     * „4 Portionen · 20 Min. Vorbereitung · …“ – true, wenn die Zeile eine Eckdaten-Zeile war.
     *
     * @param  array<string, mixed>  $data
     */
    private static function parseFacts(string $line, array &$data): bool
    {
        $found = [];
        $difficulties = collect(Difficulty::cases())->mapWithKeys(fn (Difficulty $d) => [mb_strtolower($d->label()) => $d->value]);

        foreach (preg_split('/\s*·\s*/u', $line) as $part) {
            $part = mb_strtolower(trim($part));

            match (true) {
                (bool) preg_match('/^(\d+)\s*portion(en)?$/u', $part, $m) => $found['servings'] = (int) $m[1],
                (bool) preg_match('/^(\d+)\s*min\.?\s*vorbereitung$/u', $part, $m) => $found['prep_minutes'] = (int) $m[1],
                (bool) preg_match('/^(\d+)\s*min\.?\s*kochen(\/backen)?$/u', $part, $m) => $found['cook_minutes'] = (int) $m[1],
                (bool) preg_match('/^(\d+)\s*kcal$/u', $part, $m) => $found['calories'] = (int) $m[1],
                (bool) preg_match('/^(\d+)\s*g\s*protein$/u', $part, $m) => $found['protein'] = (int) $m[1],
                $difficulties->has($part) => $found['difficulty'] = $difficulties[$part],
                default => $found = null,
            };

            if ($found === null) {
                return false;
            }
        }

        $data = [...$data, ...$found];

        return true;
    }

    /**
     * @return array{amount: string, unit: string, name: string, is_optional: bool}
     */
    private static function ingredient(string $line): array
    {
        $line = self::stripBullet($line);
        $optional = (bool) preg_match('/\s*\(optional\)$/iu', $line);
        $line = preg_replace('/\s*\(optional\)$/iu', '', $line);
        $parsed = IngredientLineParser::parse($line);

        // Erkannte Einheit so übernehmen, wie sie dasteht („2 Zweige“ bleibt „Zweige“, nicht „Zweig“).
        $unit = $parsed['unit'];
        if ($unit !== null && preg_match('/(\S+)\s+'.preg_quote($parsed['name'], '/').'$/u', $line, $m)) {
            $unit = $m[1];
        }

        return [
            'amount' => $parsed['amount'] ?? '',
            'unit' => $unit ?? '',
            'name' => $parsed['name'],
            'is_optional' => $optional,
        ];
    }

    /**
     * @return array{body: string, parallel: bool}
     */
    private static function step(string $line, int $index): array
    {
        $line = preg_replace('/^\d+\s*[.)]\s*/u', '', self::stripBullet($line));
        $parallel = $index > 0 && str_starts_with(mb_strtolower($line), trim(self::PARALLEL));

        return [
            'body' => trim($parallel ? mb_substr($line, mb_strlen(trim(self::PARALLEL))) : $line),
            'parallel' => $parallel,
        ];
    }

    private static function stripBullet(string $line): string
    {
        return trim(preg_replace('/^[•\-*–·]\s*/u', '', trim($line)));
    }

    private static function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
