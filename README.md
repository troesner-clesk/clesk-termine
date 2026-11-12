# Termine & Tickets

WordPress-Plugin für Auftrittstermine von Künstlerinnen, Künstlern und Veranstaltungsreihen. Termine werden im Backend gepflegt und mit dem Block „Termine“ (oder einem Shortcode) auf einer Seite ausgegeben, wahlweise in drei Designs und mit Filtern für Stadt, Location und Monat. Jeder Termin hat zusätzlich eine eigene Seite zum Teilen.

## Funktionen

- Eigener Menüpunkt „Termine“ mit den Feldern Programm (Titel), Datum, Uhrzeit, Stadt, Location, Ticket-Link, Preis und Status. Optional und eingeklappt: Hinweis, Anschrift der Location (Straße, PLZ) und Link „Mehr Infos“; die Zeile des eingeklappten Abschnitts nennt, was davon ausgefüllt ist. Dazu optional eine Beschreibung und ein Bild.
- Schnelleinstellungen in der Terminliste: Die Liste zeigt das Bild jedes Termins. Ein Klick darauf öffnet die Mediathek zum Wählen oder Tauschen, das kleine Kreuz entfernt das Bild. Status, Ticket-Link und Preis lassen sich ebenfalls direkt in der Zeile ändern. Jede Änderung wird sofort gespeichert.
- „Duplizieren“ in der Terminliste und im Bearbeiten-Fenster: legt eine Kopie als Entwurf an und markiert die Felder, die sich üblicherweise ändern. Status und Hinweis werden nicht übernommen.
- Vorschläge beim Tippen für Programm, Stadt und Location aus bisherigen Terminen; eine bekannte Location ergänzt eine noch leere Stadt und die Anschrift. Eine eingetragene Stadt und selbst eingetippte Anschriften bleiben stehen; nur die in eine Kopie übernommene Anschrift wird ersetzt, wenn sich die Location ändert.
- Status mit Badge: Wenige Tickets, Ausverkauft, Zusatztermin, Verlegt, Abgesagt, Eintritt frei, Geschlossene Veranstaltung. Bei „Ausverkauft“, „Abgesagt“ und „Geschlossene Veranstaltung“ entfällt der Ticket-Button.
- Preis (optional): Der günstigste Preis erscheint als „ab 18 €“ vor dem Ticket-Button (im Design „Tickets“ beim Status), auch ohne Vorverkauf; 0 erscheint als „Eintritt frei“. Bei vergangenen, ausverkauften, abgesagten und geschlossenen Terminen entfällt er, beim Status „Eintritt frei“ steht nur das Badge. Eingaben wie „18,50“, „ab 18 €“, „18,-“ oder „frei“ werden verstanden. Google bekommt den Preis über die strukturierten Daten und kann ihn in der Terminanzeige zeigen.
- Block „Termine“ mit drei Designs: Karten, Liste, Tickets. Das Design wird im Editor am Block gewählt (Seitenleiste „Block“, Bereich „Darstellung“). Im Karten-Design steht bei Terminen ohne Bild ein Ticketsymbol in der Bildfläche.
- Filter für Stadt, Location und Monat ohne Neuladen. Ein Filter erscheint nur, wenn es mindestens zwei Werte gibt; nicht kombinierbare Werte werden ausgegraut. Ohne JavaScript bleiben alle Termine sichtbar.
- Vergangene Termine verschwinden am Tag nach dem Termin automatisch aus den Listen (Zeitzone der Website). Eine Liste kann alternativ vergangene oder alle Termine zeigen, etwa als Archiv.
- Eigene Seite je Termin unter `/termine/programm-stadt-datum/` mit Eckdaten, Bild, Beschreibung, Ticket-Button, Karte, Teilen und weiteren Terminen desselben Programms.
- Teilen: auf Geräten mit Teilen-Dialog öffnet sich dieser, sonst stehen WhatsApp, Facebook, E-Mail und „Link kopieren“ bereit. Alles reine Links, es werden keine fremden Skripte geladen.
- Für Suchmaschinen und Vorschauen in Messengern: Seitentitel, Beschreibung, Open-Graph-Daten und strukturierte Daten (schema.org/Event mit Angebot aus Ticket-Link und Preis; der Status „Eintritt frei“ zählt dort wie Preis 0). Vergangene, geschlossene und passwortgeschützte Termine sind von der Indexierung ausgenommen und stehen nicht in der Sitemap von WordPress.
- Kartenlink zu Google Maps (reiner Link, nichts wird eingebettet).
- Keine Abhängigkeit von der REST-API im Frontend.

## Installation

1. ZIP bauen: `bash scripts/build-zip.sh` erzeugt `dist/clesk-termine.zip`.
2. In WordPress unter Plugins → Plugin hinzufügen → Plugin hochladen die ZIP-Datei hochladen und aktivieren.
3. Unter „Termine“ die Veranstaltungen anlegen.
4. Auf der gewünschten Seite den Block „Termine“ einfügen und in der Seitenleiste Design, Filter und Zeitraum wählen.

Für ein Update dieselbe Datei erneut hochladen; WordPress fragt dann, ob die installierte Version durch die hochgeladene ersetzt werden soll. Die Termine bleiben erhalten, auch beim Deaktivieren oder Löschen des Plugins. Die Adressen der Terminseiten richtet das Plugin beim ersten Seitenaufruf nach Installation oder Update selbst ein.

Voraussetzungen: WordPress 6.3 oder neuer, PHP 7.4 oder neuer. Der HTML-Anker des Blocks steht ab WordPress 7.0 zur Verfügung.

## Terminseiten

- Die Adresse entsteht beim Veröffentlichen automatisch aus Programm, Stadt und Datum, zum Beispiel `/termine/poetry-slam-koeln-19-11-2026/`. Entwürfe haben noch keine endgültige Adresse. Ändert sich bei einem veröffentlichten Termin eine dieser Angaben, ändert sich die Adresse mit, und die alte leitet auf die neue um.
- Wer die Adresse im Bearbeiten-Fenster von Hand ändert, behält sie. Feld leeren stellt die Automatik wieder her. Eine von Hand gesetzte Adresse, die genau dem Titel entspricht, gilt als automatisch und wird ersetzt.
- „Alle Termine“ auf der Terminseite führt zur veröffentlichten Seite mit dem Block „Termine“ und springt dort zur Liste. Gibt es mehrere, gewinnt die mit der vollständigen Liste.
- Termine haben kein Archiv und keinen Feed: Sammeladressen wie `/?post_type=clesk_termin` leiten zur Übersicht um.
- Die Basis `termine` belegt alle Adressen unterhalb von `/termine/`. Eine vorhandene Seite `/termine/` bleibt erreichbar, ihre Unterseiten nicht. Wer das braucht, wählt über `clesk_termine_slug` eine andere Basis.
- Die Seite nutzt die Einzelansicht des Themes (Kopf, Fuß, Inhaltsbreite) und ersetzt nur den Inhalt. Bei GeneratePress gibt das Plugin die Überschrift selbst aus und blendet Seitenleiste und Beitragsbild des Themes aus; andere Themes zeigen ihren eigenen Titel. Ist die Überschrift in GeneratePress für die Seite abgeschaltet (Inhaltstitel im Layout-Kasten des Termins oder die Option „Disable title“ eines Elements aus GP Premium), lässt auch das Plugin sie weg.
- Suchmaschinen: Vergangene, geschlossene, passwortgeschützte und undatierte Termine tragen „noindex“ und stehen nicht in der Sitemap. Kommende Termine stehen drin, damit Google neue Terminseiten schnell findet.
- Ist ein SEO-Plugin aktiv (Yoast SEO, Rank Math, SEOPress, All in One SEO, The SEO Framework, Slim SEO), überlässt das Plugin ihm Seitentitel, Beschreibung und Open-Graph-Daten. Die strukturierten Daten gibt es weiterhin aus.
- The SEO Framework ist eingebunden: Titel („Programm in Stadt am Datum“), Beschreibung und Vorschau-Daten jeder Terminseite kommen aus den Termindaten, solange im SEO-Kasten des Termins nichts von Hand eingetragen ist. „noindex“ für vergangene und geschlossene Termine setzt das Plugin dort über die Robots-Angabe, damit fallen diese Termine auch aus der Sitemap von The SEO Framework (`/sitemap.xml`). Ein täglicher Lauf kurz nach Mitternacht leert deren Zwischenspeicher, damit Termine vom Vortag am nächsten Tag herausfallen. In der Terminliste ist die SEO-Leiste von The SEO Framework zunächst ausgeblendet („Ansicht anpassen“ blendet sie ein).
- Bei anderen SEO-Plugins gelten deren Einstellungen für Indexierung und Sitemap: „noindex“ und die Auswahl der Sitemap wirken dort nur auf die Ausgabe von WordPress selbst.
- GeneratePress mit GP Premium: Kommen Kopfbereich oder Überschrift der Unterseiten aus Elementen (Design → Elemente, zum Beispiel ein über den Inhalt gelegter Header oder ein Seitenkopf mit dem Titel), entscheiden deren Anzeigeregeln, ob sie auch für den Beitragstyp „Termine“ gelten. Soll die Terminseite denselben Seitenkopf bekommen wie die übrigen Unterseiten, den Beitragstyp „Termine“ in die Anzeigeregeln dieses Elements aufnehmen. Ist dort „Disable title“ gesetzt, zeigt nur noch der Seitenkopf die Überschrift, der Terminkopf beginnt dann mit dem Datum.
- Liegt der Header über dem Inhalt („Merge“) und folgt ihm auf der Terminseite kein Seitenkopf, holt das Plugin ihn zurück in den Seitenfluss. Wäre das Menü auf dem Seitenhintergrund nicht lesbar (helle Schrift auf hellem Grund), bekommt der Header eine Fläche in der Textfarbe der Seite. Andere Farbe: `.ctt-single-page .header-wrap.ctt-headband { background-color: … }`.

## Block-Einstellungen

| Bereich | Einstellung | Standard |
|---|---|---|
| Darstellung | Design: Karten, Liste, Tickets | Karten |
| Darstellung | Nach Monaten gruppieren | an |
| Darstellung | Bilder anzeigen (nur Karten) | an |
| Darstellung | Kartenlink anzeigen | an |
| Darstellung | Terminseiten verlinken | an |
| Darstellung | Teilen-Symbol anzeigen | an |
| Darstellung | Beschriftung Ticket-Button | Tickets |
| Filter | Stadt, Location, Monat | alle an |
| Auswahl | Zeitraum: kommende, vergangene, alle | kommende |
| Auswahl | Programm (nur Termine mit genau diesem Titel) | alle |
| Auswahl | Höchstens anzeigen (0 = alle, intern höchstens 500). Bei „alle“ zählen zuerst die nächsten Termine, dann die jüngsten vergangenen | 0 |
| Auswahl | Text, wenn keine Termine anstehen | Aktuell sind keine Termine geplant. |

## Shortcode

Für Stellen ohne Block-Editor:

```text
[termine design="liste" filter="stadt,monat" zeitraum="kommende" programm="Poetry Slam" anzahl="5"]
```

| Attribut | Werte | Standard |
|---|---|---|
| `design` | `karten`, `liste`, `ticket` | `karten` |
| `filter` | Liste aus `stadt`, `location`, `monat` oder leer | `stadt,location,monat` |
| `monate` | Nach Monaten gruppieren: `ja`, `nein` | `ja` |
| `bilder` | `ja`, `nein` | `ja` |
| `karte` | `ja`, `nein` | `ja` |
| `details` | Terminseiten verlinken: `ja`, `nein` | `ja` |
| `teilen` | Teilen-Symbol: `ja`, `nein` | `ja` |
| `zeitraum` | `kommende`, `vergangene`, `alle` | `kommende` |
| `programm` | exakter Titel | leer |
| `anzahl` | Zahl, 0 = alle | `0` |
| `ticket_text` | Beschriftung des Ticket-Buttons | `Tickets` |
| `leer_text` | Text ohne Termine | `Aktuell sind keine Termine geplant.` |

`[clesk_termine]` funktioniert gleich und ist für den Fall gedacht, dass ein anderes Plugin `[termine]` schon belegt.

## Anpassen

Farben: Das CSS nutzt die globalen Farben von GeneratePress (`--accent`, `--accent-3`, `--contrast`, `--base-2`, `--base-3`) und fällt sonst auf eigene Werte zurück. Eigene Werte lassen sich über die Variablen `--ctt-accent`, `--ctt-highlight`, `--ctt-text` usw. auf `.ctt` setzen.

Filter für Entwickler (vor `init` registrieren, zum Beispiel in der `functions.php` des Child-Themes):

| Filter | Wirkung | Standard |
|---|---|---|
| `clesk_termine_single_pages` | Eigene Seite je Termin an oder aus | `true` |
| `clesk_termine_slug` | Erster Teil der Adresse der Terminseiten | `termine` |
| `clesk_termine_overview_url` | Ziel von „Alle Termine“ auf der Terminseite | automatisch erkannt |
| `clesk_termine_performer` | Künstlername in den strukturierten Daten | Website-Titel bis zum ersten Trenner |
| `clesk_termine_output_meta` | Seitentitel, Beschreibung und Open Graph der Terminseiten ausgeben | `true` ohne SEO-Plugin |
| `clesk_termine_single_print_title` | Überschrift der Terminseite selbst ausgeben | `true` bei GeneratePress und vorhandenem Titel, solange das Theme die Überschrift dort nicht abgeschaltet hat |

## Datenmodell

Beitragstyp `clesk_termin`. Titel ist das Programm, Inhalt die Beschreibung, Beitragsbild das Bild. Felder als Post-Meta:

| Schlüssel | Inhalt |
|---|---|
| `_ctt_date` | Datum `Y-m-d` |
| `_ctt_time` | Beginn `H:i` oder leer |
| `_ctt_city` | Stadt |
| `_ctt_venue` | Location |
| `_ctt_street` | Straße und Hausnummer |
| `_ctt_zip` | PLZ |
| `_ctt_ticket_url` | Ticket-Link |
| `_ctt_price` | Preis ab in Euro als Dezimalzahl mit Punkt (`18`, `18.5`), `0` = Eintritt frei, leer = keine Angabe |
| `_ctt_status` | `few`, `soldout`, `extra`, `moved`, `cancelled`, `free`, `private` oder leer |
| `_ctt_note` | Hinweis |
| `_ctt_info_url` | Link „Mehr Infos“ |
| `_ctt_start` | abgeleiteter Sortierschlüssel `Y-m-d H:i` |
| `_ctt_auto_slug` | zuletzt automatisch vergebene Adresse der Terminseite |

## Entwicklung

Kein Build-Schritt: Das Editor-Skript nutzt die globalen `wp.*`-Pakete, die Abhängigkeiten stehen in `blocks/termine/index.asset.php`.

Lokaler Test mit WordPress Playground (Node.js nötig, Port frei wählbar):

```bash
npx @wp-playground/cli@latest server --port=9410 --login \
  --mount="$PWD/clesk-termine:/wordpress/wp-content/plugins/clesk-termine"
```

Danach im Playground das Plugin aktivieren, Termine anlegen und den Block auf einer Seite einfügen.

## Lizenz

GPL-2.0-or-later, siehe `LICENSE`. Die Symbole stammen aus Lucide (ISC-Lizenz), siehe `clesk-termine/ICONS-LICENSE.txt`.
