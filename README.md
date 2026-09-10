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

Anschließend im Contao Manager oder per `contao:migrate` die Installation
abschließen. Es sind keine Datenbankänderungen nötig; das Bundle arbeitet
ausschließlich auf der Spalte `meta` der Tabelle `tl_files`.

## Backend

Das Modul heißt **Metadaten** und steht in der Gruppe **System** direkt hinter
der Dateiverwaltung. Nicht-Administratoren müssen es in ihrer Benutzergruppe
unter „Erlaubte Module“ freigeschaltet bekommen.

### Auswahl der Dateien

| Feld | Bedeutung |
| --- | --- |
| Ordner | Ein Ordner aus der Dateiverwaltung oder „alle Dateien“; die Auswahl ist durchsuchbar, Tippen filtert die Liste |
| Unterordner einschließen | Auch Dateien in allen Unterordnern bearbeiten |
| Dateiendungen | Nur Dateien mit diesen Endungen, z. B. `jpg, png`; leer für alle |
| Sprache | Eine aktivierte Backend-Sprache oder „alle vorhandenen Sprachen“ |
| Felder | Nur die angehakten Felder werden bearbeitet |

Benutzer mit Dateifreigaben sehen nur ihre freigegebenen Ordner; „alle
Dateien“ bedeutet für sie „alle Dateien innerhalb der Freigaben“.

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

### Vorschau und Ausführung

„Vorschau anzeigen“ berechnet die Änderungen und listet je Datei, Sprache
und Feld den bisherigen und den neuen Wert auf, ohne etwas zu speichern.
Erst danach erscheint der Knopf **„… Dateien jetzt ändern“**, der die
angezeigte Änderung ausführt. Nach dem Ausführen leitet das Modul auf sich
selbst um, ein Neuladen der Seite wiederholt die Änderung also nicht.

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

`tools/pruefstand.php` lädt Konfiguration, Sprachdateien, Template und
Klassen des Bundles gegen eine echte Contao-Installation und meldet, ob alle
benutzten Kern-Klassen, -Methoden und -Dienste in dieser Fassung vorhanden
sind. Eine Datenbank braucht er nicht.

```
C:\xampp\php\php.exe tools/pruefstand.php F:\Claude\contao-test-413
C:\xampp\php\php.exe tools/pruefstand.php F:\Claude\contao-test
```

## Entwickler

Die Kernlogik (Lesen, Prüfen, Ersetzen, Setzen, Vergleichen) steckt in
`src/Classes/Bearbeitung.php` und kommt ohne Contao und ohne Datenbank aus.
Das Backend-Modul `src/Modules/Metadaten.php` kümmert sich nur um Formular,
Dateiauswahl, Versionen und Speichern.

```
vendor/bin/phpunit          # Unit-Tests der Kernlogik (tests/)
```

Ohne `composer install` im Bundle genügt ein anderswo installiertes PHPUnit
mit `-c phpunit.xml.dist`; der Bootstrap registriert dann einen eigenen
Autoloader.

## Lizenz

LGPL-3.0-or-later, siehe [LICENSE](LICENSE).
