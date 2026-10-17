<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Übernimmt alte Fotodateien (storage/app/public/recipes) in die Datenbank.
 * Auf jeder Installation einmal ausführen – jede findet nur die Fotos, die bei ihr hochgeladen wurden.
 */
class MoveRecipeImages extends Command
{
    protected $signature = 'recipes:move-images';

    protected $description = 'Übernimmt Rezeptfotos, die als Datei auf dieser Installation liegen, in die Datenbank';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $pending = Recipe::query()->whereNotNull('image_path')->whereNull('image_hash')->orderBy('title')->get();

        if ($pending->isEmpty()) {
            $this->components->info('Keine alten Fotodateien mehr offen – alle Fotos liegen in der Datenbank.');

            return self::SUCCESS;
        }

        $moved = $missing = $failed = 0;

        foreach ($pending as $recipe) {
            if (! $disk->exists($recipe->image_path)) {
                $missing++;
                $this->components->twoColumnDetail($recipe->title, '<fg=yellow>Datei liegt nicht hier</>');

                continue;
            }

            try {
                $recipe->storeImage($disk->get($recipe->image_path), $disk->mimeType($recipe->image_path) ?: 'image/jpeg');
                $moved++;
                $this->components->twoColumnDetail($recipe->title, '<fg=green>übernommen</>');
            } catch (Throwable $e) {
                $failed++;
                $this->components->twoColumnDetail($recipe->title, '<fg=red>Fehler</>');
                $this->line('  '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->components->info("{$moved} übernommen, {$missing} nicht auf dieser Installation, {$failed} fehlgeschlagen.");

        if ($missing > 0) {
            $this->components->warn('Die fehlenden Fotos liegen auf der anderen Installation – den Befehl dort ebenfalls ausführen.');
        }

        if ($failed > 0) {
            $this->components->warn('Bei „packet bigger than max_allowed_packet“ muss max_allowed_packet in der MariaDB erhöht werden.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
