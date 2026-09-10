<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

/*
 * Bootstrap für die Unit-Tests.
 *
 * Wurde im Bundle „composer install“ ausgeführt, genügt der erzeugte
 * Autoloader. Andernfalls (Aufruf über ein anderswo installiertes PHPUnit)
 * wird ein eigener PSR-4-Autoloader vorangestellt. Die geprüften Klassen
 * (Auftrag, Bearbeitung) kommen ohne Contao aus; eine Referenzinstallation
 * wird deshalb nur eingebunden, wenn sie vorhanden ist.
 */

$strRoot = \dirname(__DIR__);

if (is_file($strRoot.'/vendor/autoload.php'))
{
	require $strRoot.'/vendor/autoload.php';

	return;
}

// Vorangestellt, damit die Arbeitsfassung des Bundles vor einer eventuell
// in der Referenzinstallation liegenden älteren Kopie gewinnt
spl_autoload_register(
	static function (string $strClass) use ($strRoot): void
	{
		$arrPrefixe = array(
			'Schachbulle\\ContaoMetadatenBundle\\Tests\\' => $strRoot.'/tests/',
			'Schachbulle\\ContaoMetadatenBundle\\'        => $strRoot.'/src/',
		);

		foreach ($arrPrefixe as $strPrefix => $strDir)
		{
			if (0 !== strncmp($strClass, $strPrefix, \strlen($strPrefix)))
			{
				continue;
			}

			$strFile = $strDir.str_replace('\\', '/', substr($strClass, \strlen($strPrefix))).'.php';

			if (is_file($strFile))
			{
				require $strFile;
			}

			return;
		}
	},
	true,
	true
);

// Contao-Referenzinstallation für die Contao-Klassen (optional)
foreach (array(getenv('CONTAO_TEST_DIR'), $strRoot.'/../../contao-test') as $strContao)
{
	if ($strContao && is_file($strContao.'/vendor/autoload.php'))
	{
		require $strContao.'/vendor/autoload.php';
		break;
	}
}
