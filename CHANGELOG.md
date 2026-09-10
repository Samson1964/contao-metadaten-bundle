# Metadaten Changelog

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
