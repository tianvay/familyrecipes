<?php

namespace App\Support\RecipeImport;

use Illuminate\Support\Str;

/**
 * Liest ein Rezept aus den strukturierten Daten einer Webseite
 * (<script type="application/ld+json"> mit schema.org/Recipe), wie sie Chefkoch,
 * Lecker, EatSmarter, viele Food-Blogs (WordPress-Plugins) usw. mitliefern.
 */
final class JsonLdRecipeParser
{
    /**
     * @return array{title: string, description: string|null, servings: int|null, prep_minutes: int|null, cook_minutes: int|null, calories: int|null, protein: int|null, ingredients: list<array{amount: string|null, unit: string|null, name: string}>, steps: list<string>, tags: list<string>, image_url: string|null}|null
     */
    /**
     * Kategorien von Rezeptseiten, die als Tag nichts aussagen.
     */
    private const IGNORED_TAGS = ['kochen', 'backen', 'braten', 'gekocht', 'gebacken', 'raffiniert oder preiswert', 'rezept', 'rezepte'];

    public static function parse(string $html): ?array
    {
        [$recipe, $nodes] = self::findRecipeNode($html);

        if ($recipe === null || blank($title = self::text($recipe['name'] ?? null))) {
            return null;
        }

        // Viele Seiten (z. B. Chefkoch, WordPress-Plugins) verweisen per @id auf andere Einträge
        $resolve = fn (mixed $value) => is_array($value) && isset($value['@id']) && count($value) === 1
            ? ($nodes[$value['@id']] ?? $value)
            : $value;

        $title = self::withoutAuthor($title, $resolve($recipe['author'] ?? null), $resolve);

        $prep = self::minutes($recipe['prepTime'] ?? null);
        $cook = self::minutes($recipe['cookTime'] ?? null);
        $total = self::minutes($recipe['totalTime'] ?? null);

        if ($cook === null && $total !== null) {
            $cook = max(0, $total - (int) $prep) ?: null;
        }

        return [
            'title' => Str::limit($title, 250, ''),
            'description' => self::description(self::text($recipe['description'] ?? null), $title),
            'servings' => self::servings($recipe['recipeYield'] ?? null),
            'prep_minutes' => $prep,
            'cook_minutes' => $cook,
            'calories' => self::firstInt($recipe['nutrition']['calories'] ?? null),
            'protein' => self::firstInt($recipe['nutrition']['proteinContent'] ?? null),
            'ingredients' => array_values(array_filter(array_map(
                fn ($line) => is_string($line) && filled(trim($line)) ? IngredientLineParser::parse($line) : null,
                self::list($recipe['recipeIngredient'] ?? $recipe['ingredients'] ?? []),
            ))),
            'steps' => self::steps($recipe['recipeInstructions'] ?? []),
            'tags' => self::tags($recipe),
            'image_url' => self::imageUrl($recipe['image'] ?? null, $resolve),
        ];
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: array<string, array<string, mixed>>} Rezept und alle Einträge nach @id
     */
    private static function findRecipeNode(string $html): array
    {
        preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches);

        foreach ($matches[1] as $json) {
            $data = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5), true);

            if ($node = self::searchRecipe($data)) {
                return [$node, self::indexById($data)];
            }
        }

        return [null, []];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function indexById(mixed $data, array $index = []): array
    {
        if (! is_array($data)) {
            return $index;
        }

        if (isset($data['@id']) && is_string($data['@id']) && count($data) > 1) {
            $index[$data['@id']] ??= $data;
        }

        foreach ($data as $value) {
            $index = self::indexById($value, $index);
        }

        return $index;
    }

    /**
     * Beschreibungen sind oft Werbetexte für Suchmaschinen:
     * „Feine Linsensuppe - Eine würzige … Über 1037 Bewertungen … Mit ► Portionsrechner ► Kochbuch“.
     */
    private static function description(string $description, string $title): ?string
    {
        // Vorangestellten Titel entfernen
        $description = preg_replace('/^'.preg_quote($title, '/').'\s*[-–:|]\s*/iu', '', $description);

        // Ab Werbe-Floskeln abschneiden
        $description = preg_split('/\s*(?:Über \d[\d.]* Bewertungen|Mit ►|►)/u', $description)[0];

        return trim($description) ?: null;
    }

    /**
     * Chefkoch hängt den Autor an den Titel („Linseneintopf von Surina“) – den entfernen.
     */
    private static function withoutAuthor(string $title, mixed $author, \Closure $resolve): string
    {
        $first = $resolve(self::list($author)[0] ?? null);
        $name = is_array($first) ? ($first['name'] ?? null) : $first;

        if (! is_string($name) || blank($name)) {
            return $title;
        }

        $suffix = ' von '.trim($name);

        return str_ends_with(mb_strtolower($title), mb_strtolower($suffix))
            ? trim(mb_substr($title, 0, -mb_strlen($suffix)))
            : $title;
    }

    private static function searchRecipe(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $type = $data['@type'] ?? null;

        if ($type === 'Recipe' || (is_array($type) && in_array('Recipe', $type, true))) {
            return $data;
        }

        foreach ($data['@graph'] ?? (array_is_list($data) ? $data : []) as $node) {
            if ($found = self::searchRecipe($node)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Anleitung als Text, Liste, HowToStep oder HowToSection mit Unterschritten.
     *
     * @return list<string>
     */
    private static function steps(mixed $instructions): array
    {
        if (is_string($instructions)) {
            $instructions = preg_split('/\R+/u', self::text($instructions, keepLineBreaks: true));
        }

        $steps = [];

        foreach (self::list($instructions) as $item) {
            if (is_string($item)) {
                $steps[] = self::text($item);
            } elseif (is_array($item) && isset($item['itemListElement'])) {
                array_push($steps, ...self::steps($item['itemListElement']));
            } elseif (is_array($item)) {
                $steps[] = self::text($item['text'] ?? $item['name'] ?? null);
            }
        }

        return array_values(array_map(
            fn (string $step) => Str::limit($step, 1990, ''),
            array_filter($steps, 'filled'),
        ));
    }

    /**
     * Stichworte und Kategorien als Tags (höchstens 6).
     *
     * @param  array<string, mixed>  $recipe
     * @return list<string>
     */
    private static function tags(array $recipe): array
    {
        $raw = [];

        foreach (['recipeCategory', 'recipeCuisine', 'keywords'] as $field) {
            foreach (self::list($recipe[$field] ?? []) as $value) {
                if (is_string($value)) {
                    array_push($raw, ...explode(',', $value));
                }
            }
        }

        return collect($raw)
            ->map(fn (string $tag) => Str::ucfirst(trim(self::text($tag))))
            ->filter(fn (string $tag) => $tag !== '' && mb_strlen($tag) <= 30 && ! in_array(mb_strtolower($tag), self::IGNORED_TAGS, true))
            ->unique(fn (string $tag) => mb_strtolower($tag))
            ->take(6)
            ->values()
            ->all();
    }

    private static function imageUrl(mixed $image, \Closure $resolve): ?string
    {
        $first = $resolve(self::list($resolve($image))[0] ?? null);
        $url = is_array($first) ? ($first['url'] ?? $first['contentUrl'] ?? null) : $first;

        return is_string($url) && str_starts_with($url, 'http') ? $url : null;
    }

    /**
     * ISO-8601-Dauer („PT1H30M“, „P0DT0H20M“) in Minuten.
     */
    private static function minutes(mixed $duration): ?int
    {
        if (! is_string($duration) || ! preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:\d+S)?)?$/i', trim($duration), $m)) {
            return null;
        }

        $minutes = (int) ($m[1] ?? 0) * 1440 + (int) ($m[2] ?? 0) * 60 + (int) ($m[3] ?? 0);

        return $minutes > 0 && $minutes <= 1440 ? $minutes : null;
    }

    private static function servings(mixed $yield): ?int
    {
        foreach (self::list($yield) as $value) {
            if (($servings = self::firstInt($value)) && $servings <= 100) {
                return $servings;
            }
        }

        return null;
    }

    private static function firstInt(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return (int) round($value);
        }

        return is_string($value) && preg_match('/\d+/', $value, $m) ? (int) $m[0] : null;
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        return match (true) {
            $value === null => [],
            is_array($value) && array_is_list($value) => $value,
            default => [$value],
        };
    }

    private static function text(mixed $value, bool $keepLineBreaks = false): string
    {
        if (! is_string($value)) {
            return '';
        }

        $text = html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $value)), ENT_QUOTES | ENT_HTML5);

        return $keepLineBreaks
            ? trim(preg_replace('/[^\S\n]+/u', ' ', $text))
            : trim(preg_replace('/\s+/u', ' ', $text));
    }
}
