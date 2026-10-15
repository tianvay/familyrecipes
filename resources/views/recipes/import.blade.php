<x-layouts.app title="Rezept importieren">
    <div class="mx-auto max-w-xl">
        <a href="{{ route('recipes.index') }}" class="mb-6 inline-flex items-center gap-1 text-sm font-medium text-ink-soft hover:text-ink">
            ← Alle Rezepte
        </a>
        <h1 class="mb-2 font-display text-4xl font-semibold tracking-tight">Rezept importieren</h1>
        <p class="mb-8 text-ink-soft">
            Link zu einem Rezept einfügen, z. B. von Chefkoch, Lecker oder einem Food-Blog.
            Die Daten landen im Rezeptformular – dort prüfst du alles und speicherst.
        </p>

        <form method="POST" action="{{ route('recipes.import.store') }}" class="card-section space-y-5"
              x-data="{ busy: false }" @submit="busy = true">
            @csrf

            <div>
                <label for="url" class="field-label">Link zum Rezept</label>
                <input id="url" type="url" name="url" value="{{ old('url') }}" required autofocus inputmode="url"
                       class="field" placeholder="https://www.chefkoch.de/rezepte/…">
                @error('url') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn btn-primary w-full" :disabled="busy">
                <span x-show="! busy">Rezept übernehmen</span>
                <span x-cloak x-show="busy">Wird geladen …</span>
            </button>

            <p class="text-xs text-ink-soft">
                Tipp am iPhone/iPad: Auf der Rezeptseite über „Teilen → Kopieren“ den Link kopieren und hier einfügen.
            </p>
        </form>
    </div>
</x-layouts.app>
