<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

use Contao\Backend;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\System;
use Schachbulle\ContaoMetadatenBundle\Classes\Auftrag;
use Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung;

/*
 * Tabelle tl_metadaten: gespeicherte Bearbeitungsaufträge.
 *
 * Ein Auftrag hält Dateiauswahl (Ordner als Dateibaum, Unterordner,
 * Endungen, Sprache, Felder) und Betriebsart samt Suchtext/Ersatztext bzw.
 * festen Werten. Ausgeführt wird er über die Operation „Vorschau“
 * (key=vorschau), die zuerst alle Änderungen zeigt und erst auf Knopfdruck
 * schreibt. Aufträge bleiben erhalten und lassen sich wiederverwenden.
 */
$GLOBALS['TL_DCA']['tl_metadaten'] = array
(
	// Config
	'config' => array
	(
		// Der Kurzname 'Table' existiert unter Contao 5 nicht mehr; der voll
		// qualifizierte Klassenname ist in beiden Fassungen richtig.
		'dataContainer'                 => DC_Table::class,
		'enableVersioning'              => true,
		'sql' => array
		(
			'keys' => array
			(
				'id'                    => 'primary',
			)
		)
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                      => DataContainer::MODE_SORTED,
			'fields'                    => array('titel'),
			'flag'                      => DataContainer::SORT_INITIAL_LETTER_ASC,
			'panelLayout'               => 'search,limit',
			'disableGrouping'           => true,
		),
		'label' => array
		(
			'fields'                    => array('titel'),
			'format'                    => '%s',
			'label_callback'            => array('tl_metadaten', 'label'),
		),
		'global_operations' => array
		(
			'all' => array
			(
				'label'                 => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'                  => 'act=select',
				'class'                 => 'header_edit_all',
				'attributes'            => 'onclick="Backend.getScrollOffset();" accesskey="e"'
			)
		),
		'operations' => array
		(
			'edit' => array
			(
				'label'                 => &$GLOBALS['TL_LANG']['tl_metadaten']['edit'],
				'href'                  => 'act=edit',
				'icon'                  => 'edit.svg',
			),
			'copy' => array
			(
				'label'                 => &$GLOBALS['TL_LANG']['tl_metadaten']['copy'],
				'href'                  => 'act=copy',
				'icon'                  => 'copy.svg'
			),
			'delete' => array
			(
				'label'                 => &$GLOBALS['TL_LANG']['tl_metadaten']['delete'],
				'href'                  => 'act=delete',
				'icon'                  => 'delete.svg',
				'attributes'            => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"'
			),
			// Vorschau und Ausführung: key=vorschau ruft Modules\Metadaten::vorschau()
			'vorschau' => array
			(
				'label'                 => &$GLOBALS['TL_LANG']['tl_metadaten']['vorschau'],
				'href'                  => 'key=vorschau',
				'icon'                  => 'diff.svg',
			),
			'show' => array
			(
				'label'                 => &$GLOBALS['TL_LANG']['tl_metadaten']['show'],
				'href'                  => 'act=show',
				'icon'                  => 'show.svg'
			),
		)
	),

	// Palettes
	'palettes' => array
	(
		'__selector__'                  => array('modus'),
		'default'                       => '{titel_legend},titel;{auswahl_legend},ordner,unterordner,endungen,sprache,felder;{modus_legend},modus',
	),

	// Subpalettes: Ein Select mit __selector__ schaltet über feldname_wert um
	'subpalettes' => array
	(
		'modus_' . Auftrag::MODUS_ERSETZEN => 'suche,ersatz,gross,regex',
		'modus_' . Auftrag::MODUS_SETZEN   => 'wert_title,wert_alt,wert_link,wert_caption,wert_license,nurLeere',
	),

	// Fields
	'fields' => array
	(
		'id' => array
		(
			'sql'                       => "int(10) unsigned NOT NULL auto_increment"
		),
		'tstamp' => array
		(
			'sql'                       => "int(10) unsigned NOT NULL default 0"
		),
		'titel' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['titel'],
			'exclude'                   => true,
			'search'                    => true,
			'inputType'                 => 'text',
			'eval'                      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'                       => "varchar(255) NOT NULL default ''"
		),
		// Der Dateibaum zeigt mit 'files' => false nur Ordner und lädt jeden
		// Zweig erst beim Aufklappen — im Gegensatz zu einer Auswahlliste
		// aller Ordner, die bei großen Dateiverwaltungen den Browser lahmlegt.
		'ordner' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['ordner'],
			'exclude'                   => true,
			'inputType'                 => 'fileTree',
			'eval'                      => array('fieldType' => 'radio', 'files' => false, 'tl_class' => 'clr'),
			'sql'                       => "binary(16) NULL"
		),
		'unterordner' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['unterordner'],
			'exclude'                   => true,
			'inputType'                 => 'checkbox',
			'default'                   => '1',
			'eval'                      => array('tl_class' => 'w50'),
			'sql'                       => "char(1) NOT NULL default '1'"
		),
		'endungen' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['endungen'],
			'exclude'                   => true,
			'inputType'                 => 'text',
			'eval'                      => array('maxlength' => 255, 'tl_class' => 'w50'),
			'sql'                       => "varchar(255) NOT NULL default ''"
		),
		'sprache' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['sprache'],
			'exclude'                   => true,
			'inputType'                 => 'select',
			'options_callback'          => array('tl_metadaten', 'getSprachen'),
			'eval'                      => array('includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_metadaten']['alleSprachen'], 'chosen' => true, 'tl_class' => 'w50 clr'),
			'sql'                       => "varchar(64) NOT NULL default ''"
		),
		'felder' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['felder'],
			'exclude'                   => true,
			'inputType'                 => 'checkbox',
			'options_callback'          => array('tl_metadaten', 'getFelder'),
			'eval'                      => array('multiple' => true, 'mandatory' => true, 'tl_class' => 'clr'),
			'sql'                       => "blob NULL"
		),
		'modus' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['modus'],
			'exclude'                   => true,
			'inputType'                 => 'select',
			'options'                   => array(Auftrag::MODUS_ERSETZEN, Auftrag::MODUS_SETZEN),
			'reference'                 => &$GLOBALS['TL_LANG']['tl_metadaten']['modusOptionen'],
			'default'                   => Auftrag::MODUS_ERSETZEN,
			'eval'                      => array('submitOnChange' => true, 'tl_class' => 'w50'),
			'sql'                       => "varchar(16) NOT NULL default '" . Auftrag::MODUS_ERSETZEN . "'"
		),
		// Such-, Ersatz- und Wertetexte: decodeEntities + allowHtml, damit die
		// Werte so gespeichert werden, wie sie in tl_files.meta stehen sollen
		// (das Metadaten-Feld erlaubt HTML, und „&“ darf nicht zu „&amp;“ werden)
		'suche' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['suche'],
			'exclude'                   => true,
			'inputType'                 => 'text',
			'eval'                      => array('mandatory' => true, 'maxlength' => 255, 'decodeEntities' => true, 'allowHtml' => true, 'tl_class' => 'w50 clr'),
			'sql'                       => "varchar(255) NOT NULL default ''"
		),
		'ersatz' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['ersatz'],
			'exclude'                   => true,
			'inputType'                 => 'text',
			'eval'                      => array('maxlength' => 255, 'decodeEntities' => true, 'allowHtml' => true, 'tl_class' => 'w50'),
			'sql'                       => "varchar(255) NOT NULL default ''"
		),
		'gross' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['gross'],
			'exclude'                   => true,
			'inputType'                 => 'checkbox',
			'default'                   => '1',
			'eval'                      => array('tl_class' => 'w50 clr'),
			'sql'                       => "char(1) NOT NULL default '1'"
		),
		'regex' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['regex'],
			'exclude'                   => true,
			'inputType'                 => 'checkbox',
			'eval'                      => array('tl_class' => 'w50'),
			'sql'                       => "char(1) NOT NULL default ''"
		),
		'wert_title' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['wert_title'],
			'exclude'                   => true,
			'inputType'                 => 'text',
			'eval'                      => array('maxlength' => 255, 'decodeEntities' => true, 'allowHtml' => true, 'tl_class' => 'w50 clr'),
			'sql'                       => "varchar(255) NOT NULL default ''"
		),
		'wert_alt' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['wert_alt'],
			'exclude'                   => true,
			'inputType'                 => 'text',
			'eval'                      => array('maxlength' => 255, 'decodeEntities' => true, 'allowHtml' => true, 'tl_class' => 'w50'),
			'sql'                       => "varchar(255) NOT NULL default ''"
		),
		'wert_link' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['wert_link'],
			'exclude'                   => true,
			'inputType'                 => 'text',
			'eval'                      => array('maxlength' => 2048, 'decodeEntities' => true, 'dcaPicker' => true, 'tl_class' => 'w50 clr'),
			'sql'                       => "text NULL"
		),
		'wert_license' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['wert_license'],
			'exclude'                   => true,
			'inputType'                 => 'text',
			'eval'                      => array('maxlength' => 255, 'decodeEntities' => true, 'dcaPicker' => true, 'tl_class' => 'w50'),
			'sql'                       => "varchar(255) NOT NULL default ''"
		),
		'wert_caption' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['wert_caption'],
			'exclude'                   => true,
			'inputType'                 => 'textarea',
			'eval'                      => array('decodeEntities' => true, 'allowHtml' => true, 'style' => 'height:60px', 'tl_class' => 'clr'),
			'sql'                       => "text NULL"
		),
		'nurLeere' => array
		(
			'label'                     => &$GLOBALS['TL_LANG']['tl_metadaten']['nurLeere'],
			'exclude'                   => true,
			'inputType'                 => 'checkbox',
			'default'                   => '1',
			'eval'                      => array('tl_class' => 'clr'),
			'sql'                       => "char(1) NOT NULL default '1'"
		),
	)
);

/**
 * Rückrufe der Tabelle tl_metadaten.
 *
 * Der Konstruktor ist ausdrücklich öffentlich, weil Backend::__construct()
 * unter Contao 4.13 protected ist und Contao die Klasse von außen erzeugt.
 * Keine der Methoden darf so heißen wie eine statische Methode in System,
 * Controller oder Backend (etwa getLanguages), sonst bricht 4.13 beim Laden
 * der Datei ab.
 */
class tl_metadaten extends Backend
{
	/**
	 * Erzeugt die Rückrufklasse.
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Liefert die im Backend aktivierten Sprachen für das Auswahlfeld.
	 *
	 * Derselbe Dienst, aus dem Contaos MetaWizard seine Sprachen bezieht —
	 * damit stehen hier genau die Sprachen, die auch in der Dateiverwaltung
	 * angeboten werden.
	 *
	 * @return array<string, string> Sprachkürzel => Anzeigename
	 */
	public function getSprachen(): array
	{
		return System::getContainer()->get('contao.intl.locales')->getEnabledLocales();
	}

	/**
	 * Liefert die fünf Metadaten-Felder mit Contaos eigener Bezeichnung.
	 *
	 * Die Bezeichnungen kommen aus MSC.aw_<feld>, damit sie mit dem
	 * MetaWizard der Dateiverwaltung übereinstimmen.
	 *
	 * @return array<string, string> Feldschlüssel => Bezeichnung
	 */
	public function getFelder(): array
	{
		// Im Backend ist „default“ längst geladen; der Aufruf greift nur, wenn
		// die Rückrufklasse außerhalb einer Backend-Anfrage benutzt wird
		if (!isset($GLOBALS['TL_LANG']['MSC']['aw_title']))
		{
			System::loadLanguageFile('default');
		}

		$liste = array();

		foreach (Bearbeitung::FELDER as $feld)
		{
			$liste[$feld] = (string) ($GLOBALS['TL_LANG']['MSC']['aw_' . $feld] ?? $feld);
		}

		return $liste;
	}

	/**
	 * Baut die Zeile der Auftragsliste: Titel, Ordner und Betriebsart.
	 *
	 * @param array  $row   Der Datensatz
	 * @param string $label Das von Contao vorformatierte Label (der Titel)
	 *
	 * @return string HTML der Listenzeile
	 */
	public function label(array $row, string $label): string
	{
		$ordner = $GLOBALS['TL_LANG']['tl_metadaten']['alleDateien'] ?? 'alle Dateien';

		if ($row['ordner'])
		{
			$objOrdner = FilesModel::findByUuid($row['ordner']);
			$ordner = null !== $objOrdner ? $objOrdner->path : ($GLOBALS['TL_LANG']['tl_metadaten']['ordnerFehlt'] ?? '?');

			if ($row['unterordner'])
			{
				$ordner .= '/…';
			}
		}

		$modus = $GLOBALS['TL_LANG']['tl_metadaten']['modusOptionen'][$row['modus']] ?? $row['modus'];

		return sprintf(
			'%s <span class="tl_gray">[%s · %s]</span>',
			$label,
			StringUtil::specialchars($ordner),
			StringUtil::specialchars((string) $modus)
		);
	}
}
