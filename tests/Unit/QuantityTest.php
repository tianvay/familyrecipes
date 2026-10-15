<?php

namespace Tests\Unit;

use App\Support\Quantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    /**
     * @return array<string, array{string|null, string|null}>
     */
    public static function amounts(): array
    {
        return [
            'ganze Zahl' => ['200', '200'],
            'Komma' => ['0,5', '0,5'],
            'Punkt' => ['1.5', '1,5'],
            'Bruch' => ['1/2', '0,5'],
            'Bruchzeichen' => ['½', '0,5'],
            'Bereich Halbgeviertstrich' => ['500–600', '500–600'],
            'Bereich Bindestrich mit Leerzeichen' => ['2 - 3', '2–3'],
            'leer' => ['', null],
            'null' => [null, null],
            'Text' => ['eine Prise', null],
            'drei Teile' => ['1-2-3', null],
        ];
    }

    #[DataProvider('amounts')]
    public function test_parse_and_format(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, Quantity::parse($input)?->format());
    }

    public function test_quantities_can_be_added_including_ranges(): void
    {
        $this->assertSame('500', Quantity::parse('200')->add(Quantity::parse('300'))->format());
        $this->assertSame('700–800', Quantity::parse('500–600')->add(Quantity::parse('200'))->format());
        $this->assertSame('1,5', Quantity::parse('0,5')->add(Quantity::parse('1'))->format());
        $this->assertSame('1500', Quantity::parse('1000')->add(Quantity::parse('500'))->format());
    }
}
