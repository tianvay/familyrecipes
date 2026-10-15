<?php

namespace Database\Seeders;

use App\Actions\SaveRecipe;
use App\Enums\Difficulty;
use App\Models\Recipe;
use Illuminate\Database\Seeder;

/**
 * Drei Beispielrezepte aus der ursprünglichen recipes.json.
 */
class RecipeSeeder extends Seeder
{
    public function run(SaveRecipe $saveRecipe): void
    {
        foreach ($this->recipes() as $data) {
            $saveRecipe->handle(new Recipe, $data);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recipes(): array
    {
        return [
            [
                'title' => 'Süßkartoffel-Pfanne mit Kräuter-Dip',
                'summary' => 'Knusprige Süßkartoffeln aus der Pfanne mit frischem Kräuter-Dip.',
                'description' => 'Knusprige Süßkartoffeln aus der Pfanne mit einem frischen Kräuter-Dip. Optional kann Hähnchen für eine proteinreiche Variante ergänzt werden.',
                'emoji' => '🍠',
                'color' => '#E8853D',
                'servings' => 2,
                'prep_minutes' => 10,
                'cook_minutes' => 20,
                'difficulty' => Difficulty::Easy,
                'calories' => 340,
                'protein' => 12,
                'tags' => ['Pfanne', 'Vegetarisch', 'High Protein', 'Meal Prep', 'Wenig Abwasch'],
                'ingredients' => [
                    ['amount' => '500–600', 'unit' => 'g', 'name' => 'Süßkartoffeln'],
                    ['amount' => '1–2', 'unit' => 'EL', 'name' => 'Butter'],
                    ['amount' => '250', 'unit' => 'g', 'name' => 'Magerquark oder griechischer Joghurt'],
                    ['name' => 'Frische Kräuter'],
                    ['name' => 'Salz'],
                    ['name' => 'Schwarzer Pfeffer'],
                    ['name' => 'Chiliflocken'],
                    ['amount' => '200–250', 'unit' => 'g', 'name' => 'Hähnchenbrust oder Putenschnitzel', 'is_optional' => true],
                    ['amount' => '1', 'unit' => 'Stück', 'name' => 'Zitrone', 'is_optional' => true],
                ],
                'steps' => [
                    'Süßkartoffeln schälen und in etwa 1 cm große Würfel schneiden.',
                    'Optional Hähnchen oder Putenschnitzel in Streifen schneiden.',
                    'Kräuter fein hacken.',
                    'Magerquark oder Joghurt mit Kräutern, Zitronensaft, Salz und Pfeffer verrühren.',
                    'Butter in einer Pfanne erhitzen und die Süßkartoffeln 5–7 Minuten anbraten.',
                    '3–4 EL Wasser hinzufügen, Deckel auflegen und weitere 5 Minuten dämpfen.',
                    'Deckel entfernen und die Kartoffeln knusprig anbraten.',
                    'Mit Salz, Pfeffer und Chiliflocken würzen.',
                    'Optional das Fleisch in derselben Pfanne separat anbraten.',
                    'Mit dem Kräuter-Dip servieren.',
                ],
                'tips' => [
                    'Mit Hähnchen: ca. 490 kcal und 40 g Protein pro Portion.',
                ],
            ],
            [
                'title' => 'Crispy Tofu & Brokkoli',
                'summary' => 'Knuspriger Tofu und Brokkoli aus dem Airfryer.',
                'description' => 'Knuspriger Natur-Tofu und Brokkoli aus dem Airfryer – proteinreich, ballaststoffreich und perfekt für eine gesunde Ernährung.',
                'emoji' => '🥦',
                'color' => '#5DAA68',
                'servings' => 1,
                'prep_minutes' => 10,
                'cook_minutes' => 15,
                'difficulty' => Difficulty::VeryEasy,
                'calories' => 280,
                'protein' => 22,
                'tags' => ['Airfryer', 'High Protein', 'Vegetarisch', 'Meal Prep', 'Schnell'],
                'ingredients' => [
                    ['amount' => '1', 'unit' => 'Block', 'name' => 'Natur-Tofu'],
                    ['amount' => '1', 'unit' => 'Kopf', 'name' => 'Brokkoli'],
                    ['amount' => '1', 'unit' => 'EL', 'name' => 'Sojasauce'],
                    ['name' => 'Knoblauchpulver'],
                    ['name' => 'Paprikapulver (edelsüß)'],
                ],
                'steps' => [
                    'Tofu in etwa 2 cm große Würfel schneiden.',
                    'Brokkoli in mundgerechte Röschen teilen.',
                    'Tofu und Brokkoli mit Sojasauce sowie den Gewürzen vermengen.',
                    'Alles in den Airfryer geben und bei 200 °C für 12–15 Minuten garen.',
                    'Nach der Hälfte der Garzeit den Korb kräftig schütteln.',
                    'Direkt heiß servieren.',
                ],
                'tips' => [
                    'Den Tofu vorher gut ausdrücken, damit er besonders knusprig wird.',
                    'Backpapier-Einlagen erleichtern die Reinigung des Airfryers.',
                    'Mit Sesam, Sriracha oder Erdnusssauce servieren.',
                ],
            ],
            [
                'title' => 'High Protein Cheesecake',
                'summary' => 'Cremiger Cheesecake auf Skyr-Basis.',
                'description' => 'Cremiger Cheesecake auf Skyr-Basis mit viel Protein und wenig Kalorien. Ideal als gesunder Snack oder Dessert.',
                'emoji' => '🍰',
                'color' => '#F5D76E',
                'servings' => 8,
                'prep_minutes' => 10,
                'cook_minutes' => 50,
                'difficulty' => Difficulty::Easy,
                'calories' => 95,
                'protein' => 11,
                'tags' => ['Dessert', 'Backofen', 'High Protein', 'Meal Prep', 'Kalorienarm'],
                'ingredients' => [
                    ['amount' => '500', 'unit' => 'g', 'name' => 'Skyr'],
                    ['amount' => '200', 'unit' => 'g', 'name' => 'Frischkäse (fettreduziert)'],
                    ['amount' => '2', 'unit' => 'Stück', 'name' => 'Eier'],
                    ['amount' => '1', 'unit' => 'Päckchen', 'name' => 'Puddingpulver'],
                    ['name' => 'Zitronen Flavor Powder'],
                ],
                'steps' => [
                    'Den Backofen auf 180 °C vorheizen.',
                    'Alle Zutaten in eine große Schüssel geben und zu einer glatten Masse verrühren.',
                    'Die Masse gleichmäßig in eine Kuchenform füllen.',
                    'Den Cheesecake 40 bis 60 Minuten backen.',
                    'Nach dem Backen vollständig auf Zimmertemperatur abkühlen lassen.',
                    'Vor dem Servieren optional einige Stunden kalt stellen.',
                ],
                'tips' => [
                    'Gut gekühlt schmeckt der Cheesecake besonders cremig.',
                    'Für mehr Aroma etwas Zitronenabrieb ergänzen.',
                    'Mit Beeren oder Vanille Flavor Powder variieren.',
                ],
                'notes' => 'Nährwerte gelten pro Stück bei acht Stücken.',
            ],
        ];
    }
}
