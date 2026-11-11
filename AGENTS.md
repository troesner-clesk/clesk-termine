# Arbeitsregeln Termine & Tickets

WordPress-Plugin, Einstieg und Datenmodell in [README.md](README.md). Das Repository ist öffentlich: keine Kundendaten, Zugangsdaten oder Werkzeughinweise einchecken. Wo das Plugin im Einsatz ist, steht in der internen Kundenablage, nicht hier.

## Fallen

- Version steht an drei Stellen und muss übereinstimmen: Plugin-Header und `CLESK_TERMINE_VERSION` in `clesk-termine/clesk-termine.php` sowie `version` in `clesk-termine/blocks/termine/block.json`. `scripts/build-zip.sh` bricht bei Abweichung ab.
- Kein Build-Schritt. Neue `wp.*`-Pakete im Editor-Skript von Hand in `blocks/termine/index.asset.php` eintragen.
- Metaschlüssel `_ctt_*` nicht umbenennen, sonst sind bestehende Termine weg. Wer Metadaten direkt schreibt, ruft danach `Clesk_Termine_Events::sync_start()` und `wp_update_post()` auf, sonst fehlt der Termin in Listen und Sortierung und die Adresse der Terminseite passt nicht.
- Das Frontend darf nicht von der REST-API abhängen: Zielseiten sperren sie teilweise für Gäste. Filter laufen clientseitig auf dem serverseitig gerenderten HTML.
- Die Terminseite ersetzt den Inhalt über `the_content` (Priorität 20, nur in der Hauptschleife). Theme-Anpassungen dafür stehen gesammelt in `Clesk_Termine_Single::setup()`; das Beitragsbild des Themes wird nur während der Hauptschleife unterdrückt, damit SEO-Plugins es im Seitenkopf weiter finden. Das Blog-Modul von GP Premium fragt das Bild an diesen Filtern vorbei ab, deshalb zusätzlich `generate_single_featured_image_output`.
- Ob die Terminseite ihre Überschrift selbst ausgibt, entscheidet `Clesk_Termine_Single::print_title()` erst beim Rendern und fragt dafür GeneratePress (`generate_show_title`) ohne den eigenen Filter ab. Nicht in `setup()` vorziehen: Elemente aus GP Premium melden „Titel deaktivieren“ ebenfalls erst auf `wp` an, die Reihenfolge ist nicht garantiert.
- Über den Inhalt gelegter Header aus GP Premium (`.header-wrap`) ohne Seitenkopf: CSS holt ihn auf Terminseiten in den Seitenfluss zurück, `assets/headband.js` setzt bei zu wenig Kontrast die Klasse `ctt-headband`. Das Skript steht bewusst als erstes Kind in `#page`, nicht zwischen Header und `#page`: Beide Regeln hängen daran, dass `#page` direkt auf `.header-wrap` folgt.
- Permalink-Regeln schreibt `Clesk_Termine_Events::maybe_flush_rewrite()` neu, sobald sich Version oder Adressaufbau ändern. Ein Update per ZIP löst keinen Aktivierungs-Hook aus, deshalb nicht auf Aktivierung verlassen.
- Adresse der Terminseite: automatisch aus Programm, Stadt und Datum, solange sie niemand von Hand geändert hat (`_ctt_auto_slug`). Entwürfe bekommen bewusst noch keine Adresse: WordPress prüft Entwurfs-Adressen nicht auf Eindeutigkeit, eine Kopie würde sonst die Seite des Originals für Besucher verdecken. Umleitungen alter Adressen kommen von WordPress (`_wp_old_slug`), also nie direkt in die Datenbank schreiben.
- Styles hängen an `.ctt` und den GeneratePress-Farbvariablen; Theme-Regeln für `button`, `select`, `a`, `p` und Listen werden dort gezielt überschrieben.
- `.ctt` ist ein Query-Container und meldet deshalb keine Eigenbreite. Die Hülle `.ctt-wrap` gleicht das über ein `::before` mit großer Wunschbreite aus; ohne sie fällt die Liste in Flex-Layouts zusammen.
- Die Layouts für mittlere und schmale Breiten stehen doppelt in `style.css`: als `@container`-Regeln und gespiegelt im Block `@supports not (container-type: inline-size)`. Beide Stellen zusammen ändern.
- Sammeladressen der Termine (`/?post_type=clesk_termin`, auch als Feed) leiten bewusst zur Übersicht um, Termine haben kein Archiv.

## Prüfen

- `bash scripts/build-zip.sh` (prüft Versionen und PHP-Syntax, baut `dist/clesk-termine.zip`).
- Lokaler Test mit WordPress Playground, Befehl in der README. Vor dem Hochladen auf eine Live-Seite alle drei Designs, die Filter, das Duplizieren (Original bleibt für Besucher erreichbar, Kopie bekommt beim Veröffentlichen ihre eigene Adresse), die Editor-Vorschau und eine Terminseite prüfen (kommend, vergangen, abgesagt, ohne Bild).
- GeneratePress im Test auf die Flexbox-Struktur stellen (`generate_settings['structure'] = 'flexbox'`), sonst weicht das Layout von aktuellen Installationen ab.
