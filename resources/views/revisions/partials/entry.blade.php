{{--
    Ein Eintrag im Änderungsprotokoll.
    $revision: App\Models\RecipeRevision
    $showRecipe: Rezepttitel mit anzeigen (Gesamtprotokoll)
--}}
@php
    $action = $revision->action;
    $badge = match ($action) {
        App\Enums\RevisionAction::Created => 'bg-herb/10 text-herb',
        App\Enums\RevisionAction::Updated => 'bg-paper-deep text-ink',
        App\Enums\RevisionAction::Deleted => 'bg-accent/10 text-accent-dark',
    };
@endphp

<li class="flex gap-4 p-5 sm:px-6">
    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-paper-deep font-display text-sm font-semibold"
          title="{{ $revision->authorName() }}">
        {{ $revision->user_name ? App\Models\User::initialsFor($revision->user_name) : '⚙' }}
    </span>

    <div class="min-w-0 flex-1">
        <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="font-semibold">{{ $revision->authorName() }}</span>
            <span class="text-ink-soft">hat</span>
            @if ($showRecipe ?? false)
                @if ($revision->recipe)
                    <a href="{{ route('recipes.show', $revision->recipe) }}" class="font-semibold hover:text-accent-dark">„{{ $revision->recipe_title }}“</a>
                @else
                    <span class="font-semibold">„{{ $revision->recipe_title }}“</span>
                @endif
            @else
                <span class="text-ink-soft">das Rezept</span>
            @endif
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ $action->verb() }}</span>
        </p>
        <p class="mt-0.5 text-xs text-ink-soft">
            <time datetime="{{ $revision->created_at->toIso8601String() }}" title="{{ $revision->created_at->format('d.m.Y H:i') }}">
                {{ $revision->created_at->format('d.m.Y, H:i') }} Uhr · {{ $revision->created_at->diffForHumans() }}
            </time>
        </p>

        @if ($revision->changes)
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($revision->changes as $change)
                    <li class="rounded-xl bg-paper px-3 py-2">
                        <span class="font-semibold">{{ App\Models\RecipeRevision::fieldLabel($change['field']) }}:</span>

                        @if (array_key_exists('added', $change))
                            @foreach ($change['removed'] as $item)
                                <span class="mt-1 block text-accent-dark"><span aria-hidden="true">−</span> <span class="line-through decoration-accent-dark/40">{{ $item }}</span></span>
                            @endforeach
                            @foreach ($change['added'] as $item)
                                <span class="mt-1 block text-herb"><span aria-hidden="true">+</span> {{ $item }}</span>
                            @endforeach
                            @if ($change['reordered'])
                                <span class="text-ink-soft">Reihenfolge geändert</span>
                            @endif
                        @elseif ($change['field'] === 'image')
                            <span class="text-ink-soft">
                                {{ match (true) {
                                    blank($change['old']) => 'Foto hinzugefügt',
                                    blank($change['new']) => 'Foto entfernt',
                                    default => 'Foto ersetzt',
                                } }}
                            </span>
                        @else
                            @if (filled($change['old']))
                                <span class="text-accent-dark line-through decoration-accent-dark/40">{{ Str::limit($change['old'], 160) }}</span>
                                <span class="text-ink-soft" aria-hidden="true">→</span>
                            @endif
                            @if (filled($change['new']))
                                <span class="text-herb">{{ Str::limit($change['new'], 160) }}</span>
                            @else
                                <span class="text-ink-soft">entfernt</span>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</li>
