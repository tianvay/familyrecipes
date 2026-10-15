<?php

namespace App\Models;

use App\Enums\RevisionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Eintrag im Änderungsprotokoll eines Rezepts.
 *
 * changes enthält bei Bearbeitungen eine Liste von Einträgen:
 *  - Einzelwerte: ['field' => 'cook_minutes', 'old' => '15 Min.', 'new' => '20 Min.']
 *  - Listen:      ['field' => 'ingredients', 'added' => [...], 'removed' => [...], 'reordered' => false]
 */
class RecipeRevision extends Model
{
    public const UPDATED_AT = null;

    private const FIELD_LABELS = [
        'title' => 'Titel',
        'summary' => 'Kurzbeschreibung',
        'description' => 'Beschreibung',
        'emoji' => 'Emoji',
        'color' => 'Farbe',
        'image' => 'Foto',
        'servings' => 'Portionen',
        'prep_minutes' => 'Vorbereitung',
        'cook_minutes' => 'Koch-/Backzeit',
        'difficulty' => 'Schwierigkeit',
        'calories' => 'Kalorien',
        'protein' => 'Protein',
        'source' => 'Quelle',
        'notes' => 'Notizen',
        'tags' => 'Tags',
        'ingredients' => 'Zutaten',
        'steps' => 'Zubereitung',
        'tips' => 'Tipps & Varianten',
    ];

    protected $fillable = ['recipe_title', 'user_id', 'user_name', 'action', 'changes'];

    protected function casts(): array
    {
        return [
            'action' => RevisionAction::class,
            'changes' => 'array',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? $field;
    }

    /**
     * Name der Person zum Zeitpunkt der Änderung; ohne Person war es ein Import oder Seeder.
     */
    public function authorName(): string
    {
        return $this->user_name ?? 'System';
    }

    /**
     * Vergleicht zwei Schnappschüsse (siehe Recipe::auditSnapshot()).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<array<string, mixed>>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $new) {
            $old = $before[$field] ?? null;

            if (is_array($new)) {
                $old = (array) $old;
                $added = array_values(array_diff($new, $old));
                $removed = array_values(array_diff($old, $new));
                $reordered = ! $added && ! $removed && $old !== $new;

                if ($added || $removed || $reordered) {
                    $changes[] = compact('field', 'added', 'removed', 'reordered');
                }

                continue;
            }

            if ((string) $old !== (string) $new) {
                $changes[] = ['field' => $field, 'old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }
}
