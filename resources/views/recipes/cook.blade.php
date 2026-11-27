<x-layouts.focus :title="'Kochmodus: ' . $recipe->title">
    <div x-data="cookingMode({ pages: @js($pages) })"
         @keydown.window="onKey($event)"
         @touchstart.passive="onTouchStart($event)"
         @touchend.passive="onTouchEnd($event)"
         class="flex min-h-dvh flex-col"
         style="--recipe: {{ $recipe->accent_color }}">

        {{-- Kopfzeile --}}
        <header class="recipe-tint flex items-center gap-3 px-4 py-3 sm:px-6">
            <a href="{{ route('recipes.show', $recipe) }}" class="grid size-11 shrink-0 place-items-center rounded-full bg-surface/70 text-xl hover:bg-surface" aria-label="Kochmodus beenden">✕</a>
            <p class="min-w-0 flex-1 truncate font-display text-lg font-semibold sm:text-xl">
                <span aria-hidden="true">{{ $recipe->emoji }}</span> {{ $recipe->title }}
            </p>

            {{-- Laufende Timer (auch von anderen Seiten); bei mehreren mit Schrittnummer --}}
            <div class="flex min-w-0 flex-wrap justify-end gap-2">
                <template x-for="timer in timers" :key="timer.step">
                    <button type="button" @click="tapTimer(timer)"
                            :class="timer.done ? 'bg-accent text-on-accent animate-pulse' : 'bg-surface/80'"
                            class="flex items-center gap-1.5 rounded-full px-3 py-2 font-semibold tabular-nums sm:px-4"
                            :aria-label="timer.done ? `Timer von Schritt ${timer.step} abgelaufen, tippen zum Beenden` : `Timer von Schritt ${timer.step} läuft, tippen zum Anzeigen`">
                        <span aria-hidden="true">⏱</span>
                        <span x-show="timers.length > 1" class="text-xs opacity-70" x-text="`S${timer.step}`"></span>
                        <span x-text="timer.done ? 'Fertig!' : display(timer)"></span>
                    </button>
                </template>
            </div>

            <button type="button" @click="showIngredients = true" class="btn btn-ghost shrink-0 bg-surface/70">
                <span aria-hidden="true">🥕</span> <span class="hidden sm:inline">Zutaten</span>
            </button>
        </header>

        @if (empty($steps))
            <main class="grid flex-1 place-items-center p-8 text-center">
                <p class="text-xl text-ink-soft">Dieses Rezept hat noch keine Zubereitungsschritte.</p>
            </main>
        @else
            {{-- Schritt --}}
            <main class="flex flex-1 flex-col justify-center px-6 py-8 sm:px-12 lg:px-24">
                <template x-if="! finished">
                    <div class="mx-auto w-full" :class="page.length > 1 ? 'max-w-6xl' : 'max-w-4xl'">
                        <p class="mb-4 text-sm font-semibold tracking-widest text-accent uppercase">
                            <span x-text="page.length > 1 ? `Schritte ${page.map((step) => step.number).join(' + ')}` : `Schritt ${page[0].number}`"></span>
                            von {{ count($steps) }}
                            <span x-show="page.length > 1"> · ⇉ gleichzeitig</span>
                        </p>

                        {{-- Gleichzeitige Schritte: iPhone untereinander, iPad/Desktop nebeneinander --}}
                        <div :class="page.length > 1 && 'grid gap-4 md:grid-cols-2 md:gap-6'" aria-live="polite">
                            <template x-for="step in page" :key="step.number">
                                <div :class="page.length > 1 && 'rounded-3xl border border-line bg-surface p-5 sm:p-6'">
                                    <p x-show="page.length > 1" class="mb-2 text-xs font-semibold tracking-widest text-ink-soft uppercase" x-text="`Schritt ${step.number}`"></p>
                                    <p class="font-display leading-snug font-medium"
                                       :class="page.length > 1 ? 'text-2xl lg:text-3xl' : 'text-3xl sm:text-4xl lg:text-5xl lg:leading-tight'"
                                       x-text="step.text"></p>

                                    <div class="mt-6 flex flex-col gap-4">
                                        <template x-if="step.ingredients.length">
                                            <div>
                                                <p class="mb-2 text-sm font-semibold text-ink-soft">Dafür brauchst du:</p>
                                                <ul class="flex flex-wrap gap-2">
                                                    <template x-for="item in step.ingredients" :key="item">
                                                        <li class="rounded-full border border-line bg-surface px-4 py-2 text-base sm:text-lg" x-text="item"></li>
                                                    </template>
                                                </ul>
                                            </div>
                                        </template>

                                        <template x-if="step.timer">
                                            <div>
                                                <button type="button" @click="startTimer(step)" x-show="! timerFor(step.number) || timerFor(step.number).done"
                                                        class="btn btn-primary px-6! py-3! text-lg">
                                                    ⏱ Timer starten (<span x-text="step.timer.label"></span>)
                                                </button>
                                                <div x-show="timerFor(step.number) && ! timerFor(step.number).done" class="flex flex-wrap items-center gap-4">
                                                    <span class="font-display font-semibold tabular-nums" :class="page.length > 1 ? 'text-4xl' : 'text-5xl'" x-text="display(timerFor(step.number))"></span>
                                                    <button type="button" @click="stopTimer(step.number)" class="btn btn-ghost">Timer abbrechen</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="finished">
                    <div class="mx-auto max-w-xl text-center">
                        <p class="text-7xl" aria-hidden="true">🍽️</p>
                        <p class="mt-4 font-display text-4xl font-semibold sm:text-5xl">Guten Appetit!</p>
                        <div class="mt-8 flex flex-wrap justify-center gap-3">
                            <button type="button" @click="goTo(0)" class="btn btn-ghost">Von vorne</button>
                            <a href="{{ route('recipes.show', $recipe) }}" class="btn btn-primary">Zurück zum Rezept</a>
                        </div>
                    </div>
                </template>
            </main>

            {{-- Navigation --}}
            <footer class="border-t border-line bg-surface/80 px-4 py-4 backdrop-blur sm:px-6">
                <div class="mx-auto flex max-w-4xl items-center gap-4">
                    <button type="button" @click="previous()" :disabled="isFirst && ! finished"
                            class="btn btn-ghost min-w-28 py-4! text-lg disabled:opacity-30">← Zurück</button>

                    <nav class="flex flex-1 flex-wrap justify-center gap-2" aria-label="Schritte">
                        @foreach ($pages as $i => $page)
                            <button type="button" @click="goTo({{ $i }})"
                                    :class="index === {{ $i }} && ! finished ? 'bg-accent w-6' : (index > {{ $i }} || finished ? 'bg-ink-soft/50 w-2.5' : 'bg-line w-2.5')"
                                    class="h-2.5 rounded-full transition-all"
                                    aria-label="{{ count($page) > 1 ? 'Schritte '.implode(' + ', array_column($page, 'number')) : 'Schritt '.$page[0]['number'] }}"></button>
                        @endforeach
                    </nav>

                    <button type="button" @click="next()" x-show="! finished"
                            class="btn btn-primary min-w-28 py-4! text-lg" x-text="isLast ? 'Fertig ✓' : 'Weiter →'">Weiter →</button>
                </div>
                <p class="mt-2 text-center text-xs text-ink-soft">Tipp: Zum Blättern auch einfach wischen.</p>
            </footer>
        @endif

        {{-- Zutaten als Overlay --}}
        <div x-cloak x-show="showIngredients" x-transition.opacity class="fixed inset-0 z-30 flex justify-end bg-black/50" @click.self="showIngredients = false">
            <aside class="flex h-full w-full max-w-md flex-col overflow-y-auto bg-paper p-6 shadow-2xl" role="dialog" aria-label="Zutaten">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-2xl font-semibold">Zutaten</h2>
                    <button type="button" @click="showIngredients = false" class="grid size-11 place-items-center rounded-full hover:bg-paper-deep" aria-label="Schließen">✕</button>
                </div>
                @if ($recipe->servings)
                    <p class="mb-4 text-sm text-ink-soft">für {{ $recipe->servings }} {{ $recipe->servings === 1 ? 'Portion' : 'Portionen' }}</p>
                @endif
                <ul class="divide-y divide-line">
                    @foreach ($recipe->ingredients as $ingredient)
                        <li>
                            <label class="flex cursor-pointer items-baseline gap-3 py-3 text-lg" :class="checked.includes({{ $ingredient->id }}) && 'opacity-40 line-through'">
                                <input type="checkbox" value="{{ $ingredient->id }}" x-model.number="checked" class="size-5 translate-y-0.5 accent-accent">
                                <span>{{ $ingredient->label() }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </aside>
        </div>
    </div>
</x-layouts.focus>
