<?php

namespace App\Models;

use App\Enums\Difficulty;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
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
        'image_path',
        'servings',
        'prep_minutes',
        'cook_minutes',
        'difficulty',
        'calories',
        'protein',
        'source',
        'tips',
        'notes',
        'is_favorite',
    ];

    protected function casts(): array
    {
        return [
            'difficulty' => Difficulty::class,
            'tips' => 'array',
            'is_favorite' => 'boolean',
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
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
    protected function favorites(Builder $query): void
    {
        $query->where('is_favorite', true);
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
        return Attribute::get(
            // asset() statt Storage::url(), damit die URL zur aufgerufenen Adresse passt
            // (artisan serve, XAMPP unter /rezeptbuch, Zugriff per LAN-IP).
            fn (): ?string => $this->image_path ? asset('storage/'.$this->image_path) : null
        );
    }

    protected function accentColor(): Attribute
    {
        return Attribute::get(fn (): string => $this->color ?: '#C8553D');
    }
}
