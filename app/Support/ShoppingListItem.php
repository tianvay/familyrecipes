<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Eine Zeile der Einkaufsliste: eine Zutat, zusammengefasst über alle Gerichte.
 */
final class ShoppingListItem
{
    /**
     * @param  Collection<int, array{title: string, date: CarbonImmutable}>  $sources  Für welche Gerichte (an welchem Tag)
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $unit,
        public readonly ?Quantity $quantity,
        public readonly ?string $rawAmount,
        public readonly bool $optional,
        public readonly Collection $sources,
    ) {}

    /**
     * z. B. „500 g“, „2 Stück“, „eine Prise“ oder leer bei „nach Bedarf“.
     */
    public function amountLabel(): string
    {
        $amount = $this->quantity?->format() ?? $this->rawAmount;

        return trim(implode(' ', array_filter([$amount, $this->unit], 'filled')));
    }

    /**
     * z. B. „Lasagne (Mo, Fr), Gemüsesuppe (Mi)“
     */
    public function sourceLabel(): string
    {
        return $this->sources
            ->groupBy('title')
            ->map(fn (Collection $uses, string $title) => $title.' ('.$uses->map(
                fn (array $use) => mb_substr(Week::dayName($use['date']->dayOfWeekIso), 0, 2)
            )->unique()->join(', ').')')
            ->join(', ');
    }
}
