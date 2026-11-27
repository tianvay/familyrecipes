<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Step;
use InvalidArgumentException;

/**
 * Rezept-Code zum Teilen: ein Rezept als kurzer Text („RZB1:…“), der sich per
 * Messenger oder Mail verschicken und in jedem Familien-Rezeptbuch wieder einlesen lässt.
 *
 * Aufbau: Präfix mit Formatversion + base64url(deflate(JSON)) + „.“ als Endzeichen
 * (damit Text hinter dem Code nicht mitgelesen wird). Das JSON nutzt kurze
 * Schlüssel, damit der Code handlich bleibt. Nicht enthalten sind Foto (zu groß),
 * Notizen (familienintern), Favoriten und Verlauf.
 */
class RecipeShareCode
{
    public const PREFIX = 'RZB1:';

    public const END = '.';

    /** Obergrenze für das entpackte JSON – schützt vor „Zip-Bomben“. */
    private const MAX_JSON_BYTES = 256 * 1024;

    public static function encode(Recipe $recipe): string
    {
        $recipe->loadMissing(['ingredients', 'steps', 'tags']);

        $data = array_filter([
            't' => $recipe->title,
            's' => $recipe->summary,
            'd' => $recipe->description,
            'e' => $recipe->emoji,
            'c' => $recipe->color,
            'p' => $recipe->servings,
            'pm' => $recipe->prep_minutes,
            'cm' => $recipe->cook_minutes,
            'df' => $recipe->difficulty?->value,
            'kc' => $recipe->calories,
            'pr' => $recipe->protein,
            'q' => $recipe->source,
            'tg' => $recipe->tags->pluck('name')->all(),
            'tp' => $recipe->tips ?? [],
            // Zutat: [Menge, Einheit, Name, optional?] – leere Felder am Ende entfallen
            'i' => $recipe->ingredients->map(fn (Ingredient $ingredient) => self::trimTrailing([
                (string) $ingredient->amount,
                (string) $ingredient->unit,
                $ingredient->name,
                $ingredient->is_optional ? 1 : 0,
            ]))->all(),
            // Schritt: Text oder [Text, 1], wenn er gleichzeitig mit dem vorigen läuft
            'st' => $recipe->steps->map(fn (Step $step) => $step->parallel ? [$step->body, 1] : $step->body)->all(),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return self::PREFIX.rtrim(strtr(base64_encode(gzdeflate($json, 9)), '+/', '-_'), '=').self::END;
    }

    /**
     * Nachricht zum Verschicken: das Rezept lesbar für alle, darunter der Code fürs Rezeptbuch.
     */
    public static function message(Recipe $recipe): string
    {
        $recipe->loadMissing(['ingredients', 'steps', 'tags']);

        $facts = array_filter([
            $recipe->servings ? "{$recipe->servings} ".($recipe->servings === 1 ? 'Portion' : 'Portionen') : null,
            $recipe->prep_minutes ? "{$recipe->prep_minutes} Min. Vorbereitung" : null,
            $recipe->cook_minutes ? "{$recipe->cook_minutes} Min. Kochen/Backen" : null,
        ]);

        $lines = [trim(($recipe->emoji ?: '🍽️').' '.$recipe->title)];

        if ($recipe->summary) {
            $lines[] = $recipe->summary;
        }

        if ($facts) {
            $lines[] = implode(' · ', $facts);
        }

        $lines[] = '';
        $lines[] = 'Zutaten:';

        foreach ($recipe->ingredients as $ingredient) {
            $lines[] = '• '.$ingredient->label();
        }

        $lines[] = '';
        $lines[] = 'Zubereitung:';

        foreach ($recipe->steps->values() as $i => $step) {
            $lines[] = ($i + 1).'. '.($step->parallel ? '(gleichzeitig) ' : '').$step->body;
        }

        if ($recipe->tips) {
            $lines[] = '';
            $lines[] = 'Tipps:';

            foreach ($recipe->tips as $tip) {
                $lines[] = '• '.$tip;
            }
        }

        $lines[] = '';
        $lines[] = 'Rezept-Code fürs Familien-Rezeptbuch (ganze Nachricht unter „Rezept importieren“ einfügen):';
        $lines[] = self::encode($recipe);

        return implode("\n", $lines);
    }

    /**
     * Findet den Code auch mitten in einer weitergeleiteten Nachricht.
     */
    public static function containsCode(string $text): bool
    {
        return stripos($text, self::PREFIX) !== false;
    }

    /**
     * Liest einen Rezept-Code und liefert Formulareingaben (wie old()) für recipes.create.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException mit einer Meldung für die Nutzerin/den Nutzer
     */
    public static function decode(string $text): array
    {
        $data = self::unpack($text);

        $title = self::string($data['t'] ?? null);
        $ingredients = collect(is_array($data['i'] ?? null) ? $data['i'] : [])
            ->filter(fn ($row) => is_array($row) && filled(self::string($row[2] ?? null)))
            ->map(fn (array $row) => [
                'amount' => self::string($row[0] ?? null) ?? '',
                'unit' => self::string($row[1] ?? null) ?? '',
                'name' => self::string($row[2]),
                'is_optional' => (bool) ($row[3] ?? false),
            ])
            ->values()
            ->all();
        $steps = collect(is_array($data['st'] ?? null) ? $data['st'] : [])
            ->map(fn ($step) => is_array($step)
                ? ['body' => self::string($step[0] ?? null), 'parallel' => (bool) ($step[1] ?? false)]
                : ['body' => self::string($step), 'parallel' => false])
            ->filter(fn (array $step) => filled($step['body']))
            ->values()
            ->all();

        if ($title === null || $ingredients === [] || $steps === []) {
            throw new InvalidArgumentException('Der Rezept-Code ist unvollständig (Titel, Zutaten oder Zubereitung fehlen).');
        }

        return [
            'title' => $title,
            'summary' => self::string($data['s'] ?? null),
            'description' => self::string($data['d'] ?? null),
            'emoji' => self::string($data['e'] ?? null),
            'color' => self::string($data['c'] ?? null),
            'servings' => self::integer($data['p'] ?? null),
            'prep_minutes' => self::integer($data['pm'] ?? null),
            'cook_minutes' => self::integer($data['cm'] ?? null),
            'difficulty' => self::string($data['df'] ?? null),
            'calories' => self::integer($data['kc'] ?? null),
            'protein' => self::integer($data['pr'] ?? null),
            'source' => self::string($data['q'] ?? null),
            'tags' => self::stringList($data['tg'] ?? null),
            'tips' => self::stringList($data['tp'] ?? null),
            'ingredients' => $ingredients,
            'steps' => $steps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function unpack(string $text): array
    {
        $start = stripos($text, self::PREFIX);

        if ($start === false) {
            throw new InvalidArgumentException('Das ist kein Rezept-Code. Er beginnt mit „'.self::PREFIX.'“.');
        }

        // Messenger brechen lange Codes manchmal um: bis zum Endzeichen lesen und Leerraum entfernen.
        // Fehlt das Endzeichen (abgeschnitten?), nur bis zum ersten Leerraum lesen.
        $rest = substr($text, $start + strlen(self::PREFIX));
        $end = strpos($rest, self::END);
        $rest = $end === false ? preg_split('/\s/', $rest)[0] : preg_replace('/\s+/', '', substr($rest, 0, $end));
        preg_match('/^[A-Za-z0-9_-]*$/', $rest, $match);

        $packed = isset($match[0]) ? base64_decode(strtr($match[0], '-_', '+/'), strict: true) : false;
        $json = $packed === false || $packed === '' ? false : @gzinflate($packed, self::MAX_JSON_BYTES);
        $data = $json === false ? null : json_decode($json, true);

        if (! is_array($data)) {
            throw new InvalidArgumentException('Der Rezept-Code ist beschädigt oder unvollständig. Bitte den ganzen Code kopieren.');
        }

        return $data;
    }

    /**
     * @param  list<string|int>  $values
     * @return list<string|int>
     */
    private static function trimTrailing(array $values): array
    {
        while ($values !== [] && in_array(end($values), ['', 0], true)) {
            array_pop($values);
        }

        return $values;
    }

    private static function string(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function integer(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn ($item) => self::string($item))
            ->filter()
            ->values()
            ->all();
    }
}
