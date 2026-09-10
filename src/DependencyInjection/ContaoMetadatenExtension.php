<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoMetadatenBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Lädt die Dienstkonfiguration des Bundles in den Symfony-Behälter.
 */
class ContaoMetadatenExtension extends Extension
{
	/**
	 * Lädt die services.yml aus Resources/config.
	 *
	 * @param array            $mergedConfig Zusammengeführte Bundle-Konfiguration (ungenutzt)
	 * @param ContainerBuilder $container    Der zu befüllende Behälter
	 */
	public function load(array $mergedConfig, ContainerBuilder $container): void
	{
		$loader = new YamlFileLoader(
			$container,
			new FileLocator(__DIR__.'/../Resources/config')
		);

		$loader->load('services.yml');
	}
}
