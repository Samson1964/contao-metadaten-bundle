<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

use Schachbulle\ContaoMetadatenBundle\Classes\Helfer;
use Schachbulle\ContaoMetadatenBundle\Model\MetadatenModel;
use Schachbulle\ContaoMetadatenBundle\Modules\Metadaten;

/*
 * Die Aufträge hängen als zweite Tabelle im Modul Dateiverwaltung.
 *
 * Erreichbar über die globale Operation „Metadaten“ der Dateiverwaltung
 * (siehe dca/tl_files.php), Adresse do=files&table=tl_metadaten. Contao
 * erlaubt jede Tabelle, die in 'tables' des Moduls steht; die Rechte folgen
 * damit dem Modul Dateiverwaltung. Zusätzlich verlangt tl_metadaten das
 * Recht „Dateien bearbeiten“ (siehe onload_callback dort).
 *
 * Die Schlüssel metadaten_vorschau und metadaten_bildteile ruft Contao auf,
 * sobald &key=… in der Adresse steht (Operationen in der Auftragsliste), in
 * 4.13 und 5.x gleich. Das Präfix vermeidet Zusammenstöße mit Schlüsseln
 * anderer Erweiterungen im selben Modul.
 *
 * Bis Version 1.7.0 war „Metadaten“ ein eigenes Modul in der Gruppe System.
 */
// Nur einhängen, wenn es die Dateiverwaltung gibt; sonst entstünde ein Modul ohne Kern
if (isset($GLOBALS['BE_MOD']['system']['files']))
{
	$GLOBALS['BE_MOD']['system']['files']['tables'][] = 'tl_metadaten';
	$GLOBALS['BE_MOD']['system']['files']['metadaten_vorschau'] = array(Metadaten::class, 'vorschau');
	$GLOBALS['BE_MOD']['system']['files']['metadaten_bildteile'] = array(Metadaten::class, 'bildteile');
}

/*
 * Model der Auftragstabelle
 */
$GLOBALS['TL_MODELS']['tl_metadaten'] = MetadatenModel::class;

/*
 * Stylesheet der Vorschauseite nur im Backend laden — im Frontend wäre es
 * totes Gewicht auf jeder Seite
 */
if (Helfer::istBackend())
{
	$GLOBALS['TL_CSS'][] = 'bundles/contaometadaten/css/backend.css|static';
}
