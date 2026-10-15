{{-- Wochenwechsel: zurück immer (Historie), vor nur bis zur nächsten Woche. --}}
<nav class="flex items-center gap-2" aria-label="Woche wechseln">
    <a href="{{ route('meal-plan.show', $week->previous()) }}" class="btn btn-ghost px-3!" title="Vorherige Woche" aria-label="Vorherige Woche">←</a>
    @unless ($week->isCurrent())
        <a href="{{ route('meal-plan.show') }}" class="btn btn-ghost">Diese Woche</a>
    @endunless
    @if ($week->start->lessThan(App\Support\Week::current()->next()->start))
        <a href="{{ route('meal-plan.show', $week->next()) }}" class="btn btn-ghost px-3!" title="Nächste Woche" aria-label="Nächste Woche">→</a>
    @endif
</nav>
