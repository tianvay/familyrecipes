<x-layouts.app title="Einkaufsliste">
    @php
        $dateLabel = fn ($date) => App\Support\Week::dayName($date->dayOfWeekIso).', '.$date->format('d.m.');
        $required = $list->items->reject->optional->values();
        $optional = $list->items->filter->optional->values();
    @endphp

    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-sm font-semibold tracking-widest text-accent uppercase">KW {{ $week->number() }}</p>
            <h1 class="font-display text-4xl font-semibold tracking-tight sm:text-5xl">Einkaufsliste</h1>
            <p class="mt-2 text-ink-soft">
                @if ($list->from->isSameDay($list->to))
                    Für {{ $dateLabel($list->from) }}
                @else
                    Für {{ $dateLabel($list->from) }} bis {{ $dateLabel($list->to) }}
                @endif
                · vergangene Tage sind nicht enthalten
            </p>
        </div>
        <a href="{{ route('meal-plan.show') }}" class="btn btn-ghost">Zum Essensplan</a>
    </div>

    @if ($list->meals->isEmpty())
        <div class="card-section flex flex-col items-center gap-4 py-14 text-center">
            <span class="text-5xl" aria-hidden="true">🛒</span>
            <h2 class="font-display text-2xl font-semibold">Nichts einzukaufen</h2>
            <p class="max-w-sm text-ink-soft">Für den Rest dieser Woche ist noch kein Gericht geplant.</p>
            @can('plan-meals', $week)
                <a href="{{ route('meal-plan.edit', $week) }}" class="btn btn-primary">Plan festlegen</a>
            @endcan
        </div>
    @else
        <div class="grid gap-10 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            {{-- Zutaten zum Abhaken --}}
            <section x-data="{ done: [] }" class="order-2 lg:order-1">
                @if ($list->isEmpty())
                    <p class="rounded-3xl border border-dashed border-line px-6 py-10 text-center text-ink-soft">
                        Die geplanten Gerichte haben keine Rezepte – es gibt keine Zutaten einzukaufen.
                    </p>
                @else
                    <div class="mb-4 flex items-baseline justify-between gap-4">
                        <h2 class="font-display text-2xl font-semibold">Zutaten</h2>
                        <p class="text-sm text-ink-soft" aria-live="polite">
                            <span x-text="done.length">0</span> von {{ $list->items->count() }} erledigt
                        </p>
                    </div>

                    @foreach (['' => $required, 'Optional' => $optional] as $heading => $items)
                        @continue($items->isEmpty())

                        @if ($heading)
                            <h3 class="mt-8 mb-3 text-sm font-semibold tracking-wider text-ink-soft uppercase">{{ $heading }}</h3>
                        @endif

                        <ul class="divide-y divide-line overflow-hidden rounded-3xl border border-line bg-white">
                            @foreach ($items as $item)
                                @php($id = ($heading ?: 'p').$loop->index)
                                <li>
                                    <label class="flex cursor-pointer items-start gap-4 px-5 py-3.5 transition hover:bg-paper"
                                           :class="done.includes('{{ $id }}') && 'opacity-40'">
                                        <input type="checkbox" value="{{ $id }}" x-model="done" class="mt-1 size-5 shrink-0 rounded accent-accent">
                                        <span class="w-24 shrink-0 pt-0.5 font-semibold tabular-nums"
                                              :class="done.includes('{{ $id }}') && 'line-through'">{{ $item->amountLabel() }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-medium" :class="done.includes('{{ $id }}') && 'line-through'">{{ $item->name }}</span>
                                            <span class="block text-xs text-ink-soft">für {{ $item->sourceLabel() }}</span>
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                @endif
            </section>

            {{-- Berücksichtigte Gerichte --}}
            <aside class="order-1 lg:order-2 lg:sticky lg:top-24 lg:self-start">
                <h2 class="mb-4 font-display text-2xl font-semibold">Berücksichtigte Gerichte</h2>
                <ol class="space-y-2">
                    @foreach ($list->meals as $meal)
                        <li class="flex items-center gap-3 rounded-2xl border border-line bg-white px-4 py-3"
                            @if ($meal->recipe) style="--recipe: {{ $meal->recipe->accent_color }}" @endif>
                            <span class="w-9 shrink-0 text-sm font-semibold text-ink-soft">{{ mb_substr(App\Support\Week::dayName($meal->date->dayOfWeekIso), 0, 2) }}</span>
                            <span @class([
                                'grid size-9 shrink-0 place-items-center rounded-xl text-lg',
                                'recipe-tint' => $meal->recipe,
                                'bg-paper-deep' => ! $meal->recipe,
                            ]) aria-hidden="true">{{ $meal->recipe?->emoji ?: ($meal->recipe ? '🍽️' : '✏️') }}</span>
                            <span class="min-w-0">
                                @if ($meal->recipe)
                                    <a href="{{ route('recipes.show', $meal->recipe) }}" class="block text-sm font-semibold leading-snug hover:text-accent-dark">{{ $meal->displayTitle() }}</a>
                                @else
                                    <span class="block text-sm font-semibold leading-snug">{{ $meal->displayTitle() }}</span>
                                    <span class="block text-xs text-ink-soft">kein Rezept – keine Zutaten</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            </aside>
        </div>
    @endif
</x-layouts.app>
