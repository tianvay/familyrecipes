@props(['recipe'])

<article class="group relative flex flex-col overflow-hidden rounded-3xl border border-line bg-white transition hover:-translate-y-0.5 hover:shadow-[0_12px_32px_-12px_rgb(43_36_32/0.25)]"
         style="--recipe: {{ $recipe->accent_color }}">
    <div class="recipe-tint relative aspect-[4/3] overflow-hidden">
        @if ($recipe->image_url)
            <img src="{{ $recipe->image_url }}" alt="" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
        @else
            <span class="absolute inset-0 grid place-items-center text-7xl transition duration-500 group-hover:scale-110" aria-hidden="true">
                {{ $recipe->emoji ?: '🍽️' }}
            </span>
        @endif

        @if ($recipe->is_favorite)
            <span class="absolute top-3 right-3 grid size-8 place-items-center rounded-full bg-white/90 text-accent shadow-sm" title="Favorit">♥</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-5">
        <h2 class="font-display text-lg leading-snug font-semibold">
            <a href="{{ route('recipes.show', $recipe) }}" class="after:absolute after:inset-0">
                {{ $recipe->title }}
            </a>
        </h2>

        @if ($recipe->summary || $recipe->description)
            <p class="line-clamp-2 text-sm text-ink-soft">{{ $recipe->summary ?: $recipe->description }}</p>
        @endif

        <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-1 pt-2 text-xs font-medium text-ink-soft">
            @if ($recipe->total_minutes)
                <span>⏱ {{ $recipe->total_minutes }} Min.</span>
            @endif
            @if ($recipe->servings)
                <span>🍽 {{ $recipe->servings }} {{ $recipe->servings === 1 ? 'Portion' : 'Portionen' }}</span>
            @endif
            @if ($recipe->difficulty)
                <span>{{ $recipe->difficulty->label() }}</span>
            @endif
        </div>
    </div>
</article>
