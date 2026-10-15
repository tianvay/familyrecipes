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

Danach unter http://localhost:8000 erreichbar.

### Betrieb über XAMPP

Apache liefert das Rezeptbuch über einen eigenen VirtualHost auf **Port 80** aus
(`C:\xampp\apache\conf\extra\httpd-vhosts.conf`, dazu `Listen 80` in `httpd.conf`).
Damit ist es im Heimnetz unter `http://192.168.178.64` bzw. `http://fredrgb.fritz.box` erreichbar.

Damit andere Geräte (z. B. iPad) zugreifen dürfen, braucht Windows eine Firewall-Freigabe für Port 80
im privaten Netzwerk (PowerShell als Administrator):

```powershell
New-NetFirewallRule -DisplayName "Rezeptbuch (Apache, Heimnetz)" -Direction Inbound -Action Allow `
  -Program "C:\xampp\apache\bin\httpd.exe" -Protocol TCP -LocalPort 80 -Profile Private -RemoteAddress LocalSubnet
```

In der FRITZ!Box sollte dem Rechner immer dieselbe IP-Adresse zugewiesen werden.
Die Tests laufen unabhängig davon gegen eine SQLite-In-Memory-Datenbank (`phpunit.xml`).

### Auf iPhone/iPad als App

In Safari `http://192.168.178.64` öffnen → Teilen-Symbol → **„Zum Home-Bildschirm“**. Das Rezeptbuch
erscheint dann mit eigenem Icon und startet im Vollbild ohne Safari-Leisten
(`public/manifest.json`, Icons in `public/`, neu erzeugen mit `python -I resources/icons/generate.py`).

## Startseite

Nach dem Anmelden zeigt die Startseite „Heute gibt's …“ (mit Rezept-Link), das Gericht von morgen,
die nächsten Tage, den Stand der nächsten Woche (geplante Tage, Vorschläge) und Schnellzugriffe.

## Fotos

Fotos werden vor dem Hochladen im Browser auf höchstens 1600 px verkleinert und als JPEG gespeichert
(iPhone-Fotos: typischerweise mehrere MB → unter 1 MB). Kann ein Gerät das nicht, nimmt der Server
Originale bis 12 MB an. Am iPhone/iPad kann man direkt mit der Kamera fotografieren.

## Rezept per Link importieren

„🔗 Aus Link importieren“ (Rezeptübersicht oder „Neues Rezept“, für Erwachsene): Link z. B. von Chefkoch
einfügen → Titel, Beschreibung, Portionen, Zeiten, Kalorien, Zutaten (in Menge/Einheit/Name zerlegt),
Schritte, Tags und Foto landen im Rezeptformular und werden erst beim Speichern übernommen.

- Grundlage sind die strukturierten Rezeptdaten der Seite (schema.org/Recipe, JSON-LD); Seiten ohne
  diese Daten werden mit einem Hinweis abgelehnt.
- Werbetexte in Beschreibungen und von Chefkoch angehängte Autorennamen werden entfernt.
- Aus Sicherheitsgründen werden nur öffentliche Adressen abgerufen (auch nach Weiterleitungen),
  keine Geräte im Heimnetz. Nicht gespeicherte Import-Fotos werden nach einem Tag gelöscht.
- Code: `app/Actions/ImportRecipeFromUrl.php`, `app/Support/RecipeImport/`.

## Kochmodus

„👩‍🍳 Kochmodus“ auf der Rezeptseite: Vollbild, ein Schritt pro Bildschirm in großer Schrift, blättern per
Knopf, Wischen oder Pfeiltasten. Unter jedem Schritt stehen die Zutaten, die darin vorkommen; bei
Zeitangaben („12–15 Minuten“) gibt es einen Timer mit Signalton. Die Zutatenliste lässt sich jederzeit
einblenden und abhaken. Code: `app/Support/CookingSteps.php`, `resources/js/cooking-mode.js`.

## Benutzer & Rollen

Anmeldung mit **Benutzername** und Passwort, keine Selbstregistrierung.

| Rolle | Rezepte ansehen, Favoriten, Essen vorschlagen | Rezepte ändern, Essensplan festlegen | Accounts, Familie, Änderungsprotokoll |
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

## Essensplan

Unter „Essensplan“ (Woche = Montag bis Sonntag, Adresse z. B. `/essensplan/2026-W43`):

- **Vorschlagen** (alle): ein Rezept oder eine eigene Idee als Freitext, optional mit Wunschtag und Notiz.
  Auch direkt auf jeder Rezeptseite über „📅 Vorschlagen“. Jede Person kann ein Gericht pro Woche einmal vorschlagen
  und eigene Vorschläge zurückziehen.
- **Festlegen** (Erwachsene): „Plan festlegen“ zeigt alle Vorschläge mit Anzahl, Namen und Wunschtagen.
  Pro Tag ein Gericht, jedes Gericht höchstens zweimal pro Woche.
- Vorschlagen und Planen geht für diese und nächste Woche; ältere Wochen bleiben als Historie lesbar.
- Beim Planen steht an jedem Rezept, wann es es zuletzt gab; oben gibt es die Gruppe „Lange nicht gegessen“
  (seit mindestens drei Wochen oder noch nie). Auch die Rezeptseite zeigt „Zuletzt gegessen“.
- Vorschläge und Pläne werden dauerhaft aufbewahrt (Grundlage für spätere Auswertungen) und bleiben
  auch nach dem Löschen eines Rezepts oder Accounts erhalten.

## Einkaufsliste

Unter „Einkaufsliste“ (nur Erwachsene) entsteht automatisch eine Liste aus dem Essensplan:
alle Zutaten der Gerichte von **heute bis Sonntag** der laufenden Woche; vergangene Tage zählen nicht.

- Gleiche Zutaten mit gleicher Einheit werden addiert (auch Bereiche wie „500–600 g“ und Kommazahlen),
  doppelt geplante Gerichte zählen doppelt. Bei jeder Zeile steht, für welche Gerichte und Tage sie gebraucht wird.
- Zutaten, die in allen Gerichten optional sind, stehen getrennt unter „Optional“.
- Freitext-Gerichte (z. B. „Pizza bestellen“) haben keine Zutaten und erscheinen nur als Hinweis.
- **Haben wir immer da:** Zutaten wie Salz oder Öl mit „immer da“ markieren – sie stehen dann eingeklappt
  unten, zählen nicht mit und werden nicht geteilt („doch einkaufen“ holt sie zurück). Abgleich über den
  ganzen Zutatennamen ohne Groß-/Kleinschreibung.
- Logik: `app/Support/ShoppingList.php`, Mengen: `app/Support/Quantity.php`.

**Teilen** (für den Supermarkt): Der Knopf „Teilen“ öffnet – wo der Browser es erlaubt – das
Teilen-Menü des Geräts (Notizen, WhatsApp, Erinnerungen …). Das geht nur über HTTPS; im Heimnetz
per `http://` erscheint stattdessen ein eigenes Menü: Text kopieren, per WhatsApp, per E-Mail oder als
Textdatei speichern. Abgehakte Zutaten (z. B. schon im Haus) werden beim Teilen weggelassen.
Frontend: `resources/js/shopping-list.js`.

## Aufbau

| Bereich | Ort |
| --- | --- |
| Models | `app/Models` – `Recipe`, `Ingredient`, `Step`, `Tag`, `User`, `RecipeRevision`, `MealSuggestion`, `MealPlanEntry` |
| Enums | `app/Enums` – `Difficulty`, `Role`, `RevisionAction` |
| Kalenderwoche | `app/Support/Week.php` (ISO-Woche, offen = diese und nächste Woche) |
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
