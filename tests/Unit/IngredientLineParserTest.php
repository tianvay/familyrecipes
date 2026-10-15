<?php

namespace Tests\Unit;

use App\Support\RecipeImport\IngredientLineParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IngredientLineParserTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null, string|null, string}>
     */
    public static function lines(): array
    {
        return [
            'Gramm' => ['200 g Mehl', '200', 'g', 'Mehl'],
            'Esslöffel' => ['1 EL Olivenöl', '1', 'EL', 'Olivenöl'],
            'Bruch' => ['1/2 TL Salz', '0,5', 'TL', 'Salz'],
            'Bruchzeichen ohne Einheit' => ['½ Zitrone', '0,5', null, 'Zitrone'],
            'gemischte Zahl' => ['1 ½ EL Zucker', '1,5', 'EL', 'Zucker'],
            'Bereich mit (n)' => ['2-3 Zehe(n) Knoblauch', '2–3', 'Zehe', 'Knoblauch'],
            'Bereich mit bis' => ['4 bis 6 Stück Äpfel', '4–6', 'Stück', 'Äpfel'],
            'Abkürzung mit Punkt' => ['1 Pck. Vanillezucker', '1', 'Päckchen', 'Vanillezucker'],
            'Komma' => ['1,5 kg Rinderbraten', '1,5', 'kg', 'Rinderbraten'],
            'Zusatz bleibt' => ['250 ml Milch (3,5 % Fett)', '250', 'ml', 'Milch (3,5 % Fett)'],
            'ohne Einheit' => ['2 Eier', '2', null, 'Eier'],
            'Adjektiv ist keine Einheit' => ['1 kleine Zwiebel(n)', '1', null, 'kleine Zwiebel'],
            'ohne Menge' => ['Salz und Pfeffer', null, null, 'Salz und Pfeffer'],
            'HTML-Reste' => ['200&nbsp;g <b>Feta</b>', '200', 'g', 'Feta'],
        ];
    }

    #[DataProvider('lines')]
    public function test_parse(string $line, ?string $amount, ?string $unit, string $name): void
    {
        $this->assertSame(
            ['amount' => $amount, 'unit' => $unit, 'name' => $name],
            IngredientLineParser::parse($line),
        );
    }
}
