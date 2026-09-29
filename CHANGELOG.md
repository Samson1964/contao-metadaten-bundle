# Metadaten Changelog

## Version 1.6.0 (2026-09-29)

Keine Datenbankaktualisierung nötig.

* Add: Unterscheidung „ein oder zwei Spieler“. Die Bildanalyse liefert zwei Vorschläge, den Ausschnitt um den scharfen Spieler und einen breiten Ausschnitt (5 bis 98 Prozent der Breite, 2 bis 90 Prozent der Höhe), und wählt anhand der Schärfeverteilung quer über das Bild einen davon vor
* Add: Im Bildteil-Editor schalten die Knöpfe „1 Spieler“ und „2 Spieler“ jede Karte mit einem Klick um; die Vorwahl der Analyse ist hervorgehoben, das Umschalten setzt das Häkchen „übernehmen“
* Add: `tools/bildteilmessung.php` meldet zusätzlich, wie viele Fotos richtig eingeteilt werden und welche Überdeckung nach dem Umschalten der falsch eingeteilten erreicht wird

Gemessen an den 206 Markierungen der DSSAM-Galerie:

| | Ohne Unterscheidung (1.5.0) | Mit Unterscheidung (1.6.0) |
| --- | --- | --- |
| Falsch eingeteilte Fotos, die im Editor nachgearbeitet werden müssen | 55 | 42 |
| Mittlere Überdeckung ohne Nacharbeit | 0,74 | 0,74 |
| Mittlere Überdeckung, wenn die falsch eingeteilten umgeschaltet werden | 0,74 | 0,79 |

Die automatische Einteilung allein verbessert die Überdeckung kaum: Sitzen zwei Spieler nebeneinander und ist nur einer scharf, erkennt die Schärfekarte den zweiten nicht. Der Gewinn liegt im Umschalten mit einem Klick statt Aufziehen von Hand.

## Version 1.5.0 (2026-09-29)

Keine Datenbankaktualisierung nötig.

* Change: Der Vorschlag für den wichtigen Bildteil folgt jetzt der **Schärfe** des Fotos. Der scharf abgebildete Bereich im oberen Bildteil bestimmt die Breite, mindestens 55 Prozent der Bildbreite; die Höhe reicht von 2 bis 96 Prozent. Gilt für den Bildteil-Editor und die automatische Markierung
* Change: Gesichter aus pico.js verkleinern den Vorschlag nicht mehr auf die Köpfe; ein sicher erkanntes Gesicht außerhalb des Vorschlags erweitert ihn nur noch seitlich
* Change: Die automatische Markierung analysiert Vorschaubilder mit 800 statt 240 Pixeln, wie der Editor
* Add: Messwerkzeug `tools/bildteilmessung.php`, das die Schätzung an Markierungen von Hand misst, samt den 206 Markierungen der DSSAM-Galerie in `tools/messdaten/dssam-2026.csv`

Grundlage ist eine Messung an 206 Turnierfotos der DSSAM 2026, deren wichtigen Teil der Benutzer von Hand markiert hatte. Mittlere Überdeckung mit diesen Markierungen:

| Vorschlag | Überdeckung |
| --- | --- |
| bis 1.4.0: Rechteck um die Köpfe aus pico.js | 0,24 |
| ganzes Bild | 0,61 |
| ab 1.5.0: Schärferegel, geprüft an Fotos, an denen sie nicht eingestellt wurde | 0,74 |

pico.js fand nur in der Hälfte der Fotos überhaupt ein Gesicht; Köpfe, die aufs Brett schauen, übersieht es fast immer.

## Version 1.4.0 (2026-09-28)

Keine Datenbankaktualisierung nötig.

* Add: Gesichtserkennung im Bildteil-Editor — der Browser sucht in jedem Vorschaubild nach Gesichtern und setzt den Vorschlag auf die Köpfe, mit Zugabe für Haare und Kinn; bei mehreren Gesichtern umschließt das Rechteck alle
* Add: Jede Karte meldet das Ergebnis („Ein Gesicht erkannt“, „3 Gesichter erkannt“, „Kein Gesicht erkannt, Vorschlag ist eine Schätzung“)
* Add: pico.js und die Erkennungsdaten `facefinder` von Nenad Markuš (MIT-Lizenz) liegen unverändert unter `src/Resources/public/js/vendor/` bei, samt Herkunft, Prüfsummen und Lizenztext; der Prüfstand vergleicht die Prüfsummen
* Change: Vorschaubilder im Editor sind 800 statt 480 Pixel groß, damit auch kleinere Gesichter erkannt werden
* Change: Ohne erkanntes Gesicht bleibt die bisherige Schätzung aus dem Bildinhalt der Vorschlag; ein von Hand geändertes Rechteck wird von der Erkennung nicht mehr angefasst

Die Erkennung läuft vollständig im Browser. Es wird kein Bild und kein Ergebnis an einen fremden Server übertragen.

## Version 1.3.0 (2026-09-28)

Keine Datenbankaktualisierung nötig.

* Add: Operation „Wichtige Bildteile markieren“ in der Auftragsliste — zeigt die Bilder der Dateiauswahl, die noch keinen wichtigen Teil haben, je zwölf auf einer Seite, mit einem Vorschlag als Rechteck
* Add: Das Rechteck lässt sich im Browser verschieben, an den Ecken in der Größe ändern, neu aufziehen und mit den Pfeiltasten bewegen; „Vorschlag“ stellt den Ausgangswert wieder her
* Add: Gespeichert werden nur Bilder mit Häkchen „übernehmen“; wer ein Rechteck anfasst, setzt das Häkchen automatisch. Jedes gespeicherte Bild bekommt eine Version
* Add: Link „Diese Bilder von Hand markieren“ auf der Vorschauseite des Auftrags
* Change: Der Hilfetext von „Wichtigen Bildteil automatisch markieren“ sagt jetzt deutlich, dass die Schätzung keine Gesichter erkennt, und verweist auf die neue Operation
* Change: Der Bildteil-Editor nimmt nur Bilder an, die zur Dateiauswahl des Auftrags gehören und noch unmarkiert sind — eine manipulierte Eingabe erreicht keine fremden Dateien und überschreibt keine bestehende Markierung

## Version 1.2.0 (2026-09-27)

**Datenbankaktualisierung nötig** (neue Spalte `wichtigerTeil` in `tl_metadaten`).

* Add: Option „Wichtigen Bildteil automatisch markieren“ im Auftrag — bei Bildern ohne wichtigen Teil wird er aus dem Bildinhalt geschätzt (Details, Hauttöne, kräftige Farben) und zusammen mit den Metadaten in einer Version gespeichert; bereits markierte Bilder bleiben unverändert
* Add: Die Vorschau zeigt für die ersten zwölf Bilder das geschätzte Rechteck über dem Vorschaubild
* Add: Betriebsart „Metadaten nicht ändern“ für Aufträge, die nur den wichtigen Bildteil markieren
* Add: Praxisprobe `tools/bildprobe.php`, die den Weg von der Dateiabfrage über Contaos Bildfabrik bis zur gespeicherten Version gegen eine echte Installation mit Datenbank prüft und hinter sich aufräumt
* Change: Sprache und Felder stehen jetzt in den Subpaletten der beiden Betriebsarten, die Metadaten ändern
* Change: Der Knopf auf der Vorschauseite heißt „Auftrag jetzt ausführen“, weil er neben Metadaten auch Bildteile schreiben kann
* Fix: **Unter Contao 5 fand die Dateiabfrage keine einzige Datei**, sobald ein Ordner oder Dateiendungen gewählt waren — `Statement::execute()` entpackt dort ein übergebenes Feld nicht mehr. Die Parameter werden jetzt mit `...` übergeben. Betroffen waren die Versionen 1.0.0 bis 1.1.0; unter Contao 4.13 lief die Abfrage richtig
* Fix: Fehlender Text „(mit Unterordnern)“ in der Zusammenfassung des Auftrags

## Version 1.1.0 (2026-09-10)

**Datenbankaktualisierung nötig** (neue Tabelle `tl_metadaten`).

* Add: Aufträge werden in der neuen Tabelle `tl_metadaten` gespeichert und über Contaos DC_Table bearbeitet — der Ordner wird damit im echten Dateibaum der Dateiverwaltung gewählt, der jeden Zweig erst beim Aufklappen lädt
* Add: Aufträge lassen sich duplizieren, wiederverwenden und beliebig oft mit Vorschau ausführen
* Add: Operation „Vorschau und Ausführen“ in der Auftragsliste; die Vorschauseite zeigt oben die Einstellungen des Auftrags, darunter die Änderungen je Datei
* Change: Die Betriebsart blendet über Subpaletten nur noch die passenden Felder ein
* Fix: Die Ordnerauswahl als Auswahlliste aller Ordner (Chosen) legte bei großen Dateiverwaltungen den Browser lahm; sie ist ersatzlos durch den Dateibaum abgelöst
* Fix: Ein Auftrag, dessen Ordner außerhalb der Dateifreigaben des ausführenden Benutzers liegt, wird mit Meldung abgewiesen

## Version 1.0.1 (2026-09-10)

* Change: Die Ordnerauswahl ist jetzt eine durchsuchbare Auswahl (Chosen in Contao 4.13, Choices in Contao 5) — bei vielen Ordnern lässt sich der gewünschte per Tippen finden, statt die ganze Liste zu durchscrollen
* Fix: Einleitung, Zwischenüberschriften „Suchen und ersetzen“ / „Werte setzen“, Vorschau und Ergebnisliste standen außerhalb eines `widget`-Blocks und hingen am linken Rand

## Version 1.0.0 (2026-09-10)

Erste lauffähige Fassung. Die Versionen 0.0.x enthielten nur ein aus dem
Schiedsrichterverteiler-Bundle kopiertes Gerüst ohne eigene Funktion.

* Add: Backend-Modul „Metadaten“ in der Gruppe System hinter der Dateiverwaltung
* Add: Suchen und Ersetzen in Titel, Alternativtext, Link, Bildunterschrift und Lizenz — global, je Ordner oder je Ordner samt Unterordnern, wahlweise auf eine Sprache beschränkt, ohne Beachtung der Groß-/Kleinschreibung oder als regulärer Ausdruck mit Rückverweisen
* Add: Werte setzen für viele Dateien auf einmal (etwa eine Bildunterschrift für einen ganzen Ordner), wahlweise nur in leeren Feldern oder überschreibend
* Add: Eingrenzung auf Dateiendungen
* Add: Vorschau mit bisherigem und neuem Wert je Datei, Sprache und Feld; der Ausführen-Knopf erscheint erst nach einer Vorschau
* Add: Jede geänderte Datei bekommt eine Version in `tl_version` und lässt sich in der Dateiverwaltung zurücksetzen
* Add: Benutzer mit Dateifreigaben sehen und bearbeiten nur Dateien innerhalb ihrer Freigaben
* Add: Unit-Tests der datenbankfreien Kernlogik (`tests/`) und Prüfstand gegen echte Contao-Installationen (`tools/pruefstand.php`)
* Change: Kompatibilität mit Contao 4.13 und 5.7 unter PHP 8.4 (geprüft gegen 4.13.58 und 5.7.7 mit PHP 8.4.24); `composer.json` verlangt `php: ^7.4 || ^8.0` und `contao/core-bundle: ^4.13 || ^5.7`
* Fix: `_instanceof`-Block für `ContainerAwareInterface` aus der `services.yml` entfernt — die Schnittstelle gibt es seit Symfony 7 nicht mehr, der Block hätte den Behälterbau unter Contao 5 verhindert
* Fix: `REQUEST_TOKEN`, `ampersand()`, `specialchars()` und `$this->import('BackendUser')` aus dem alten Gerüst ersetzt; nichts davon existiert unter Contao 5
* Fix: Alle PHP-Dateien mit `declare(strict_types=1)`, ohne CRLF und ohne BOM

## Version 0.0.2 (2026-07-30)

* Change: Beschreibung, Keywords und Homepage in der composer.json ergänzt, damit Packagist das Paket verständlich darstellt und über die Suche auffindbar macht

## Version 0.0.1 (2021-08-12)

* Initiale Version für Contao 4
