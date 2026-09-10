<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

/*
 * Felder
 */
$GLOBALS['TL_LANG']['tl_metadaten']['titel'] = array('Titel', 'Ein Name für diesen Auftrag, z. B. „Bildunterschrift Vereinsmeisterschaft 2026“.');
$GLOBALS['TL_LANG']['tl_metadaten']['ordner'] = array('Ordner', 'Nur Dateien in diesem Ordner bearbeiten. Ohne Auswahl werden alle Dateien der Dateiverwaltung berücksichtigt.');
$GLOBALS['TL_LANG']['tl_metadaten']['unterordner'] = array('Unterordner einschließen', 'Auch die Dateien in allen Unterordnern des gewählten Ordners bearbeiten.');
$GLOBALS['TL_LANG']['tl_metadaten']['endungen'] = array('Dateiendungen', 'Nur Dateien mit diesen Endungen bearbeiten, durch Komma getrennt (z. B. „jpg, png“). Leer lassen für alle Dateien.');
$GLOBALS['TL_LANG']['tl_metadaten']['sprache'] = array('Sprache', 'Beim Ersetzen: nur diese Sprache bearbeiten oder alle in den Dateien vorhandenen Sprachen. Beim Setzen von Werten ist eine Sprache Pflicht.');
$GLOBALS['TL_LANG']['tl_metadaten']['felder'] = array('Felder', 'Nur die angehakten Felder werden bearbeitet; alle anderen bleiben unverändert.');
$GLOBALS['TL_LANG']['tl_metadaten']['modus'] = array('Betriebsart', '„Suchen und ersetzen“ tauscht einen Text in bereits gefüllten Feldern aus. „Werte setzen“ schreibt feste Werte in die gewählten Felder.');
$GLOBALS['TL_LANG']['tl_metadaten']['suche'] = array('Suchen nach', 'Der zu ersetzende Text. Leere Felder werden beim Ersetzen nie angefasst.');
$GLOBALS['TL_LANG']['tl_metadaten']['ersatz'] = array('Ersetzen durch', 'Der neue Text. Leer lassen, um den Suchtext zu entfernen. Bei regulären Ausdrücken sind Rückverweise wie $1 erlaubt.');
$GLOBALS['TL_LANG']['tl_metadaten']['gross'] = array('Groß- und Kleinschreibung beachten', 'Abgeschaltet findet „berlin“ auch „Berlin“ und „BERLIN“.');
$GLOBALS['TL_LANG']['tl_metadaten']['regex'] = array('Regulärer Ausdruck', 'Den Suchtext als regulären Ausdruck (PCRE) auswerten, ohne Begrenzer. Beispiel: „\s+“ für beliebige Leerräume.');
$GLOBALS['TL_LANG']['tl_metadaten']['wert_title'] = array('Titel', 'Neuer Wert für das Feld „Titel“. Leer lassen, um das Feld zu leeren (nur wirksam, wenn „Titel“ oben angehakt ist).');
$GLOBALS['TL_LANG']['tl_metadaten']['wert_alt'] = array('Alternativer Text', 'Neuer Wert für das Feld „Alternativer Text“.');
$GLOBALS['TL_LANG']['tl_metadaten']['wert_link'] = array('Link', 'Neuer Wert für das Feld „Link“, z. B. eine Adresse oder ein Insert-Tag {{link_url::…}}.');
$GLOBALS['TL_LANG']['tl_metadaten']['wert_caption'] = array('Bildunterschrift', 'Neuer Wert für das Feld „Bildunterschrift“.');
$GLOBALS['TL_LANG']['tl_metadaten']['wert_license'] = array('Lizenz', 'Neuer Wert für das Feld „Lizenz“, z. B. eine Adresse oder ein Insert-Tag {{link_url::…}}.');
$GLOBALS['TL_LANG']['tl_metadaten']['nurLeere'] = array('Nur leere Felder füllen', 'Eingeschaltet bleiben bereits vorhandene Werte stehen. Abgeschaltet werden die gewählten Felder in allen Dateien überschrieben — auch mit einem leeren Wert.');

/*
 * Optionen
 */
$GLOBALS['TL_LANG']['tl_metadaten']['alleSprachen'] = '– alle vorhandenen Sprachen –';
$GLOBALS['TL_LANG']['tl_metadaten']['alleDateien'] = 'alle Dateien';
$GLOBALS['TL_LANG']['tl_metadaten']['ordnerFehlt'] = 'Ordner nicht mehr vorhanden';
$GLOBALS['TL_LANG']['tl_metadaten']['modusOptionen']['ersetzen'] = 'Suchen und ersetzen';
$GLOBALS['TL_LANG']['tl_metadaten']['modusOptionen']['setzen'] = 'Werte setzen';

/*
 * Legenden
 */
$GLOBALS['TL_LANG']['tl_metadaten']['titel_legend'] = 'Auftrag';
$GLOBALS['TL_LANG']['tl_metadaten']['auswahl_legend'] = 'Auswahl der Dateien';
$GLOBALS['TL_LANG']['tl_metadaten']['modus_legend'] = 'Bearbeitung';

/*
 * Operationen
 */
$GLOBALS['TL_LANG']['tl_metadaten']['new'] = array('Neuer Auftrag', 'Einen neuen Bearbeitungsauftrag anlegen');
$GLOBALS['TL_LANG']['tl_metadaten']['edit'] = array('Auftrag bearbeiten', 'Auftrag ID %s bearbeiten');
$GLOBALS['TL_LANG']['tl_metadaten']['copy'] = array('Auftrag duplizieren', 'Auftrag ID %s duplizieren');
$GLOBALS['TL_LANG']['tl_metadaten']['delete'] = array('Auftrag löschen', 'Auftrag ID %s löschen');
$GLOBALS['TL_LANG']['tl_metadaten']['show'] = array('Details', 'Details des Auftrags ID %s anzeigen');
$GLOBALS['TL_LANG']['tl_metadaten']['vorschau'] = array('Vorschau und Ausführen', 'Änderungen des Auftrags ID %s anzeigen und ausführen');
