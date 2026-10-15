<?php

namespace App\Support;

/**
 * Eine Mengenangabe aus einem Rezept, ggf. als Bereich („500–600“).
 * Versteht ganze Zahlen, Kommazahlen („0,5“), Brüche („1/2“, „½“) und Bereiche mit - oder –.
 */
final class Quantity
{
    private const FRACTIONS = ['½' => 0.5, '¼' => 0.25, '¾' => 0.75, '⅓' => 1 / 3, '⅔' => 2 / 3];

    private function __construct(
        public readonly float $min,
        public readonly float $max,
    ) {}

    /**
     * null, wenn die Angabe leer ist oder keine Zahl enthält (z. B. „eine Prise“).
     */
    public static function parse(?string $amount): ?self
    {
        $amount = trim((string) $amount);

        if ($amount === '') {
            return null;
        }

        $parts = preg_split('/\s*[-–]\s*/u', $amount);

        if (count($parts) > 2) {
            return null;
        }

        $values = array_map(self::parseNumber(...), $parts);

        if (in_array(null, $values, true)) {
            return null;
        }

        return new self($values[0], $values[1] ?? $values[0]);
    }

    public function add(self $other): self
    {
        return new self($this->min + $other->min, $this->max + $other->max);
    }

    /**
     * Deutsche Schreibweise: „1,5“, Bereiche als „500–600“.
     */
    public function format(): string
    {
        $min = self::formatNumber($this->min);

        return $this->min === $this->max ? $min : $min.'–'.self::formatNumber($this->max);
    }

    private static function parseNumber(string $value): ?float
    {
        $value = trim($value);

        if (isset(self::FRACTIONS[$value])) {
            return self::FRACTIONS[$value];
        }

        if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', $value, $m) && (int) $m[2] !== 0) {
            return (int) $m[1] / (int) $m[2];
        }

        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }
}
