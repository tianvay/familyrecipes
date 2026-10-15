{{--
    Wünsche, gleiche Gerichte zusammengefasst (siehe App\Support\WishList::group()).
    $groups:  Collection der Gruppen
    $empty:   Text, wenn es keine gibt
    $compact: schmale Darstellung (z. B. Seitenleiste beim Planen)
--}}
@php
    $compact ??= false;
    $currentWeek = App\Support\Week::current();
@endphp

@if ($groups->isEmpty())
    <p class="rounded-2xl border border-dashed border-line px-5 py-6 text-center text-sm text-ink-soft">{{ $empty }}</p>
@else
    <ul class="space-y-3">
        @foreach ($groups as $group)
            @php
                $count = $group['suggestions']->count();
                $plannedOn = $group['plannedOn'];
            @endphp
            <li @class([
                    'rounded-2xl border bg-white',
                    'border-herb/40' => $plannedOn,
                    'border-line' => ! $plannedOn,
                    'p-4' => ! $compact,
                    'p-3' => $compact,
                ])
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
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold leading-snug">
                            @if ($group['recipe'])
                                <a href="{{ route('recipes.show', $group['recipe']) }}" class="hover:text-accent-dark">{{ $group['title'] }}</a>
                            @else
                                <span>{{ $group['title'] }}</span>
                            @endif
                            <span class="rounded-full bg-accent/10 px-2 py-0.5 text-xs font-semibold text-accent-dark" title="{{ $count }}× gewünscht">{{ $count }}×</span>
                            @if ($plannedOn)
                                <span class="rounded-full bg-herb/10 px-2 py-0.5 text-xs font-semibold text-herb">
                                    ✓ eingeplant: {{ mb_substr(App\Support\Week::dayName($plannedOn->dayOfWeekIso), 0, 2) }} {{ $plannedOn->format('d.m.') }}
                                </span>
                            @endif
                        </p>

                        <ul class="mt-1.5 space-y-1 text-xs text-ink-soft">
                            @foreach ($group['suggestions'] as $wish)
                                @php($wishWeek = App\Support\Week::containing($wish->week_start))
                                <li class="flex flex-wrap items-center gap-x-1.5">
                                    <span class="font-medium text-ink">{{ $wish->user?->name ?? 'Ehemaliges Mitglied' }}</span>
                                    @if ($wishWeek->start->greaterThan($currentWeek->start))
                                        <span>· ab KW {{ $wishWeek->number() }}</span>
                                    @elseif ($wishWeek->start->lessThan($currentWeek->start))
                                        <span>· seit KW {{ $wishWeek->number() }}</span>
                                    @endif
                                    @if ($wish->preferred_day)
                                        <span>· am liebsten {{ App\Support\Week::dayName($wish->preferred_day) }}</span>
                                    @endif
                                    @if ($wish->note)
                                        <span>· „{{ $wish->note }}“</span>
                                    @endif
                                    @if (! $plannedOn)
                                        @can('delete', $wish)
                                            <form method="POST" action="{{ route('wishes.destroy', $wish) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-accent-dark underline decoration-accent/30 underline-offset-2 hover:decoration-accent-dark">
                                                    {{ $wish->user_id === auth()->id() ? 'zurückziehen' : 'entfernen' }}
                                                </button>
                                            </form>
                                        @endcan
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
