# Metadaten für Contao

Backend-Modul für **Contao 4.13 und 5**, das die Metadaten von Dateien aus der
Dateiverwaltung gesammelt bearbeitet: Titel, Alternativtext, Link,
Bildunterschrift und Lizenz lassen sich in vielen Dateien auf einmal
**suchen und ersetzen** oder mit festen Werten **setzen** — global, für einen
Ordner oder für einen Ordner samt Unterordnern.

Contao selbst bietet in der Dateiverwaltung zwar „Mehrere bearbeiten“, aber
kein Überschreiben: Wer hundert Fotos eines Turniers mit derselben
Bildunterschrift versehen oder einen umbenannten Verein in allen Bildtiteln
austauschen will, klickt sich sonst durch jede Datei einzeln.

## Voraussetzungen

* Contao 4.13 oder Contao 5.7 (geprüft gegen 4.13.58 und 5.7.7)
* PHP 7.4 oder 8.x (geprüft mit PHP 8.4)

## Installation

```
composer require schachbulle/contao-metadaten-bundle
```

Anschließend die **Datenbank aktualisieren** (Contao Manager oder
`contao:migrate`): Das Bundle legt die Tabelle `tl_metadaten` für die
gespeicherten Aufträge an. Die Metadaten selbst bleiben, wo sie sind — in der
Spalte `meta` der Tabelle `tl_files`.

## Backend

Das Modul heißt **Metadaten** und steht in der Gruppe **System** direkt hinter
der Dateiverwaltung. Es verwaltet **Aufträge**: Ein Auftrag beschreibt, welche
Dateien wie bearbeitet werden sollen, und lässt sich beliebig oft mit
Vorschau ausführen — etwa nach jedem neuen Schwung Turnierfotos.

Nicht-Administratoren brauchen in ihrer Benutzergruppe das Modul unter
„Erlaubte Module“ und die Felder von `tl_metadaten` unter „Erlaubte Felder“.

### Auftrag anlegen

| Feld | Bedeutung |
| --- | --- |
| Titel | Name des Auftrags |
| Ordner | Ein Ordner aus dem Dateibaum der Dateiverwaltung; ohne Auswahl gelten alle Dateien |
| Unterordner einschließen | Auch Dateien in allen Unterordnern bearbeiten |
| Dateiendungen | Nur Dateien mit diesen Endungen, z. B. `jpg, png`; leer für alle |
| Betriebsart | „Suchen und ersetzen“, „Werte setzen“ oder „Metadaten nicht ändern“; blendet die passenden Felder ein |
| Sprache | Eine aktivierte Backend-Sprache oder „alle vorhandenen Sprachen“ |
| Felder | Nur die angehakten Felder werden bearbeitet |
| Wichtigen Bildteil automatisch markieren | Schätzt bei Bildern ohne wichtigen Teil diesen aus dem Bildinhalt |

Der Ordner wird über Contaos eigenen Dateibaum gewählt, der jeden Zweig erst
beim Aufklappen lädt — auch bei sehr großen Dateiverwaltungen ohne Wartezeit.

Benutzer mit Dateifreigaben sehen im Baum nur ihre Freigaben. Ein Auftrag,
dessen Ordner außerhalb der eigenen Freigaben liegt, lässt sich nicht
ausführen; „alle Dateien“ bedeutet für sie „alle Dateien innerhalb der
Freigaben“.

### Betriebsarten

**Suchen und ersetzen** tauscht einen Text in bereits gefüllten Feldern aus.
Leere Felder werden dabei nie angefasst. Wahlweise ohne Beachtung der
Groß- und Kleinschreibung (auch für Umlaute) oder als regulärer Ausdruck
(PCRE ohne Begrenzer, Rückverweise wie `$1` im Ersatztext sind erlaubt).
Ein leerer Ersatztext entfernt den Suchtext.

**Werte setzen** schreibt die eingetragenen Werte in die gewählten Felder
einer Sprache. Mit „Nur leere Felder füllen“ (Vorgabe) bleiben vorhandene
Werte stehen; abgeschaltet werden die gewählten Felder in allen ausgewählten
Dateien überschrieben — auch mit einem leeren Wert, was einem Löschen
gleichkommt.

### Wichtige Bildteile von Hand markieren

Contao merkt sich zu jedem Bild einen „wichtigen Teil“, der beim Zuschneiden
im Bild bleibt. In der Dateiverwaltung wird er Bild für Bild als Rechteck
aufgezogen — bei hunderten Fotos eine Fleißarbeit, zumal man die unmarkierten
Bilder erst suchen muss.

Die Operation **„Wichtige Bildteile markieren“** in der Auftragsliste zeigt
genau diese Bilder: alle aus der Dateiauswahl des Auftrags (Ordner,
Unterordner, Dateiendungen), die **noch keinen** wichtigen Teil haben, zwölf
je Seite. Betriebsart und Metadaten des Auftrags spielen dabei keine Rolle.

Jedes Bild trägt ein rotes Rechteck als Vorschlag. So wird es zurechtgerückt:

| Handgriff | Wirkung |
| --- | --- |
| Am Rechteck ziehen | verschieben |
| An einer Ecke ziehen | Größe ändern |
| Daneben ins Bild ziehen | neues Rechteck aufziehen |
| Pfeiltasten | verschieben, mit Umschalttaste Größe ändern |
| Knopf „Vorschlag“ | Ausgangswert wiederherstellen |

Gespeichert werden nur Bilder mit Häkchen bei **„übernehmen“**. Wer ein
Rechteck anfasst, setzt das Häkchen automatisch; „Alle anhaken“ übernimmt die
Vorschläge der ganzen Seite. Bilder ohne Häkchen bleiben unmarkiert und
erscheinen beim nächsten Aufruf wieder. Gespeicherte Bilder fallen aus der
Liste, die übrigen rücken nach.

Der Editor läuft mit Maus, Finger und Tastatur.

#### Gesichtserkennung

Sobald die Seite geladen ist, sucht der Browser in jedem Bild nach
Gesichtern. Findet er welche, rückt der Vorschlag auf die Köpfe, mit etwas
Zugabe für Haare und Kinn; bei mehreren Gesichtern umschließt das Rechteck
alle. Unter jedem Bild steht das Ergebnis. Ohne Fund bleibt die Schätzung
aus dem Bildinhalt stehen.

Die Erkennung leistet [pico.js](https://github.com/nenadmarkus/picojs) von
Nenad Markuš (MIT-Lizenz). Bibliothek und Erkennungsdaten liegen dem Bundle
bei und laufen vollständig im Browser: Es wird kein Bild und kein Ergebnis
an einen fremden Server übertragen.

Was die Erkennung kann und was nicht:

* Sie findet Gesichter, die ungefähr nach vorn schauen und aufrecht stehen.
  Profile, stark geneigte Köpfe und sehr kleine Gesichter entgehen ihr.
* Brillen, Schatten und dunkle Bilder senken die Sicherheit. Damit ein
  Fehlfund das Rechteck nicht vom Kopf wegzieht, zählt neben dem besten Fund
  jeder weitere nur, wenn er für sich genommen sicher ist. Auf Gruppenfotos
  können deshalb einzelne Köpfe fehlen.
* Der Vorschlag bleibt ein Vorschlag. Gespeichert wird erst mit Häkchen.

### Wichtiger Bildteil automatisch

Mit dem Häkchen **„Wichtigen Bildteil automatisch markieren“** im Auftrag
schätzt das Modul den wichtigen Teil beim Ausführen ungeprüft für alle
ausgewählten Bilder, die noch keinen haben. Bereits markierte Bilder werden
nie angefasst. Derselbe Schätzwert ist der Vorschlag im Editor oben — dort
lässt er sich vor dem Speichern berichtigen, hier nicht. Für Personenfotos
ist deshalb der Editor der bessere Weg.

Das Verfahren bewertet jedes Pixel eines Vorschaubildes nach Detailreichtum,
Nähe zu Hauttönen und Farbsättigung und sucht das Fenster, in dem diese
Wertung am dichtesten liegt. Auf Personenfotos trifft es damit in der Regel
das Gesicht; ist der Inhalt gleichmäßig verteilt (Turniersaal, Gruppenfoto),
wird der Teil entsprechend größer. Der Teil umfasst je Achse 30 bis 80
Prozent des Bildes.

Es ist eine Schätzung, **keine Gesichtserkennung**. Die Vorschau zeigt
deshalb für die ersten zwölf Bilder das Rechteck über dem Bild. Was nicht
passt, lässt sich danach in der Dateiverwaltung von Hand nachbessern oder
über die Versionen der Datei zurücksetzen.

Gut zu wissen:

* Ausgewertet werden JPEG, PNG, GIF, WebP, AVIF und BMP, keine SVG. Nötig ist
  die PHP-Erweiterung GD.
* Das Vorschaubild kommt aus Contaos Bildfabrik und landet im Bildcache. Beim
  ersten Mal kostet das je Bild Rechenzeit, bei großen Fotos mit GD mehrere
  Sekunden.
* Die Bildanalyse läuft je Ausführung höchstens 20 Sekunden. Bleiben Bilder
  übrig, meldet das Modul ihre Zahl; ein erneutes Ausführen macht dort weiter.
* Ohne Imagick überspringt das Modul Bilder, die nicht in den PHP-Speicher
  passen würden, statt die Seite abbrechen zu lassen.
* Contao 4.13 verkleinert mit GD keine Bilder über 3000 Pixel Kantenlänge
  (Einstellung `gdMaxImgWidth`/`gdMaxImgHeight`) und liefert das Original
  zurück. Solche Bilder erscheinen im Editor unverkleinert und laden
  entsprechend langsam; markieren lassen sie sich trotzdem.
* Soll ein Auftrag nur Bildteile markieren, ist „Metadaten nicht ändern“ die
  passende Betriebsart.

### Vorschau und Ausführung

Die Operation **„Vorschau und Ausführen“** (Symbol mit den zwei Pfeilen) in
der Auftragsliste zeigt oben die Einstellungen des Auftrags, darunter die
Bilder mit geschätztem wichtigen Teil und je Datei, Sprache und Feld den
bisherigen und den neuen Wert, ohne etwas zu speichern. Erst darunter steht
der Knopf **„Auftrag jetzt ausführen“**. Danach leitet das Modul auf die
Vorschau zurück und listet die geänderten Dateien auf; ein Neuladen der Seite
wiederholt die Änderung nicht.

Jede geänderte Datei bekommt eine **neue Version** in `tl_version`, genau
wie nach einer Bearbeitung von Hand. In der Dateiverwaltung lässt sich der
alte Stand deshalb über die Versionsauswahl der Datei wiederherstellen.

### Was das Modul nicht tut

* Es legt keine Metadaten in Sprachen an, die nicht ausdrücklich gewählt
  wurden, und rührt beim Ersetzen keine leeren Felder an.
* Es ändert keine Dateien auf der Festplatte und synchronisiert nichts —
  die Metadaten leben allein in der Datenbank.
* Es kennt genau die fünf Felder des Contao-MetaWizards. Felder, die andere
  Erweiterungen ergänzen, bleiben unverändert erhalten.

## Prüfstand

`tools/pruefstand.php` lädt Konfiguration, Sprachdateien, DCA, Template und
Klassen des Bundles gegen eine echte Contao-Installation und meldet, ob alle
benutzten Kern-Klassen, -Methoden und -Dienste in dieser Fassung vorhanden
sind. Eine Datenbank braucht er nicht.

```
C:\xampp\php\php.exe tools/pruefstand.php F:\Claude\contao-test-413
C:\xampp\php\php.exe tools/pruefstand.php F:\Claude\contao-test
```

## Praxisprobe

`tools/bildprobe.php` braucht im Gegensatz zum Prüfstand eine laufende
Datenbank. Sie legt die übergebenen Bilder in einem eigenen Ordner unter
`files/` ab, geht den Weg des Moduls von der Dateiabfrage über die Bildfabrik
bis zur gespeicherten Version und räumt danach Ordner, Zeilen und Versionen
wieder weg. Das Bundle muss in der Installation nicht installiert sein.

```
C:\xampp\php\php.exe tools/bildprobe.php F:\Claude\contao-test-413 C:\Pfad\foto.jpg
C:\xampp\php\php.exe tools/bildprobe.php F:\Claude\contao-test C:\Pfad\foto.jpg
```

## Entwickler

Die Kernlogik (Lesen, Prüfen, Ersetzen, Setzen, Vergleichen) steckt in
`src/Classes/Bearbeitung.php`, die Abbildung eines Datensatzes auf einen
Auftrag in `src/Classes/Auftrag.php`, die Schätzung des wichtigen Bildteils
in `src/Classes/Bildanalyse.php`; alle drei kommen ohne Contao und ohne
Datenbank aus. `src/Classes/Bildteil.php` verbindet die Bildanalyse mit
Contaos Bildfabrik und prüft die Eingaben des Editors.

Der Bildteil-Editor besteht aus `be_metadaten_bildteile.html5` und
`src/Resources/public/js/bildteile.js`. Das Skript hängt sich nur an
`document` und findet seine Elemente über `data-`Attribute; deshalb läuft es
unter Contao 4.13 (MooTools) wie unter Contao 5 (Stimulus und Turbo), ohne
eine der beiden Bibliotheken zu benutzen. Der Prüfstand kontrolliert, dass
Skript und Template dieselben Attribute kennen.

`src/Resources/public/js/gesichter.js` verbindet den Editor mit pico.js. Die
Stellschrauben stehen dort als Konstanten: `KANTE` (Auflösung der Analyse),
`GUETE`, `GUETE_WEITERE` und `ANTEIL_WEITERE` (Schwellen) sowie `ZUGABE`
(Rand um das Gesicht). Die Fremddateien unter `js/vendor/` bleiben
unverändert; Herkunft, Prüfsummen und Lizenz stehen in der `LIESMICH.md`
daneben.

## Lizenz der Fremddateien

pico.js und die Erkennungsdaten `facefinder` stehen unter der MIT-Lizenz,
Urheber ist Nenad Markuš. Der Lizenztext liegt unter
`src/Resources/public/js/vendor/LIESMICH.md`. Die DCA `tl_metadaten` liefert das Formular mit Dateibaum, das
Modul `src/Modules/Metadaten.php` kümmert sich um Vorschau, Dateiauswahl,
Versionen und Speichern.

```
vendor/bin/phpunit          # Unit-Tests der Kernlogik (tests/)
```

Ohne `composer install` im Bundle genügt ein anderswo installiertes PHPUnit
mit `-c phpunit.xml.dist`; der Bootstrap registriert dann einen eigenen
Autoloader.

## Lizenz

LGPL-3.0-or-later, siehe [LICENSE](LICENSE).
