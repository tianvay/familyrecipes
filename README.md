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
php artisan migrate --seed       # Tabellen, Standard-Admin und alle Familienrezepte (Stand 09.11.2026)
php artisan recipes:import       # übrige Rezepte aus database/data/recipes.json
php artisan storage:link         # für die Vorschau importierter Fotos
npm run build
```

Der fertige Build (`public/build`) liegt mit im Git. Eine weitere Installation (z. B. auf 192.168.178.38)
lässt sich daher ohne Node per `git pull` aktualisieren. Nach Änderungen an Views, CSS oder JS also
`npm run build` ausführen und `public/build` mitcommitten. `php artisan migrate` nur auf **einer**
Installation ausführen, weil beide dieselbe Datenbank nutzen.

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

Die Fotos liegen **in der Datenbank** (Tabelle `recipe_images`, `MEDIUMBLOB`), damit alle Installationen
dieselben Fotos sehen. Ausgeliefert werden sie über `/rezepte/{rezept}/bild?v=…`, und der Browser
speichert sie dauerhaft zwischen. Voraussetzung: In der MariaDB muss `max_allowed_packet` mindestens
`32M` betragen (Standard ist 1 MB).

- Ältere Fotos, die noch als Datei unter `storage/app/public/recipes` liegen, übernimmt
  `php artisan recipes:move-images` in die Datenbank. Der Befehl muss auf **jeder** Installation einmal
  laufen, weil jede nur ihre eigenen Dateien findet. Bis dahin werden diese Fotos weiter als Datei angezeigt.
- Die Dateien bleiben als Sicherung liegen und können danach gelöscht werden.

## Rezept per Link oder geteiltem Text importieren

„🔗 Aus Link importieren“ (Rezeptübersicht oder „Neues Rezept“, für Erwachsene): Link z. B. von Chefkoch
einfügen → Titel, Beschreibung, Portionen, Zeiten, Kalorien, Zutaten (in Menge/Einheit/Name zerlegt),
Schritte, Tags und Foto landen im Rezeptformular und werden erst beim Speichern übernommen.

- Grundlage sind die strukturierten Rezeptdaten der Seite (schema.org/Recipe, JSON-LD); Seiten ohne
  diese Daten werden mit einem Hinweis abgelehnt.
- Werbetexte in Beschreibungen und von Chefkoch angehängte Autorennamen werden entfernt.
- Aus Sicherheitsgründen werden nur öffentliche Adressen abgerufen (auch nach Weiterleitungen),
  keine Geräte im Heimnetz. Nicht gespeicherte Import-Fotos werden nach einem Tag gelöscht.
- Code: `app/Actions/ImportRecipeFromUrl.php`, `app/Support/RecipeImport/`.

## Rezepte teilen

„↗ Teilen“ auf jeder Rezeptseite (alle, auch Kinder) zeigt das Rezept als Text in einem festen,
lesbaren Format – nur das Rezept, ohne Gruß oder Code. Damit passt es in WhatsApp, eine Mail oder
unter ein Kochvideo. Verschicken per Teilen-Dialog (nur HTTPS), „Kopieren“, WhatsApp oder E-Mail;
klappt Kopieren nicht, ist der Text im Feld markiert zum Selbstkopieren.

```
🧀 Omas Käsespätzle
Mit Röstzwiebeln – wie früher.
4 Portionen · 20 Min. Vorbereitung · 15 Min. Kochen/Backen · Einfach · 650 kcal · 28 g Protein
Tags: Vegetarisch, Klassiker
Quelle: Oma Hilde

Zutaten:
• 500 g Spätzle
• 1 Bund Schnittlauch (optional)

Zubereitung:
1. Spätzle kochen.
2. (gleichzeitig) Zwiebeln rösten.

Tipps:
• Mit Bergkäse wird es würziger
```

- **Einlesen:** Text unter „Rezept importieren“ ins Feld „Link oder geteiltes Rezept“ einfügen. Erkannt
  wird ein Text mit den Zeilen „Zutaten:“ und „Zubereitung:“; Kopf = letzter Absatz davor (Emoji +
  Titel, Beschreibung, Eckdaten, Tags, Quelle – alles optional außer dem Titel). Text davor/danach,
  `-`/`*` statt `•` und Schritte ohne Nummer gehen auch – so lassen sich auch von Hand getippte Rezepte
  übernehmen. Das Formular wird vorausgefüllt und erst beim Speichern übernommen; gibt es den Titel
  schon, erscheint ein Hinweis.
- Zutatenzeilen zerlegt der `IngredientLineParser` vom Link-Import. Unbekannte Einheiten (z. B. „groß“)
  landen dabei im Namen – liest sich gleich, nur die Felder sind anders aufgeteilt.
- **Nicht** enthalten: Foto, Farbe, Notizen (familienintern), Favoriten, Verlauf.
- Code: `app/Support/RecipeShareText.php`, Frontend `resources/js/recipe-share.js`.

## Kochmodus

„👩‍🍳 Kochmodus“ auf der Rezeptseite: Vollbild, ein Schritt pro Bildschirm in großer Schrift, blättern per
Knopf, Wischen oder Pfeiltasten. Unter jedem Schritt stehen die Zutaten, die darin vorkommen; bei
Zeitangaben („12–15 Minuten“) gibt es einen Timer mit Signalton. Die Zutatenliste lässt sich jederzeit
einblenden und abhaken. Code: `app/Support/CookingSteps.php`, `resources/js/cooking-mode.js`.

**Gleichzeitige Schritte:** Im Rezeptformular hat jeder Schritt ab dem zweiten das Häkchen
„⇉ gleichzeitig mit Schritt …“ (Spalte `steps.parallel`). Aufeinanderfolgende Schritte mit Häkchen bilden
eine Gruppe (`CookingSteps::groups()`). Die Rezeptseite rahmt sie als „Gleichzeitig“ ein; der Kochmodus zeigt
sie auf einer Seite – iPhone untereinander, iPad/Desktop nebeneinander. Jeder Schritt hat seinen eigenen
Timer, mehrere laufen parallel und bleiben beim Blättern in der Kopfzeile sichtbar (antippen springt zum
Schritt bzw. beendet einen abgelaufenen Timer).

## Design

Unter „Mein Konto“ wählt jedes Familienmitglied sein eigenes Farbdesign (wird sofort gespeichert):

- **Papier (hell)** – Standard, warme Papier- und Terrakottatöne
- **Catppuccin Mocha (dunkel)** – dunkle Pastellpalette ([catppuccin.com](https://catppuccin.com/palette))
- **Automatisch** – hell, wenn das Gerät im hellen Modus ist, sonst Mocha

Das Design hängt am Account (`users.theme`) und zusätzlich als Cookie am Gerät, damit auch die
Anmeldeseite passt. Technik: `<html data-theme="…">` überschreibt die Farb-Variablen aus `@theme`
in `resources/css/app.css`; Views verwenden nur diese Farben (`bg-surface` statt `bg-white`,
`text-on-accent` statt `text-white`). Code: `app/Enums/Theme.php`.

## Benutzer & Rollen

Anmeldung mit **Benutzername** und Passwort, keine Selbstregistrierung.

| Rolle | Rezepte ansehen, Favoriten | Essen wünschen | Rezepte anlegen/ändern, Essensplan festlegen, Familie | Rezepte löschen, Accounts, Änderungsprotokoll |
| --- | :---: | :---: | :---: | :---: |
| Familienoberhaupt (Admin) | ✓ | – | ✓ | ✓ |
| Erwachsen | ✓ | ✓ | ✓ | – |
| Kind | ✓ | ✓ | – | – |

- Der Admin-Account dient nur der Verwaltung: Er wünscht sich nichts (die Wunschliste sieht er nur zum
  Planen) und erscheint nicht in der Übersicht „Wer mag was?“ – auch seine Favoriten zählen dort nicht.
- „Wer mag was?“ (Menü „Familie“, alle Erwachsenen) zeigt die Top 5 **am beliebtesten** (meiste
  Favoriten), die Top 5 **am häufigsten gewünscht** (alle Wünsche, auch eingeplante; gleiche Freitexte
  werden zusammengezählt) und die Favoriten jedes Familienmitglieds.

- Jedes Mitglied kann unter „Mein Konto“ das eigene Passwort und das Design ändern.
- Der Standard-Admin wird per Migration angelegt; weitere Accounts über „Benutzer verwalten“
  oder `php artisan users:create`.

## Änderungsprotokoll

Jedes Anlegen, Bearbeiten und Löschen eines Rezepts wird mit Person, Zeitpunkt und den
konkreten Änderungen (Felder alt → neu, hinzugefügte/entfernte Zutaten, Schritte, Tags) gespeichert.

- Pro Rezept: „Verlauf ansehen“ auf der Rezeptseite (für alle sichtbar).
- Gesamt: „Änderungsprotokoll“ im Benutzermenü (nur Familienoberhaupt), inkl. gelöschter Rezepte.
- Einträge bleiben lesbar, auch wenn Rezept oder Account später gelöscht werden.
- Seeder und Import erscheinen als „System“.

## Wunschliste

Unter „Wünsche“ (Menü, Startseite, „⭐ Wünschen“ auf jeder Rezeptseite) kann sich jedes Familienmitglied
(außer dem Admin-Account) ein Rezept oder eine eigene Idee wünschen – **ab dieser oder ab nächster Woche**, optional mit Wunschtag und Notiz.

- Ein Wunsch bleibt **offen, bis das Gericht im Essensplan steht** (an einem Tag ab der gewünschten Woche),
  egal wie viele Wochen das dauert. „Erfüllt“ wird aus dem Plan berechnet: Wird das Gericht wieder
  ausgeplant, ist der Wunsch wieder offen (`app/Support/WishList.php`).
- Eingeplante Wünsche erscheinen unter „Eingeplant 🎉“, solange der Tag heute oder später ist, und bleiben
  als Historie gespeichert. Ein Wunsch wird nur durch einen Plan-Tag ab dem Tag des Wunsches erfüllt –
  wer sich ein Gericht wünscht, das letzte Woche schon dran war, steht also wieder bei den offenen Wünschen.
- Jede Person hat ein Gericht nur einmal offen auf der Liste; eigene offene Wünsche lassen sich zurückziehen,
  Erwachsene können offene Wünsche entfernen.
- Rezeptauswahl für viele Rezepte: Suchfeld (findet „Käse“ auch mit „kase“) und „♥ Nur meine Favoriten“.

## Essensplan

Unter „Essensplan“ (Woche = Montag bis Sonntag, Adresse z. B. `/essensplan/2026-W43`):

- Jede Woche zeigt die Wünsche, die noch offen oder in dieser Woche eingeplant sind (✓).
- **Festlegen** (Erwachsene): „Plan festlegen“ zeigt alle offenen Wünsche mit Anzahl, Namen und Wunschtagen.
  Pro Tag ein Gericht, jedes Gericht höchstens zweimal pro Woche.
- Planen geht für diese und nächste Woche; ältere Wochen bleiben als Historie lesbar.
- Beim Planen steht an jedem Rezept, wann es es zuletzt gab; oben gibt es die Gruppe „Lange nicht gegessen“
  (seit mindestens drei Wochen oder noch nie). Auch die Rezeptseite zeigt „Zuletzt gegessen“.
- Wünsche und Pläne werden dauerhaft aufbewahrt (Grundlage für spätere Auswertungen) und bleiben
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
| Familienrezepte für frische Installationen | `database/seeders/RecipeSeeder.php`, Daten in `database/seeders/data/` (`recipes.json` + Fotos) |
| Ursprüngliche Rezeptdaten | `database/data/recipes.json` |

## Tests

```bash
php artisan test
```
