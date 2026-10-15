<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Eine Kalenderwoche (Montag bis Sonntag), in URLs als ISO-Woche „2026-W43“.
 */
final class Week implements UrlRoutable
{
    public readonly CarbonImmutable $start;

    private function __construct(CarbonImmutable $date)
    {
        $this->start = $date->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
    }

    public static function current(): self
    {
        return new self(CarbonImmutable::now());
    }

    public static function containing(CarbonImmutable|string $date): self
    {
        return new self(CarbonImmutable::parse($date));
    }

    public static function fromIso(string $value): self
    {
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $value, $m) || (int) $m[2] < 1 || (int) $m[2] > 53) {
            throw new InvalidArgumentException("Ungültige Woche: {$value}");
        }

        $week = new self(CarbonImmutable::now()->setISODate((int) $m[1], (int) $m[2]));

        // „2026-W53“ gibt es nicht in jedem Jahr; Carbon würde in die Folgewoche rollen.
        if ($week->iso() !== $value) {
            throw new InvalidArgumentException("Ungültige Woche: {$value}");
        }

        return $week;
    }

    public function iso(): string
    {
        return $this->start->format('o-\WW');
    }

    public function end(): CarbonImmutable
    {
        return $this->start->addDays(6)->endOfDay();
    }

    public function number(): int
    {
        return (int) $this->start->isoWeek();
    }

    /**
     * @return Collection<int, CarbonImmutable> Schlüssel 1 (Montag) bis 7 (Sonntag)
     */
    public function days(): Collection
    {
        return collect(range(1, 7))->mapWithKeys(fn (int $day) => [$day => $this->start->addDays($day - 1)]);
    }

    public function previous(): self
    {
        return new self($this->start->subWeek());
    }

    public function next(): self
    {
        return new self($this->start->addWeek());
    }

    public function equals(self $other): bool
    {
        return $this->start->equalTo($other->start);
    }

    public function isCurrent(): bool
    {
        return $this->equals(self::current());
    }

    /**
     * Vorschlagen und Planen ist nur für die laufende und die kommende Woche möglich.
     */
    public function isOpen(): bool
    {
        return $this->isCurrent() || $this->equals(self::current()->next());
    }

    /**
     * z. B. „KW 43 · 19.10. – 25.10.2026“
     */
    public function label(): string
    {
        return sprintf('KW %d · %s – %s', $this->number(), $this->start->format('d.m.'), $this->end()->format('d.m.Y'));
    }

    /**
     * „Diese Woche“, „Nächste Woche“, „Letzte Woche“ oder null.
     */
    public function relativeName(): ?string
    {
        $current = self::current();

        return match (true) {
            $this->equals($current) => 'Diese Woche',
            $this->equals($current->next()) => 'Nächste Woche',
            $this->equals($current->previous()) => 'Letzte Woche',
            default => null,
        };
    }

    public static function dayName(int $day): string
    {
        return ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'][$day];
    }

    // --- UrlRoutable: route('meal-plan.show', $week) ergibt /essensplan/2026-W43 ---

    public function getRouteKey(): string
    {
        return $this->iso();
    }

    public function getRouteKeyName(): string
    {
        return 'week';
    }

    /**
     * Für Route::bind('week', …): ungültige Wochen ergeben ein 404.
     */
    public static function fromRouteKey(string $value): self
    {
        try {
            return self::fromIso($value);
        } catch (InvalidArgumentException) {
            throw (new ModelNotFoundException)->setModel(self::class);
        }
    }

    public function resolveRouteBinding($value, $field = null): self
    {
        return self::fromRouteKey((string) $value);
    }

    public function resolveChildRouteBinding($childType, $value, $field): never
    {
        throw new \LogicException('Wochen haben keine Kind-Bindungen.');
    }
}
