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

$GLOBALS['TL_LANG']['METADATEN']['legendBildteil'] = 'Wichtiger Bildteil';
$GLOBALS['TL_LANG']['METADATEN']['bildteilAnzahl'] = '%d der ausgewählten Bilder haben noch keinen wichtigen Teil. Er wird beim Ausführen aus dem Bildinhalt geschätzt; bereits markierte Bilder bleiben unverändert.';
$GLOBALS['TL_LANG']['METADATEN']['bildteilKeine'] = 'Alle ausgewählten Bilder haben bereits einen wichtigen Teil, hier gibt es nichts zu tun.';
$GLOBALS['TL_LANG']['METADATEN']['bildteilHinweis'] = 'Zur Kontrolle das Ergebnis für die ersten %d Bilder. Das Verfahren schätzt, wo im Bild am meisten los ist (Gesichter, Details, kräftige Farben); es erkennt keine Gesichter. Einzelne Bilder lassen sich danach in der Dateiverwaltung von Hand nachbessern.';
$GLOBALS['TL_LANG']['METADATEN']['markiert'] = 'Bei %d Bildern wurde der wichtige Teil markiert.';
$GLOBALS['TL_LANG']['METADATEN']['nichtAuswertbar'] = '%d Bilder ließen sich nicht auswerten (Datei fehlt, ist beschädigt, zu groß für den Speicher oder ohne erkennbare Struktur). Sie bleiben ohne wichtigen Teil.';
$GLOBALS['TL_LANG']['METADATEN']['nochOffen'] = '%d Bilder sind noch offen, weil die Rechenzeit je Durchgang begrenzt ist. Bitte führen Sie den Auftrag erneut aus.';

$GLOBALS['TL_LANG']['METADATEN']['ausfuehren'] = 'Auftrag jetzt ausführen';
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
$GLOBALS['TL_LANG']['METADATEN']['fehler']['nichtsZuTun'] = 'Der Auftrag ändert weder Metadaten noch markiert er den wichtigen Bildteil. Bitte wählen Sie eine Betriebsart oder haken Sie „Wichtigen Bildteil automatisch markieren“ an.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keinGd'] = 'Die PHP-Erweiterung GD fehlt auf diesem Server; der wichtige Bildteil lässt sich nicht automatisch bestimmen.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineFelder'] = 'Bitte wählen Sie im Auftrag mindestens ein Feld aus.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['unbekanntesFeld'] = 'Eines der gewählten Felder ist unbekannt.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineSuche'] = 'Bitte geben Sie im Auftrag einen Suchtext ein.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineSprache'] = 'Zum Setzen von Werten muss im Auftrag eine Sprache gewählt werden.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['ungueltigesMuster'] = 'Der reguläre Ausdruck ist ungültig.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['ordnerUnbekannt'] = 'Der im Auftrag gewählte Ordner ist nicht (mehr) in der Dateiverwaltung vorhanden.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['ordnerGesperrt'] = 'Der im Auftrag gewählte Ordner liegt außerhalb Ihrer Dateifreigaben.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineFreigabe'] = 'Ihr Benutzerkonto hat keine Dateifreigaben; es gibt nichts zu bearbeiten.';
