<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

use Schachbulle\ContaoMetadatenBundle\Classes\Helfer;
use Schachbulle\ContaoMetadatenBundle\Modules\Metadaten;

/*
 * Backend-Modul „Metadaten“ in der Gruppe „System“, direkt hinter der
 * Dateiverwaltung.
 *
 * Ein Modul ohne Tabelle: Contao erzeugt die Klasse aus 'callback' und ruft
 * generate() auf — in 4.13 und 5.x gleich. array_insert() aus dem Kern gibt
 * es unter Contao 5 nicht mehr, deshalb das Einfügen von Hand; das true in
 * array_slice() erhält die Modulnamen als Schlüssel.
 */
$system = $GLOBALS['BE_MOD']['system'] ?? array();
$neu = array('metadaten' => array('callback' => Metadaten::class));
$position = array_search('files', array_keys($system), true);

if (false === $position)
{
	$GLOBALS['BE_MOD']['system'] = $system + $neu;
}
else
{
	$GLOBALS['BE_MOD']['system'] = array_merge(
		\array_slice($system, 0, $position + 1, true),
		$neu,
		\array_slice($system, $position + 1, null, true)
	);
}

/*
 * Stylesheet der Modulseite nur im Backend laden — im Frontend wäre es
 * totes Gewicht auf jeder Seite
 */
if (Helfer::istBackend())
{
	$GLOBALS['TL_CSS'][] = 'bundles/contaometadaten/css/backend.css|static';
}
