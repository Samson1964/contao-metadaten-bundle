<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoMetadatenBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Schachbulle\ContaoMetadatenBundle\ContaoMetadatenBundle;

/**
 * Meldet das Bundle beim Contao Manager an.
 */
class Plugin implements BundlePluginInterface
{
	/**
	 * Liefert die Bundle-Konfiguration für den Contao Manager.
	 *
	 * Das Bundle wird nach dem Core-Bundle geladen, damit die eigene
	 * config.php den Eintrag der Dateiverwaltung in $GLOBALS['BE_MOD']
	 * bereits vorfindet und das Modul dahinter einhängen kann.
	 *
	 * @param ParserInterface $parser Der Parser des Contao Managers (ungenutzt)
	 *
	 * @return BundleConfig[] Genau eine Konfiguration für dieses Bundle
	 */
	public function getBundles(ParserInterface $parser): array
	{
		return array(
			BundleConfig::create(ContaoMetadatenBundle::class)
				->setLoadAfter(array(ContaoCoreBundle::class)),
		);
	}
}
