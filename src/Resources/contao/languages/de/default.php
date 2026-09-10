<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

/*
 * Texte der Vorschauseite (key=vorschau) des Backend-Moduls „Metadaten“.
 *
 * Die Feldbezeichnungen des Auftrags stehen in tl_metadaten.php, die
 * Bezeichnungen der einzelnen Metadaten-Felder (Titel, Alternativtext,
 * Link, Bildunterschrift, Lizenz) kommen aus Contaos eigener Sprachdatei
 * (MSC.aw_*), damit sie mit der Dateiverwaltung übereinstimmen.
 */
$GLOBALS['TL_LANG']['METADATEN']['headline'] = 'Vorschau: %s';
$GLOBALS['TL_LANG']['METADATEN']['bearbeiten'] = 'Auftrag bearbeiten';

$GLOBALS['TL_LANG']['METADATEN']['legendAuftrag'] = 'Einstellungen des Auftrags';
$GLOBALS['TL_LANG']['METADATEN']['legendVorschau'] = 'Vorschau der Änderungen';
$GLOBALS['TL_LANG']['METADATEN']['legendErgebnis'] = 'Zuletzt ausgeführt';

$GLOBALS['TL_LANG']['METADATEN']['ausfuehren'] = '%d Dateien jetzt ändern';
$GLOBALS['TL_LANG']['METADATEN']['keineTreffer'] = 'In den %d ausgewählten Dateien ändert sich nichts.';
$GLOBALS['TL_LANG']['METADATEN']['treffer'] = '%d von %d ausgewählten Dateien werden geändert. Bitte prüfen Sie die Liste, bevor Sie die Änderung ausführen.';
$GLOBALS['TL_LANG']['METADATEN']['gekuerzt'] = 'Angezeigt werden nur die ersten %d Dateien; ausgeführt wird die Änderung für alle.';
$GLOBALS['TL_LANG']['METADATEN']['erledigt'] = 'Die Metadaten von %d Dateien wurden geändert. Jede Datei hat eine neue Version bekommen und lässt sich in der Dateiverwaltung zurücksetzen.';
$GLOBALS['TL_LANG']['METADATEN']['ergebnisListe'] = 'Geänderte Dateien (%d):';

$GLOBALS['TL_LANG']['METADATEN']['spalteDatei'] = 'Datei';
$GLOBALS['TL_LANG']['METADATEN']['spalteSprache'] = 'Sprache';
$GLOBALS['TL_LANG']['METADATEN']['spalteFeld'] = 'Feld';
$GLOBALS['TL_LANG']['METADATEN']['spalteAlt'] = 'bisher';
$GLOBALS['TL_LANG']['METADATEN']['spalteNeu'] = 'neu';

$GLOBALS['TL_LANG']['METADATEN']['fehler']['auftragUnbekannt'] = 'Der Auftrag wurde nicht gefunden.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['unbekannterModus'] = 'Die Betriebsart ist unbekannt.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineFelder'] = 'Bitte wählen Sie im Auftrag mindestens ein Feld aus.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['unbekanntesFeld'] = 'Eines der gewählten Felder ist unbekannt.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineSuche'] = 'Bitte geben Sie im Auftrag einen Suchtext ein.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineSprache'] = 'Zum Setzen von Werten muss im Auftrag eine Sprache gewählt werden.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['ungueltigesMuster'] = 'Der reguläre Ausdruck ist ungültig.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['ordnerUnbekannt'] = 'Der im Auftrag gewählte Ordner ist nicht (mehr) in der Dateiverwaltung vorhanden.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['ordnerGesperrt'] = 'Der im Auftrag gewählte Ordner liegt außerhalb Ihrer Dateifreigaben.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineFreigabe'] = 'Ihr Benutzerkonto hat keine Dateifreigaben; es gibt nichts zu bearbeiten.';
