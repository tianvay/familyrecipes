# Familien-Rezeptbuch – Hinweise für Claude

Laravel-12-App (Blade, Tailwind 4, Alpine.js) für eine Familie im Heimnetz. UI, Code-Kommentare,
Commit-Messages und Antworten an den Nutzer: **Deutsch**. Details zu Funktionen stehen in `README.md`.

## Arbeitsweise mit dem Nutzer

- Nutzer ist das Familienoberhaupt. 6 echte Accounts (Admin, Papa, Mama = Erwachsene/Admin; Joshua,
  Michelle, Chantal = Kinder). Nur 2 nutzen den Desktop, alle anderen **iPhone/iPad** → mobil zuerst denken.
- **Committen/pushen nur auf ausdrückliche Bitte.** Vorher Diff auf Geheimnisse prüfen (`DB_PASSWORD`,
  `APP_KEY`, keine `storage/app`-/SQLite-Dateien). Themen in getrennte Commits. Branch: `master`,
  Remote: https://github.com/tianvay/familyrecipes. Commit-Footer: `Co-Authored-By: Claude …`.
- Die Datenbank ist **die echte Familien-Datenbank**. Testdaten nur auf freien Tagen/klar markiert
  („Claude-Test“), IDs merken und danach gezielt wieder löschen; echte Einträge nie anfassen.
- System-/Sicherheitseinstellungen (Windows-Firewall, Router, Rechte auf dem DB-Server) nicht selbst
  ändern – Befehl/SQL vorbereiten, Nutzer führt aus.
- Nach Features: Tests, Pint, `npm run build`, im Browser (built-in, `http://localhost`) prüfen,
  README ergänzen, am Ende Commit anbieten. Ideenlisten nach Umsetzbarkeit sortieren.

## Umgebung

- Windows 11, XAMPP unter `C:\xampp`, PHP 8.2 (**ohne GD und intl**), Node 22.
- Apache: VirtualHost **Port 80** → `public/` (`C:\xampp\apache\conf\extra\httpd-vhosts.conf`, `Listen 80`
  in `httpd.conf`). Port 8080 = anderes Projekt *worldbuilder* (nicht anfassen). Erreichbar unter
  `http://192.168.178.64` bzw. `http://fredrgb.fritz.box`; Firewall-Regel „Rezeptbuch (Apache, Heimnetz)“.
- Datenbank: MariaDB auf **192.168.178.38**, DB `FamilyRecipes`, Benutzer `familyrecipes` (Passwort nur
  in `.env`). Migrationen dort mit `php artisan migrate --force`.
- Lokales XAMPP-MySQL nutzt nur *worldbuilder* (am 15.10.2026 repariert, Backup
  `C:\xampp\mysql\data-backup-2026-10-15`). Dort liegen noch ungenutzte DB `rezeptbuch` + Benutzer
  `rezeptbuch` – Löschen nur nach Rückfrage. `database/database.sqlite` = alte Kopie, ungenutzt.
- Standard-Admin per Migration: `Admin` / `Test` (für Browser-Tests auf `http://localhost`).
- Kein HTTPS → `navigator.share`, Clipboard-API, Wake Lock, Service Worker nicht verfügbar
  (Fallbacks sind eingebaut). Zeitzone `Europe/Berlin`.

## Befehle

```bash
php artisan test            # SQLite in-memory (phpunit.xml), echte DB bleibt unberührt
vendor/bin/pint             # Code-Stil
npm run build               # nach jeder Änderung an Views/CSS/JS nötig (kein Vite-Dev-Server im Betrieb)
php artisan view:clear
php artisan users:create    # Account per Konsole; recipes:import = alte recipes.json importieren
```

## Aufbau

- `app/Actions/` – schreibende Abläufe: `SaveRecipe` (inkl. Änderungsprotokoll), `DeleteRecipe`,
  `SaveMealPlan`, `ImportRecipeFromUrl`.
- `app/Support/` – Domänenlogik: `Week` (ISO-Woche, Route-Binding `{week}` = `2026-W43`),
  `WishList` (Wunsch offen bis im Plan; „erfüllt“ wird berechnet), `ShoppingList`/`ShoppingListItem`/
  `Quantity` (Mengen addieren), `CookingSteps` (Zutaten/Timer je Schritt), `LastEaten`,
  `RecipeImport/` (`JsonLdRecipeParser`, `IngredientLineParser`, `UrlGuard` gegen Heimnetz-Abrufe).
- Rechte: `App\Enums\Role` (admin/adult/child), Policies `RecipePolicy`, `UserPolicy`,
  `MealSuggestionPolicy`, Gates in `AppServiceProvider` (`plan-meals`, `view-shopping-list`,
  `view-family-favorites`, `view-revision-log`).
- Model `MealSuggestion` = ein **Wunsch** (UI sagt „Wunsch/wünschen“, Tabelle heißt `meal_suggestions`).
- Frontend: Alpine-Komponenten in `resources/js/*.js`, registriert in `app.js` (`shoppingList`,
  `cookingMode`, `recipePicker`, `recipeAppearance`). Layouts: `components/layouts/{app,guest,focus}`,
  gemeinsamer `<head>` in `partials/head.blade.php`.
- Tests: `travelTo('2026-10-14 18:00')` (Mittwoch, KW 42) für Wochenlogik; Factory-States
  `User::factory()->admin()/child()`, `Recipe::factory()->complete()`.

## Stolpersteine

- `Model::shouldBeStrict()` ist aktiv: kein Lazy Loading (immer `with()`), Mass-Assignment strikt
  (z. B. kein `firstOrCreate(['key' => …])` bei berechneten Spalten).
- Datumsspalten nur mit `whereDate`/`betweenDates` vergleichen (SQLite speichert `Y-m-d H:i:s`).
- Blade: nach einem einzeiligen `@php(...)` keinen `@php … @endphp`-Block mehr in derselben Datei.
- `@js(...)` kodiert Umlaute als `\u00fc` – in Tests View-Daten (`assertViewHas`) statt Text prüfen.
- Route `rezepte/importieren` muss vor `Route::resource('rezepte')` stehen.
- Bash-Heredocs mit Backslashes (PHP-Namespaces) werden zerschossen → Edit/Write-Tool oder
  Python-Skript als Datei im Scratchpad nutzen.
- Keine GD: Fotos werden im Browser verkleinert (`recipe-appearance.js`), Server-Limit 12 MB.

## Offene Ideen (nach Umsetzbarkeit)

1. Portionen umrechnen auf der Rezeptseite (`Quantity` vorhanden)
2. Rezeptauswahl mit Suche/Favoriten auch im Planungsformular (heute `<select>`)
3. Bewertung nach dem Essen („Wie war’s?“, auch Kinder) → Grundlage für 4
4. Statistik: „oft gewünscht, selten gekocht“, Lieblingsgerichte je Person (Daten: `meal_suggestions`,
   `meal_plan_entries`, Erfüllung via `WishList`) – vom Nutzer gewünscht, Details noch besprechen
5. Essensplan als Kalender-Abo (iCal) für iPhone-Kalender
6. Abneigungen/Allergien pro Person mit Warnung beim Planen
7. Küchendienst/Tischdienst pro Tag
8. Größer/Infrastruktur: VPN über FRITZ!Box, gemeinsame Live-Einkaufsliste, HTTPS im Heimnetz
   (eigene CA auf 5 Geräten), Offline/Push

Noch nicht auf echtem iPhone/iPad verifiziert: Wunschliste in Handygröße, Timer-Signalton,
Kamera-Upload, Home-Bildschirm-App. Bekannte Grenze: „Ei“/„Eier“ werden auf der Einkaufsliste nicht
zusammengefasst.
