<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::creating(function (Tag $tag) {
            $tag->slug ??= Str::slug($tag->name, language: 'de');
        });
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class);
    }

    /**
     * Wandelt eine Liste von Tag-Namen in Tag-IDs um und legt fehlende Tags an.
     *
     * @param  iterable<string>  $names
     * @return list<int>
     */
    public static function idsFromNames(iterable $names): array
    {
        return collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => Str::slug($name, language: 'de'))
            ->map(fn (string $name) => static::firstOrCreate(['slug' => Str::slug($name, language: 'de')], ['name' => $name])->id)
            ->values()
            ->all();
    }
}
