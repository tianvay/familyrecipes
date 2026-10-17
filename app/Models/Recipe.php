<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\RevisionAction;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'summary',
        'description',
        'emoji',
        'color',
        'servings',
        'prep_minutes',
        'cook_minutes',
        'difficulty',
        'calories',
        'protein',
        'source',
        'tips',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'difficulty' => Difficulty::class,
            'tips' => 'array',
            'servings' => 'integer',
            'prep_minutes' => 'integer',
            'cook_minutes' => 'integer',
            'calories' => 'integer',
            'protein' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Recipe $recipe) {
            $recipe->slug ??= static::uniqueSlug($recipe->title);
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title, language: 'de') ?: 'rezept';
        $slug = $base;

        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class)->orderBy('position');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class)->orderBy('position');
    }

    /**
     * Das Foto in der Datenbank. Nur zum Ausliefern laden – für die Anzeige reicht image_hash.
     */
    public function image(): HasOne
    {
        return $this->hasOne(RecipeImage::class);
    }

    /**
     * Speichert ein Foto in der Datenbank und ersetzt ein vorhandenes.
     */
    public function storeImage(string $contents, string $mimeType): void
    {
        $this->image()->updateOrCreate([], [
            'mime_type' => $mimeType,
            'size' => strlen($contents),
            'data' => $contents,
        ]);

        // image_path (alte Fotodatei) wird nicht mehr gebraucht; die Datei bleibt als Sicherung liegen.
        $this->forceFill(['image_hash' => sha1($contents), 'image_path' => null])->save();
    }

    public function removeImage(): void
    {
        $this->image()->delete();

        $this->forceFill(['image_hash' => null, 'image_path' => null])->save();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    /**
     * Familienmitglieder, die das Rezept als Favorit markiert haben.
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(RecipeRevision::class)->latest('id');
    }

    public function latestRevision(): HasOne
    {
        return $this->hasOne(RecipeRevision::class)->latestOfMany();
    }

    /**
     * Schreibt einen Eintrag ins Änderungsprotokoll. Titel und Name werden kopiert,
     * damit der Eintrag auch nach dem Löschen lesbar bleibt.
     *
     * @param  list<array<string, mixed>>|null  $changes
     */
    public function recordRevision(RevisionAction $action, ?User $user, ?array $changes = null): RecipeRevision
    {
        return $this->revisions()->create([
            'recipe_title' => $this->title,
            'user_id' => $user?->getKey(),
            'user_name' => $user?->name,
            'action' => $action,
            'changes' => $changes,
        ]);
    }

    /**
     * Lesbarer Stand des Rezepts für das Änderungsprotokoll.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        $this->load(['ingredients', 'steps', 'tags']);

        $withUnit = fn (?int $value, string $unit) => $value === null ? null : "{$value} {$unit}";

        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => $this->description,
            'emoji' => $this->emoji,
            'color' => $this->color,
            'image' => $this->image_hash ?? $this->image_path,
            'servings' => $this->servings,
            'prep_minutes' => $withUnit($this->prep_minutes, 'Min.'),
            'cook_minutes' => $withUnit($this->cook_minutes, 'Min.'),
            'difficulty' => $this->difficulty?->label(),
            'calories' => $withUnit($this->calories, 'kcal'),
            'protein' => $withUnit($this->protein, 'g'),
            'source' => $this->source,
            'notes' => $this->notes,
            'tags' => $this->tags->pluck('name')->sort()->values()->all(),
            'ingredients' => $this->ingredients->map->label()->all(),
            'steps' => $this->steps->pluck('body')->all(),
            'tips' => $this->tips ?? [],
        ];
    }

    /**
     * Volltextsuche über Titel, Kurzbeschreibung und Zutaten.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            $query->whereLike('title', "%{$term}%")
                ->orWhereLike('summary', "%{$term}%")
                ->orWhereHas('ingredients', fn (Builder $q) => $q->whereLike('name', "%{$term}%"));
        });
    }

    #[Scope]
    protected function withTag(Builder $query, ?string $slug): void
    {
        if (blank($slug)) {
            return;
        }

        $query->whereHas('tags', fn (Builder $q) => $q->where('slug', $slug));
    }

    #[Scope]
    protected function favoriteOf(Builder $query, User $user): void
    {
        $query->whereHas('favoritedBy', fn (Builder $q) => $q->whereKey($user->getKey()));
    }

    /**
     * Ergänzt das Attribut is_favorite für die angegebene Person.
     */
    #[Scope]
    protected function withFavoriteFlag(Builder $query, User $user): void
    {
        $query->withExists(['favoritedBy as is_favorite' => fn (Builder $q) => $q->whereKey($user->getKey())]);
    }

    protected function totalMinutes(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->prep_minutes === null && $this->cook_minutes === null) {
                return null;
            }

            return (int) $this->prep_minutes + (int) $this->cook_minutes;
        });
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => match (true) {
            // ?v= wechselt mit dem Foto, so darf der Browser es dauerhaft zwischenspeichern.
            $this->image_hash !== null => route('recipes.image', ['recipe' => $this, 'v' => substr($this->image_hash, 0, 10)]),
            // Übergang: altes Foto, das noch nicht per recipes:move-images übernommen wurde.
            // asset() statt Storage::url(), damit die URL zur aufgerufenen Adresse passt.
            $this->image_path !== null => asset('storage/'.$this->image_path),
            default => null,
        });
    }

    protected function accentColor(): Attribute
    {
        return Attribute::get(fn (): string => $this->color ?: '#C8553D');
    }
}
