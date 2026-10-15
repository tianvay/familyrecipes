{{--
    Vorschläge einer Woche, gleiche Gerichte zusammengefasst.
    $suggestionGroups: siehe MealPlanController::suggestionGroups()
    $compact: schmale Darstellung für die Seitenleiste beim Planen
--}}
@php($compact ??= false)

@if ($suggestionGroups->isEmpty())
    <p class="rounded-2xl border border-dashed border-line px-5 py-6 text-center text-sm text-ink-soft">
        Noch keine Vorschläge für diese Woche.
    </p>
@else
    <ul class="space-y-3">
        @foreach ($suggestionGroups as $group)
            @php($count = $group['suggestions']->count())
            <li @class(['rounded-2xl border border-line bg-white', 'p-4' => ! $compact, 'p-3' => $compact])
                @if ($group['recipe']) style="--recipe: {{ $group['recipe']->accent_color }}" @endif>
                <div class="flex items-start gap-3">
                    <span @class([
                        'grid shrink-0 place-items-center rounded-xl',
                        'recipe-tint' => $group['recipe'],
                        'bg-paper-deep' => ! $group['recipe'],
                        'size-11 text-2xl' => ! $compact,
                        'size-9 text-xl' => $compact,
                    ]) aria-hidden="true">{{ $group['recipe']?->emoji ?: ($group['recipe'] ? '🍽️' : '✏️') }}</span>

                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-x-2 font-semibold leading-snug">
                            @if ($group['recipe'])
                                <a href="{{ route('recipes.show', $group['recipe']) }}" class="hover:text-accent-dark">{{ $group['title'] }}</a>
                            @else
                                <span>{{ $group['title'] }}</span>
                            @endif
                            <span class="rounded-full bg-accent/10 px-2 py-0.5 text-xs font-semibold text-accent-dark" title="{{ $count }}× vorgeschlagen">{{ $count }}×</span>
                        </p>

                        <ul class="mt-1.5 space-y-1 text-xs text-ink-soft">
                            @foreach ($group['suggestions'] as $suggestion)
                                <li class="flex flex-wrap items-center gap-x-1.5">
                                    <span class="font-medium text-ink">{{ $suggestion->user?->name ?? 'Ehemaliges Mitglied' }}</span>
                                    @if ($suggestion->preferred_day)
                                        <span>· am liebsten {{ App\Support\Week::dayName($suggestion->preferred_day) }}</span>
                                    @endif
                                    @if ($suggestion->note)
                                        <span>· „{{ $suggestion->note }}“</span>
                                    @endif
                                    @can('delete', $suggestion)
                                        <form method="POST" action="{{ route('meal-suggestions.destroy', $suggestion) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-accent-dark underline decoration-accent/30 underline-offset-2 hover:decoration-accent-dark">
                                                {{ $suggestion->user_id === auth()->id() ? 'zurückziehen' : 'entfernen' }}
                                            </button>
                                        </form>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
