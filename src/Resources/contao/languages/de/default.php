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

$GLOBALS['TL_LANG']['METADATEN']['bildteileLink'] = 'Diese Bilder von Hand markieren';

/*
 * Bildteil-Editor (key=bildteile)
 */
$GLOBALS['TL_LANG']['METADATEN']['bildteileHeadline'] = 'Wichtige Bildteile markieren: %s';
$GLOBALS['TL_LANG']['METADATEN']['legendBildteile'] = 'Bilder ohne wichtigen Teil';
$GLOBALS['TL_LANG']['METADATEN']['bildteileOffen'] = '%d Bilder in „%s“ haben noch keinen wichtigen Teil.';
$GLOBALS['TL_LANG']['METADATEN']['bildteileFertig'] = 'Alle Bilder in „%s“ haben einen wichtigen Teil. Hier gibt es nichts mehr zu tun.';
$GLOBALS['TL_LANG']['METADATEN']['bildteileAnleitung'] = 'Das rote Rechteck ist ein Vorschlag. Er folgt der Schärfe des Fotos und den Personen, die der Browser darin erkennt: Der scharf abgebildete Spieler bestimmt die Breite, ebenso scharfe Nachbarn kommen dazu, die Höhe reicht fast über das ganze Bild. Mit „1 Spieler“ und „2 Spieler“ schalten Sie zwischen dem Ausschnitt um den scharfen Spieler und einem breiten Ausschnitt für zwei nebeneinander sitzende Spieler um; vorgewählt ist, was die Bildanalyse vermutet. Ziehen Sie es an die richtige Stelle, ändern Sie die Größe an den Ecken oder ziehen Sie daneben im Bild ein neues Rechteck auf. Mit der Tastatur: Pfeiltasten verschieben, Umschalt+Pfeiltasten ändern die Größe. Gespeichert werden nur Bilder mit Häkchen bei „übernehmen“; wer ein Rechteck anfasst, setzt das Häkchen automatisch. Alle anderen Bilder bleiben unmarkiert und erscheinen beim nächsten Mal wieder.';
$GLOBALS['TL_LANG']['METADATEN']['bildteileUnlesbar'] = '%d Bilder lassen sich nicht darstellen (Datei fehlt, ist beschädigt oder zu groß für den Speicher):';
$GLOBALS['TL_LANG']['METADATEN']['bildteileAlle'] = 'Alle anhaken';
$GLOBALS['TL_LANG']['METADATEN']['bildteileKeine'] = 'Keines anhaken';
$GLOBALS['TL_LANG']['METADATEN']['bildteileSeite'] = 'Seite %d von %d';
$GLOBALS['TL_LANG']['METADATEN']['bildteileZurueck'] = '‹ vorige Seite';
$GLOBALS['TL_LANG']['METADATEN']['bildteileWeiter'] = 'nächste Seite ›';
$GLOBALS['TL_LANG']['METADATEN']['bildteileAria'] = 'Wichtiger Teil von %s, mit den Pfeiltasten verschieben';
$GLOBALS['TL_LANG']['METADATEN']['bildteileUebernehmen'] = 'übernehmen';
$GLOBALS['TL_LANG']['METADATEN']['bildteileVorschlag'] = 'Vorschlag';
$GLOBALS['TL_LANG']['METADATEN']['bildteileVorschlagTitel'] = 'Das Rechteck auf den ursprünglichen Vorschlag zurücksetzen';
$GLOBALS['TL_LANG']['METADATEN']['bildteileOhneSchaetzung'] = 'Das Bild hat keine erkennbare Schärfe, die Bildmitte ist vorbelegt.';
$GLOBALS['TL_LANG']['METADATEN']['spielerGruppe'] = 'Ausschnitt für einen oder zwei Spieler';
$GLOBALS['TL_LANG']['METADATEN']['spielerEins'] = '1 Spieler';
$GLOBALS['TL_LANG']['METADATEN']['spielerZwei'] = '2 Spieler';
$GLOBALS['TL_LANG']['METADATEN']['spielerEinsTitel'] = 'Ausschnitt um den scharf abgebildeten Spieler';
$GLOBALS['TL_LANG']['METADATEN']['spielerZweiTitel'] = 'Breiter Ausschnitt für zwei nebeneinander sitzende Spieler';
$GLOBALS['TL_LANG']['METADATEN']['personenSuche'] = 'Suche Personen …';
$GLOBALS['TL_LANG']['METADATEN']['personenEins'] = 'Eine Person erkannt.';
$GLOBALS['TL_LANG']['METADATEN']['personenViele'] = '%d Personen erkannt.';
$GLOBALS['TL_LANG']['METADATEN']['personenKeins'] = 'Keine Person erkannt.';
$GLOBALS['TL_LANG']['METADATEN']['bildteileSpeichern'] = 'Angehakte Bildteile speichern';
$GLOBALS['TL_LANG']['METADATEN']['bildteileZurVorschau'] = 'Zur Vorschau des Auftrags';
$GLOBALS['TL_LANG']['METADATEN']['bildteileNichts'] = 'Es war kein Bild angehakt, deshalb wurde nichts gespeichert.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['bildteilUngueltig'] = 'Bei %d angehakten Bildern waren die Werte des Rechtecks unbrauchbar; sie wurden nicht gespeichert.';

/*
 * Dateinamen bereinigen
 */
$GLOBALS['TL_LANG']['METADATEN']['legendDateinamen'] = 'Dateinamen';
$GLOBALS['TL_LANG']['METADATEN']['dateinamenKeine'] = 'Alle Dateinamen der Auswahl entsprechen bereits den Regeln.';
$GLOBALS['TL_LANG']['METADATEN']['dateinamenAnzahl'] = '%d Dateien werden umbenannt.';
$GLOBALS['TL_LANG']['METADATEN']['dateinamenKonflikte'] = '%d Dateien werden übersprungen, weil ihr neuer Name im selben Ordner schon vergeben ist oder zweimal vorkäme.';
$GLOBALS['TL_LANG']['METADATEN']['dateinamenHinweis'] = 'Inhaltselemente, Galerien und Insert-Tags verweisen über die UUID auf Dateien und bleiben gültig. Fest eingetragene Pfade, etwa in Texten des Editors oder in Verweisen von außen, zeigen nach dem Umbenennen ins Leere. Das Umbenennen legt keine Version an.';
$GLOBALS['TL_LANG']['METADATEN']['dateinamenBelegt'] = '(Name vergeben, wird übersprungen)';
$GLOBALS['TL_LANG']['METADATEN']['spalteOrdner'] = 'Ordner';
$GLOBALS['TL_LANG']['METADATEN']['umbenannt'] = '%d Dateien wurden umbenannt.';
$GLOBALS['TL_LANG']['METADATEN']['nichtUmbenannt'] = '%d Dateien wurden nicht umbenannt, weil der neue Name vergeben war oder das Umbenennen scheiterte.';

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
$GLOBALS['TL_LANG']['METADATEN']['fehler']['nichtsZuTun'] = 'Der Auftrag ändert weder Metadaten noch Bildteile noch Dateinamen. Bitte wählen Sie eine Betriebsart, haken Sie „Wichtigen Bildteil automatisch markieren“ an oder wählen Sie Regeln für die Dateinamen.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keinGd'] = 'Die PHP-Erweiterung GD fehlt auf diesem Server; der wichtige Bildteil lässt sich nicht automatisch bestimmen.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineFelder'] = 'Bitte wählen Sie im Auftrag mindestens ein Feld aus.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['unbekanntesFeld'] = 'Eines der gewählten Felder ist unbekannt.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineSuche'] = 'Bitte geben Sie im Auftrag einen Suchtext ein.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineSprache'] = 'Zum Setzen von Werten muss im Auftrag eine Sprache gewählt werden.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['ungueltigesMuster'] = 'Der reguläre Ausdruck ist ungültig.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['ordnerUnbekannt'] = 'Der im Auftrag gewählte Ordner ist nicht (mehr) in der Dateiverwaltung vorhanden.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['ordnerGesperrt'] = 'Der im Auftrag gewählte Ordner liegt außerhalb Ihrer Dateifreigaben.';
$GLOBALS['TL_LANG']['METADATEN']['fehler']['keineFreigabe'] = 'Ihr Benutzerkonto hat keine Dateifreigaben; es gibt nichts zu bearbeiten.';
