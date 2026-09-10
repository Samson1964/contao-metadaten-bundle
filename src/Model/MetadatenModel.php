<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoMetadatenBundle\Model;

use Contao\Model;

/**
 * Model der Tabelle tl_metadaten (gespeicherte Bearbeitungsaufträge).
 *
 * Registriert in config.php über $GLOBALS['TL_MODELS']; die Standardsuchen
 * (findByPk, findAll …) erbt die Klasse von Contao\Model.
 */
class MetadatenModel extends Model
{
	/**
	 * Name der Tabelle
	 *
	 * @var string
	 */
	protected static $strTable = 'tl_metadaten';
}
