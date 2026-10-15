{{-- Ein Gericht in der Tagesliste der Startseite. $entry: MealPlanEntry|null, $large: bool --}}
@if ($entry)
    @php($recipe = $entry->recipe)
    <div class="flex min-w-0 items-center gap-3" @if ($recipe) style="--recipe: {{ $recipe->accent_color }}" @endif>
        <span @class([
            'grid shrink-0 place-items-center rounded-xl',
            'recipe-tint' => $recipe,
            'bg-paper-deep' => ! $recipe,
            'size-12 text-2xl' => $large,
            'size-9 text-lg' => ! $large,
        ]) aria-hidden="true">{{ $recipe?->emoji ?: ($recipe ? '🍽️' : '✏️') }}</span>
        @if ($recipe)
            <a href="{{ route('recipes.show', $recipe) }}" @class(['min-w-0 font-semibold leading-snug hover:text-accent-dark', 'text-lg' => $large])>{{ $entry->displayTitle() }}</a>
        @else
            <span @class(['min-w-0 font-semibold leading-snug', 'text-lg' => $large])>{{ $entry->displayTitle() }}</span>
        @endif
    </div>
@else
    <span class="text-sm text-ink-soft">Noch nichts geplant</span>
@endif
