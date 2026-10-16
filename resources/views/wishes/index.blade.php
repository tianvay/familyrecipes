<x-layouts.app title="Wunschliste">
    <div class="mb-8 max-w-2xl">
        <p class="mb-2 text-sm font-semibold tracking-widest text-accent uppercase">Familie</p>
        <h1 class="font-display text-4xl font-semibold tracking-tight sm:text-5xl">Wunschliste</h1>
        <p class="mt-2 text-ink-soft">Was möchtet ihr essen? Wünsche bleiben stehen, bis sie im Essensplan landen.</p>
    </div>

    <div @class(['grid gap-10', 'lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]' => $canWish, 'max-w-3xl' => ! $canWish])>
        @if ($canWish)
            <div class="lg:sticky lg:top-24 lg:self-start">
                @include('wishes.partials.form')
            </div>
        @endif

        <div class="space-y-10">
            <section aria-labelledby="offen">
                <h2 id="offen" class="mb-4 font-display text-2xl font-semibold">
                    Offene Wünsche <span class="text-ink-soft">({{ $openGroups->sum(fn ($g) => $g['suggestions']->count()) }})</span>
                </h2>
                @include('wishes.partials.groups', ['groups' => $openGroups, 'empty' => 'Gerade ist die Wunschliste leer.'])
            </section>

            @if ($plannedGroups->isNotEmpty())
                <section aria-labelledby="eingeplant">
                    <h2 id="eingeplant" class="mb-1 font-display text-2xl font-semibold">Eingeplant 🎉</h2>
                    <p class="mb-4 text-sm text-ink-soft">Diese Wünsche stehen im Essensplan.</p>
                    @include('wishes.partials.groups', ['groups' => $plannedGroups, 'empty' => ''])
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
