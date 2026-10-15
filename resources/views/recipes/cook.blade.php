<x-layouts.focus :title="'Kochmodus: ' . $recipe->title">
    <div x-data="cookingMode({ steps: @js($steps) })"
         @keydown.window="onKey($event)"
         @touchstart.passive="onTouchStart($event)"
         @touchend.passive="onTouchEnd($event)"
         class="flex min-h-dvh flex-col"
         style="--recipe: {{ $recipe->accent_color }}">

        {{-- Kopfzeile --}}
        <header class="recipe-tint flex items-center gap-3 px-4 py-3 sm:px-6">
            <a href="{{ route('recipes.show', $recipe) }}" class="grid size-11 shrink-0 place-items-center rounded-full bg-white/70 text-xl hover:bg-white" aria-label="Kochmodus beenden">✕</a>
            <p class="min-w-0 flex-1 truncate font-display text-lg font-semibold sm:text-xl">
                <span aria-hidden="true">{{ $recipe->emoji }}</span> {{ $recipe->title }}
            </p>

            {{-- Laufender Timer --}}
            <button type="button" x-cloak x-show="timer" @click="timer?.done ? stopTimer() : null"
                    :class="timer?.done ? 'bg-accent text-white animate-pulse' : 'bg-white/80'"
                    class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 font-semibold tabular-nums"
                    :aria-label="timer?.done ? 'Timer abgelaufen, tippen zum Beenden' : 'Timer läuft'">
                <span aria-hidden="true">⏱</span>
                <span x-text="timer?.done ? 'Fertig!' : timerDisplay"></span>
            </button>

            <button type="button" @click="showIngredients = true" class="btn btn-ghost shrink-0 bg-white/70">
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
                    <div class="mx-auto w-full max-w-4xl">
                        <p class="mb-4 text-sm font-semibold tracking-widest text-accent uppercase">
                            Schritt <span x-text="step.number"></span> von {{ count($steps) }}
                        </p>
                        <p class="font-display text-3xl leading-snug font-medium sm:text-4xl lg:text-5xl lg:leading-tight" x-text="step.text" aria-live="polite"></p>

                        <div class="mt-8 flex flex-col gap-4">
                            <template x-if="step.ingredients.length">
                                <div>
                                    <p class="mb-2 text-sm font-semibold text-ink-soft">Dafür brauchst du:</p>
                                    <ul class="flex flex-wrap gap-2">
                                        <template x-for="item in step.ingredients" :key="item">
                                            <li class="rounded-full border border-line bg-white px-4 py-2 text-base sm:text-lg" x-text="item"></li>
                                        </template>
                                    </ul>
                                </div>
                            </template>

                            <template x-if="step.timer">
                                <div>
                                    <button type="button" @click="startTimer(step)" x-show="timer?.step !== step.number || timer?.done"
                                            class="btn btn-primary px-6! py-3! text-lg">
                                        ⏱ Timer starten (<span x-text="step.timer.label"></span>)
                                    </button>
                                    <div x-show="timer?.step === step.number && ! timer?.done" class="flex items-center gap-4">
                                        <span class="font-display text-5xl font-semibold tabular-nums" x-text="timerDisplay"></span>
                                        <button type="button" @click="stopTimer()" class="btn btn-ghost">Timer abbrechen</button>
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
            <footer class="border-t border-line bg-white/80 px-4 py-4 backdrop-blur sm:px-6">
                <div class="mx-auto flex max-w-4xl items-center gap-4">
                    <button type="button" @click="previous()" :disabled="isFirst && ! finished"
                            class="btn btn-ghost min-w-28 py-4! text-lg disabled:opacity-30">← Zurück</button>

                    <nav class="flex flex-1 flex-wrap justify-center gap-2" aria-label="Schritte">
                        @foreach ($steps as $i => $step)
                            <button type="button" @click="goTo({{ $i }})"
                                    :class="index === {{ $i }} && ! finished ? 'bg-accent w-6' : (index > {{ $i }} || finished ? 'bg-ink-soft/50 w-2.5' : 'bg-line w-2.5')"
                                    class="h-2.5 rounded-full transition-all" aria-label="Schritt {{ $i + 1 }}"></button>
                        @endforeach
                    </nav>

                    <button type="button" @click="next()" x-show="! finished"
                            class="btn btn-primary min-w-28 py-4! text-lg" x-text="isLast ? 'Fertig ✓' : 'Weiter →'">Weiter →</button>
                </div>
                <p class="mt-2 text-center text-xs text-ink-soft">Tipp: Zum Blättern auch einfach wischen.</p>
            </footer>
        @endif

        {{-- Zutaten als Overlay --}}
        <div x-cloak x-show="showIngredients" x-transition.opacity class="fixed inset-0 z-30 flex justify-end bg-ink/40" @click.self="showIngredients = false">
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
