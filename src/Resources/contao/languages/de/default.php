<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

/*
 * Texte des Backend-Moduls „Metadaten“.
 *
 * Die Bezeichnungen der einzelnen Metadaten-Felder (Titel, Alternativtext,
 * Link, Bildunterschrift, Lizenz) kommen nicht von hier, sondern aus Contaos
 * eigener Sprachdatei (MSC.aw_*), damit sie mit der Dateiverwaltung
 * übereinstimmen.
 */
$GLOBALS['TL_LANG']['METADATEN']['headline'] = 'Metadaten gesammelt bearbeiten';
$GLOBALS['TL_LANG']['METADATEN']['einleitung'] = 'Dieses Modul ändert die Metadaten (Titel, Alternativtext, Link, Bildunterschrift, Lizenz) vieler Dateien auf einmal. Wählen Sie zuerst Ordner, Felder und Betriebsart, prüfen Sie dann die Vorschau und führen Sie die Änderung erst danach aus. Jede geänderte Datei bekommt eine neue Version, die sich in der Dateiverwaltung zurücksetzen lässt.';

$GLOBALS['TL_LANG']['METADATEN']['legendAuswahl'] = 'Auswahl der Dateien';
$GLOBALS['TL_LANG']['METADATEN']['legendModus'] = 'Bearbeitung';
$GLOBALS['TL_LANG']['METADATEN']['legendVorschau'] = 'Vorschau der Änderungen';

$GLOBALS['TL_LANG']['METADATEN']['ordner'] = array('Ordner', 'Nur Dateien in diesem Ordner bearbeiten. Ohne Auswahl werden alle Dateien der Dateiverwaltung berücksichtigt.');
$GLOBALS['TL_LANG']['METADATEN']['alleOrdner'] = '– alle Dateien –';
$GLOBALS['TL_LANG']['METADATEN']['unterordner'] = array('Unterordner einschließen', 'Auch die Dateien in allen Unterordnern des gewählten Ordners bearbeiten.');
$GLOBALS['TL_LANG']['METADATEN']['endungen'] = array('Dateiendungen', 'Nur Dateien mit diesen Endungen bearbeiten, durch Komma getrennt (z. B. „jpg, png“). Leer lassen für alle Dateien.');
$GLOBALS['TL_LANG']['METADATEN']['sprache'] = array('Sprache', 'Beim Ersetzen: nur diese Sprache bearbeiten oder alle in den Dateien vorhandenen Sprachen. Beim Setzen von Werten ist eine Sprache Pflicht.');
$GLOBALS['TL_LANG']['METADATEN']['alleSprachen'] = '– alle vorhandenen Sprachen –';
$GLOBALS['TL_LANG']['METADATEN']['felder'] = array('Felder', 'Nur die angehakten Felder werden bearbeitet; alle anderen bleiben unverändert.');

$GLOBALS['TL_LANG']['METADATEN']['modus'] = array('Betriebsart', '„Suchen und ersetzen“ tauscht einen Text in bereits gefüllten Feldern aus. „Werte setzen“ schreibt die unten eingetragenen Werte in die gewählten Felder.');
$GLOBALS['TL_LANG']['METADATEN']['modusErsetzen'] = 'Suchen und ersetzen';
$GLOBALS['TL_LANG']['METADATEN']['modusSetzen'] = 'Werte setzen';

$GLOBALS['TL_LANG']['METADATEN']['suche'] = array('Suchen nach', 'Der zu ersetzende Text. Leere Felder werden beim Ersetzen nie angefasst.');
$GLOBALS['TL_LANG']['METADATEN']['ersatz'] = array('Ersetzen durch', 'Der neue Text. Leer lassen, um den Suchtext zu entfernen. Bei regulären Ausdrücken sind Rückverweise wie $1 erlaubt.');
$GLOBALS['TL_LANG']['METADATEN']['gross'] = array('Groß- und Kleinschreibung beachten', 'Abgeschaltet findet „berlin“ auch „Berlin“ und „BERLIN“.');
$GLOBALS['TL_LANG']['METADATEN']['regex'] = array('Regulärer Ausdruck', 'Den Suchtext als regulären Ausdruck (PCRE) auswerten, ohne Begrenzer. Beispiel: „\s+“ für beliebige Leerräume.');
$GLOBALS['TL_LANG']['METADATEN']['nurLeere'] = array('Nur leere Felder füllen', 'Eingeschaltet bleiben bereits vorhandene Werte stehen. Abgeschaltet werden die gewählten Felder in allen Dateien überschrieben — auch mit einem leeren Wert.');

$GLOBALS['TL_LANG']['METADATEN']['vorschau'] = 'Vorschau anzeigen';
$GLOBALS['TL_LANG']['METADATEN']['ausfuehren'] = '%d Dateien jetzt ändern';
$GLOBALS['TL_LANG']['METADATEN']['keineTreffer'] = 'In den %d ausgewählten Dateien ändert sich nichts.';
$GLOBALS['TL_LANG']['METADATEN']['treffer'] = '%d von %d ausgewählten Dateien werden geändert. Bitte prüfen Sie die Liste, bevor Sie die Änderung ausführen.';
$GLOBALS['TL_LANG']['METADATEN']['gekuerzt'] = 'Angezeigt werden nur die ersten %d Dateien; ausgeführt wird die Änderung für alle.';
$GLOBALS['TL_LANG']['METADATEN']['erledigt'] = 'Die Metadaten von %d Dateien wurden geändert.';
$GLOBALS['TL_LANG']['METADATEN']['ergebnisListe'] = 'Geänderte Dateien (%d):';

$GLOBALS['TL_LANG']['METADATEN']['spalteDatei'] = 'Datei';
$GLOBALS['TL_LANG']['METADATEN']['spalteSprache'] = 'Sprache';
$GLOBALS['TL_LANG']['METADATEN']['spalteFeld'] = 'Feld';
$GLOBALS['TL_LANG']['METADATEN']['spalteAlt'] = 'bisher';
$GLOBALS['TL_LANG']['METADATEN']['spalteNeu'] = 'neu';

$GLOBALS['TL_LANG']['METADATEN']['fehler']['unbekannterModus'] = 'Die Betriebsart ist unbekannt.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineFelder'] = 'Bitte wählen Sie mindestens ein Feld aus.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['unbekanntesFeld'] = 'Eines der gewählten Felder ist unbekannt.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineSuche'] = 'Bitte geben Sie einen Suchtext ein.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineSprache'] = 'Zum Setzen von Werten muss eine Sprache gewählt werden.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['ungueltigesMuster'] = 'Der reguläre Ausdruck ist ungültig.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['ordnerUnbekannt'] = 'Der gewählte Ordner steht nicht zur Verfügung.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['spracheUnbekannt'] = 'Die gewählte Sprache ist nicht aktiviert.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineFreigabe'] = 'Ihr Benutzerkonto hat keine Dateifreigaben; es gibt nichts zu bearbeiten.';
