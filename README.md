# Rezeptbuch

Rezeptsammlung für die Familie auf Basis von Laravel 12, Blade, Tailwind CSS 4 und Alpine.js.
Gedacht für den Betrieb im lokalen Netzwerk.

## Einrichtung

Die Daten liegen in der MariaDB auf dem Heimserver **192.168.178.38**, Datenbank **`FamilyRecipes`**.
Einmalig direkt auf dem Server (z. B. dessen phpMyAdmin) Datenbank und Benutzer anlegen:

```sql
CREATE DATABASE `FamilyRecipes` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'familyrecipes'@'192.168.178.%' IDENTIFIED BY 'ein-passwort';
GRANT ALL PRIVILEGES ON `FamilyRecipes`.* TO 'familyrecipes'@'192.168.178.%';
```

Danach das Passwort als `DB_PASSWORD` in `.env` eintragen:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed       # Tabellen, Standard-Admin und 3 Beispielrezepte
php artisan recipes:import       # übrige Rezepte aus database/data/recipes.json
php artisan storage:link         # für hochgeladene Fotos
npm run build
```

**Standard-Zugang:** Benutzername `Admin`, Passwort `Test` (Familienoberhaupt, angelegt per Migration).
Das Passwort nach der ersten Anmeldung unter „Mein Konto“ ändern.

Entwicklung (Server, Queue, Logs und Vite gleichzeitig):

```bash
composer run dev
```

Danach unter http://localhost:8000 erreichbar; über XAMPP unter `http://<Rechner-IP>:8080/rezeptbuch`.
Die Tests laufen unabhängig davon gegen eine SQLite-In-Memory-Datenbank (`phpunit.xml`).

## Benutzer & Rollen

Anmeldung mit **Benutzername** und Passwort, keine Selbstregistrierung.

| Rolle | Rezepte ansehen & Favoriten | Rezepte anlegen/ändern/löschen | Accounts, Familie, Änderungsprotokoll |
| --- | :---: | :---: | :---: |
| Familienoberhaupt | ✓ | ✓ | ✓ |
| Erwachsen | ✓ | ✓ | – |
| Kind | ✓ | – | – |

- Jedes Mitglied kann unter „Mein Konto“ das eigene Passwort ändern.
- Der Standard-Admin wird per Migration angelegt; weitere Accounts über „Benutzer verwalten“
  oder `php artisan users:create`.

## Änderungsprotokoll

Jedes Anlegen, Bearbeiten und Löschen eines Rezepts wird mit Person, Zeitpunkt und den
konkreten Änderungen (Felder alt → neu, hinzugefügte/entfernte Zutaten, Schritte, Tags) gespeichert.

- Pro Rezept: „Verlauf ansehen“ auf der Rezeptseite (für alle sichtbar).
- Gesamt: „Änderungsprotokoll“ im Benutzermenü (nur Familienoberhaupt), inkl. gelöschter Rezepte.
- Einträge bleiben lesbar, auch wenn Rezept oder Account später gelöscht werden.
- Seeder und Import erscheinen als „System“.

## Aufbau

| Bereich | Ort |
| --- | --- |
| Models | `app/Models` – `Recipe`, `Ingredient`, `Step`, `Tag`, `User`, `RecipeRevision` |
| Enums | `app/Enums` – `Difficulty`, `Role`, `RevisionAction` |
| Speichern/Löschen inkl. Protokoll | `app/Actions/SaveRecipe.php`, `app/Actions/DeleteRecipe.php` |
| Validierung | `app/Http/Requests` |
| Berechtigungen | `app/Policies` (`RecipePolicy`, `UserPolicy`), Gates `view-family-favorites` und `view-revision-log` in `AppServiceProvider` |
| Controller | `app/Http/Controllers` – Rezepte, Verlauf, Favoriten, Familie, Änderungsprotokoll, Benutzer, Konto, Login |
| Views | `resources/views`, Layouts unter `resources/views/components/layouts` |
| Artisan-Befehle | `users:create`, `recipes:import` (`app/Console/Commands`) |
| Beispielrezepte | `database/seeders/RecipeSeeder.php` |
| Ursprüngliche Rezeptdaten | `database/data/recipes.json` |

## Tests

```bash
php artisan test
```
