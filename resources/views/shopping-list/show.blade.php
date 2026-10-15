<x-layouts.app title="Einkaufsliste">
    @php
        $dateLabel = fn ($date) => App\Support\Week::dayName($date->dayOfWeekIso).', '.$date->format('d.m.');
    @endphp

    <div x-data="shoppingList(@js($list->export()))">
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

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('meal-plan.show') }}" class="btn btn-ghost">Zum Essensplan</a>

                @unless ($list->isEmpty())
                    {{-- Teilen: Teilen-Dialog des Geräts, sonst eigenes Menü --}}
                    <div class="relative" @click.outside="menuOpen = false" @keydown.escape.window="menuOpen = false">
                        <button type="button" class="btn btn-primary" @click="share()" :aria-expanded="menuOpen">
                            <span aria-hidden="true">↗</span> Teilen
                        </button>

                        <div x-cloak x-show="menuOpen" x-transition.origin.top.right
                             class="absolute right-0 z-20 mt-2 w-72 overflow-hidden rounded-2xl border border-line bg-white py-2 text-sm shadow-[0_12px_32px_-12px_rgb(43_36_32/0.25)]"
                             role="menu">
                            <p class="px-4 pt-1 pb-2 text-xs text-ink-soft">
                                <span x-text="remaining">{{ $list->items->count() }}</span> offene Zutaten teilen – abgehakte werden weggelassen.
                            </p>
                            <button type="button" role="menuitem" @click="copy()" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-paper">
                                <span aria-hidden="true">📋</span>
                                <span x-text="copied ? 'Kopiert!' : 'Text kopieren'">Text kopieren</span>
                            </button>
                            <a role="menuitem" :href="whatsAppUrl" target="_blank" rel="noopener" class="flex items-center gap-3 px-4 py-2.5 hover:bg-paper">
                                <span aria-hidden="true">💬</span> Per WhatsApp senden
                            </a>
                            <a role="menuitem" :href="mailUrl" class="flex items-center gap-3 px-4 py-2.5 hover:bg-paper">
                                <span aria-hidden="true">✉️</span> Per E-Mail senden
                            </a>
                            <button type="button" role="menuitem" @click="download()" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-paper">
                                <span aria-hidden="true">📄</span> Als Textdatei speichern
                            </button>
                        </div>
                    </div>
                @endunless
            </div>
        </div>

        {{-- Letzte Rückfallebene, wenn das Gerät nicht automatisch kopieren kann --}}
        <div x-cloak x-show="manualCopy" class="card-section mb-8 space-y-3">
            <div class="flex items-start justify-between gap-4">
                <p class="text-sm">
                    <strong>Automatisches Kopieren geht auf diesem Gerät nicht.</strong>
                    Der Text ist markiert – einfach kopieren (lange drücken bzw. Strg+C).
                </p>
                <button type="button" @click="manualCopy = false" class="text-ink-soft hover:text-ink" aria-label="Schließen">✕</button>
            </div>
            <textarea x-ref="manualCopy" readonly rows="8" class="field font-mono text-xs"></textarea>
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
                <section class="order-2 lg:order-1">
                    @if ($list->isEmpty())
                        <p class="rounded-3xl border border-dashed border-line px-6 py-10 text-center text-ink-soft">
                            Die geplanten Gerichte haben keine Rezepte – es gibt keine Zutaten einzukaufen.
                        </p>
                    @else
                        <div class="mb-4 flex items-baseline justify-between gap-4">
                            <h2 class="font-display text-2xl font-semibold">Zutaten</h2>
                            <p class="text-sm text-ink-soft" aria-live="polite">
                                <span x-text="done.length">0</span> von {{ $list->itemsToBuy()->count() }} abgehakt
                            </p>
                        </div>
                        <p class="mb-4 text-xs text-ink-soft">Tipp: Hake ab, was schon im Haus ist – beim Teilen wird es weggelassen.</p>

                        @foreach ($list->sections() as $section)
                            @if ($section['heading'])
                                <h3 class="mt-8 mb-3 text-sm font-semibold tracking-wider text-ink-soft uppercase">{{ $section['heading'] }}</h3>
                            @endif

                            <ul class="divide-y divide-line overflow-hidden rounded-3xl border border-line bg-white">
                                @foreach ($section['items'] as $id => $item)
                                    <li class="flex items-start transition hover:bg-paper" :class="done.includes('{{ $id }}') && 'opacity-40'">
                                        <label class="flex min-w-0 flex-1 cursor-pointer items-start gap-4 py-3.5 pl-5">
                                            <input type="checkbox" value="{{ $id }}" x-model="done" aria-label="{{ $item->exportLine() }}" class="mt-1 size-5 shrink-0 rounded accent-accent">
                                            <span class="w-24 shrink-0 pt-0.5 font-semibold tabular-nums"
                                                  :class="done.includes('{{ $id }}') && 'line-through'">{{ $item->amountLabel() }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-medium" :class="done.includes('{{ $id }}') && 'line-through'">{{ $item->name }}</span>
                                                <span class="block text-xs text-ink-soft">für {{ $item->sourceLabel() }}</span>
                                            </span>
                                        </label>
                                        <form method="POST" action="{{ route('pantry-items.store') }}" class="shrink-0 py-3 pr-4 pl-2">
                                            @csrf
                                            <input type="hidden" name="name" value="{{ $item->name }}">
                                            <button type="submit" class="rounded-full px-2.5 py-1 text-xs font-medium text-ink-soft hover:bg-paper-deep hover:text-ink"
                                                    title="„{{ $item->name }}“ haben wir immer da – nicht mehr auf die Liste setzen">
                                                immer da
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach

                        @if ($list->itemsToBuy()->isEmpty())
                            <p class="rounded-3xl border border-dashed border-line px-6 py-8 text-center text-ink-soft">
                                Alles, was gebraucht wird, ist immer da – nichts einzukaufen.
                            </p>
                        @endif
                    @endif

                    {{-- Haben wir immer da --}}
                    @if ($list->pantryItems()->isNotEmpty())
                        <details class="group mt-8 rounded-3xl border border-line bg-white">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold">
                                <span>🏠 Haben wir immer da ({{ $list->pantryItems()->count() }})</span>
                                <span class="text-ink-soft transition group-open:rotate-180" aria-hidden="true">⌄</span>
                            </summary>
                            <p class="px-5 pb-2 text-xs text-ink-soft">Wird nicht geteilt. Falls etwas doch aus ist: „doch einkaufen“.</p>
                            <ul class="divide-y divide-line border-t border-line">
                                @foreach ($list->pantryItems() as $item)
                                    <li class="flex items-center justify-between gap-4 px-5 py-3">
                                        <span class="min-w-0">
                                            <span class="block font-medium">{{ $item->name }}</span>
                                            <span class="block text-xs text-ink-soft">{{ trim($item->amountLabel().' · für '.$item->sourceLabel(), ' ·') }}</span>
                                        </span>
                                        <form method="POST" action="{{ route('pantry-items.destroy', $item->pantryItemId) }}" class="shrink-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost py-1.5! text-xs">doch einkaufen</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
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
    </div>
</x-layouts.app>
