# Rezeptbuch

Persönliche Rezeptsammlung auf Basis von Laravel 12, Blade, Tailwind CSS 4 und Alpine.js.

## Einrichtung

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # legt 3 Beispielrezepte an
php artisan recipes:import   # übrige Rezepte aus database/data/recipes.json
php artisan storage:link     # für hochgeladene Fotos
npm run build
```

Entwicklung (Server, Queue, Logs und Vite gleichzeitig):

```bash
composer run dev
```

Danach unter http://localhost:8000 erreichbar. Standardmäßig wird SQLite verwendet
(`database/database.sqlite`); für MySQL aus XAMPP die `DB_*`-Werte in `.env` anpassen.

## Aufbau

| Bereich | Ort |
| --- | --- |
| Models | `app/Models` – `Recipe`, `Ingredient`, `Step`, `Tag` |
| Schwierigkeitsgrade | `app/Enums/Difficulty.php` |
| Speichern inkl. Zutaten/Schritten/Tags/Bild | `app/Actions/SaveRecipe.php` |
| Validierung | `app/Http/Requests/StoreRecipeRequest.php` |
| Controller | `app/Http/Controllers/RecipeController.php` (Resource), `ToggleFavoriteController.php` |
| Views | `resources/views/recipes`, Layout unter `resources/views/components/layouts` |
| Beispielrezepte | `database/seeders/RecipeSeeder.php` |
| Ursprüngliche Rezeptdaten | `database/data/recipes.json` |

## Tests

```bash
php artisan test
```
