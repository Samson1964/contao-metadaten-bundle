<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

use Contao\DataContainer;
use Schachbulle\ContaoMetadatenBundle\Classes\Helfer;

/*
 * Globale Operation „Metadaten“ in der Dateiverwaltung.
 *
 * Führt zur Liste der Aufträge, die als zweite Tabelle im Modul
 * Dateiverwaltung hängt (do=files&table=tl_metadaten, siehe config.php).
 * Ohne das Recht „Dateien bearbeiten“ wird die Operation beim Laden der
 * Dateiverwaltung wieder entfernt.
 */
$GLOBALS['TL_DCA']['tl_files']['list']['global_operations'] = array_merge(
	array(
		'metadaten' => array
		(
			'label'      => &$GLOBALS['TL_LANG']['tl_files']['metadaten'],
			'href'       => 'table=tl_metadaten',
			'class'      => 'header_metadaten',
			'icon'       => 'bundles/contaometadaten/img/metadaten.svg',
			'attributes' => 'onclick="Backend.getScrollOffset()"',
		),
	),
	$GLOBALS['TL_DCA']['tl_files']['list']['global_operations'] ?? array()
);

$GLOBALS['TL_DCA']['tl_files']['config']['onload_callback'][] = static function (?DataContainer $dc = null): void
{
	if (!Helfer::darfDateienBearbeiten())
	{
		unset($GLOBALS['TL_DCA']['tl_files']['list']['global_operations']['metadaten']);
	}
};
