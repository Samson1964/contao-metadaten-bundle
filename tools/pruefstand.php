<?php

declare(strict_types=1);

/*
 * Prüfstand für das Metadaten-Bundle.
 *
 * Aufruf:  php tools/pruefstand.php <pfad-zur-contao-installation>
 *
 * Der Prüfstand kommt ohne Composer-Installation des Bundles und ohne
 * Datenbank aus: Er stellt dem Autoloader der Testinstallation einen eigenen
 * voran, lädt Konfiguration, Sprachdateien und Klassen des Bundles und prüft,
 * ob alle benutzten Kern-Klassen, -Methoden und -Dienste in dieser
 * Contao-Fassung vorhanden sind. Das Template wird mit php -l übersetzt.
 */

$bundle = str_replace('\\', '/', dirname(__DIR__));
$root = str_replace('\\', '/', $argv[1] ?? '');

if (!is_dir($root))
{
	fwrite(STDERR, "Installation nicht gefunden: $root\n");
	exit(1);
}

// Eigener Autoloader, vorangestellt, damit die Arbeitsfassung gewinnt
spl_autoload_register(
	static function (string $class) use ($bundle): void
	{
		$prefix = 'Schachbulle\\ContaoMetadatenBundle\\';

		if (0 === strncmp($class, $prefix, \strlen($prefix)))
		{
			$file = $bundle.'/src/'.str_replace('\\', '/', substr($class, \strlen($prefix))).'.php';

			if (is_file($file))
			{
				require $file;
			}
		}
	},
	true,
	true
);

require $root.'/vendor/autoload.php';

$fehler = array();
$hinweise = array();

// Nur Meldungen aus dem eigenen Bundle sollen zählen
set_error_handler(
	static function (int $no, string $str, string $file = '', int $line = 0) use (&$fehler): bool
	{
		if (false !== stripos(str_replace('\\', '/', $file), 'contao-metadaten-bundle'))
		{
			$fehler[] = sprintf('%s in %s:%d', $str, $file, $line);
		}

		return true;
	}
);

/**
 * Meldet das Ergebnis einer Einzelprüfung.
 */
function pruefe(string $was, bool $ok, array &$fehler): void
{
	printf("  [%s] %s\n", $ok ? ' ok ' : 'FEHL', $was);

	if (!$ok)
	{
		$fehler[] = $was;
	}
}

echo "Contao-Quellen: $root\n";
echo "PHP: ".PHP_VERSION."\n\n";

// 1. Kern-Klassen und -Methoden, die das Bundle benutzt
echo "Kern-API\n";
$api = array(
	'Contao\BackendTemplate' => 'parse',
	'Contao\BackendUser' => 'getInstance',
	'Contao\Config' => 'get',
	'Contao\Database' => 'getInstance',
	'Contao\Database\Statement' => 'execute',
	'Contao\Database\Result' => 'fetchAllAssoc',
	'Contao\FilesModel' => 'findMultipleByUuids',
	'Contao\Input' => 'postRaw',
	'Contao\Message' => 'addConfirmation',
	'Contao\StringUtil' => 'ampersand',
	'Contao\System' => 'loadLanguageFile',
	'Contao\Versions' => 'initialize',
	'Contao\CoreBundle\Csrf\ContaoCsrfTokenManager' => 'getDefaultTokenValue',
	'Contao\CoreBundle\Exception\RedirectResponseException' => null,
	'Contao\CoreBundle\Routing\ScopeMatcher' => 'isBackendRequest',
	'Contao\CoreBundle\Intl\Locales' => 'getEnabledLocales',
	'Symfony\Component\HttpFoundation\RequestStack' => 'getSession',
);

foreach ($api as $klasse => $methode)
{
	$ok = class_exists($klasse) && (null === $methode || method_exists($klasse, $methode));
	pruefe($klasse.($methode ? '::'.$methode.'()' : ''), $ok, $fehler);
}

pruefe('Message::addError()', method_exists('Contao\Message', 'addError'), $fehler);
pruefe('Message::generate()', method_exists('Contao\Message', 'generate'), $fehler);
pruefe('StringUtil::specialchars()', method_exists('Contao\StringUtil', 'specialchars'), $fehler);
pruefe('Versions::create()', method_exists('Contao\Versions', 'create'), $fehler);

// Message::addError() ohne Scope-Argument: in 4.13 ist der Vorgabewert TL_MODE,
// in 5.7 null — ein selbst übergebenes TL_MODE wäre unter Contao 5 tödlich
$refMessage = new ReflectionMethod('Contao\Message', 'addError');
pruefe('Message::addError() ohne zweites Argument aufrufbar', $refMessage->getNumberOfRequiredParameters() <= 1, $fehler);

// Versions: Konstruktor nimmt Tabelle und ID, mehr braucht das Modul nicht
$refVersions = new ReflectionMethod('Contao\Versions', '__construct');
pruefe('Versions::__construct(Tabelle, ID)', 2 === $refVersions->getNumberOfParameters(), $fehler);

// Abgelöste Kern-API, die das Bundle nicht mehr benutzen darf
echo "\nAbgelöste Kern-API (nur zur Information)\n";
printf("  Konstante TL_MODE         %s\n", \defined('TL_MODE') ? 'vorhanden' : 'entfallen');
printf("  Konstante REQUEST_TOKEN   %s\n", \defined('REQUEST_TOKEN') ? 'vorhanden' : 'entfallen');
printf("  Funktion ampersand()      %s\n", \function_exists('ampersand') ? 'vorhanden' : 'entfallen');
printf("  Funktion specialchars()   %s\n", \function_exists('specialchars') ? 'vorhanden' : 'entfallen');
printf("  Funktion array_insert()   %s\n", \function_exists('array_insert') ? 'vorhanden' : 'entfallen');

// 2. Sprachdateien laden
echo "\nSprachdateien\n";
$GLOBALS['TL_LANG'] = array();

foreach (glob($bundle.'/src/Resources/contao/languages/de/*.php') as $datei)
{
	require $datei;
	pruefe(basename($datei), true, $fehler);
}

pruefe('MOD.metadaten beschriftet', isset($GLOBALS['TL_LANG']['MOD']['metadaten'][0]), $fehler);
pruefe('METADATEN.erledigt vorhanden', isset($GLOBALS['TL_LANG']['METADATEN']['erledigt']), $fehler);

$fehlerschluessel = array('unbekannterModus', 'keineFelder', 'unbekanntesFeld', 'keineSuche', 'keineSprache', 'ungueltigesMuster', 'ordnerUnbekannt', 'spracheUnbekannt', 'keineFreigabe');

foreach ($fehlerschluessel as $schluessel)
{
	pruefe('Fehlertext '.$schluessel, isset($GLOBALS['TL_LANG']['METADATEN']['fehler'][$schluessel]), $fehler);
}

// 3. Ein Minimalbehälter, damit die config.php laufen kann. Ohne
//    Scope-Matcher meldet Helfer::istBackend() „nein“, und das Stylesheet
//    wird nicht eingetragen — genau das wird unten geprüft.
$objContainer = new Symfony\Component\DependencyInjection\ContainerBuilder();
$objContainer->setParameter('kernel.debug', false);
$objContainer->set('request_stack', new Symfony\Component\HttpFoundation\RequestStack());
Contao\System::setContainer($objContainer);

echo "\nKonfiguration\n";
$GLOBALS['BE_MOD'] = array(
	'system' => array(
		'files' => array('tables' => array('tl_files')),
		'log' => array('tables' => array('tl_log')),
	),
);
$GLOBALS['TL_CSS'] = array();

require $bundle.'/src/Resources/contao/config/config.php';
pruefe('config.php geladen', true, $fehler);
pruefe('Backend-Modul metadaten angemeldet', isset($GLOBALS['BE_MOD']['system']['metadaten']['callback']), $fehler);
pruefe('Modul steht direkt hinter der Dateiverwaltung', array('files', 'metadaten', 'log') === array_keys($GLOBALS['BE_MOD']['system']), $fehler);
pruefe('Stylesheet außerhalb des Backends nicht geladen', array() === $GLOBALS['TL_CSS'], $fehler);
pruefe('Helfer::requestToken() ohne Dienst liefert leeren Text', '' === Schachbulle\ContaoMetadatenBundle\Classes\Helfer::requestToken(), $fehler);

// 4. Modulklasse: Contao erzeugt sie mit new Klasse($dc) und ruft generate()
echo "\nModulklasse\n";
$modul = $GLOBALS['BE_MOD']['system']['metadaten']['callback'];
pruefe('Klasse '.$modul.' ladbar', class_exists($modul), $fehler);
pruefe('generate() vorhanden', method_exists($modul, 'generate'), $fehler);

$refKonstruktor = (new ReflectionClass($modul))->getConstructor();
pruefe('Konstruktor nimmt den DataContainer entgegen', null !== $refKonstruktor && 1 === $refKonstruktor->getNumberOfParameters() && 0 === $refKonstruktor->getNumberOfRequiredParameters(), $fehler);

// 5. Kernlogik ohne Datenbank
echo "\nKernlogik\n";
$auftrag = new Schachbulle\ContaoMetadatenBundle\Classes\Auftrag();
$auftrag->felder = array('caption');
$auftrag->suche = 'Foto: BSV';
$auftrag->ersatz = 'Foto: Berliner Schachverband';

$alt = array('de' => array('title' => 'Titel', 'alt' => '', 'link' => '', 'caption' => 'Foto: BSV', 'license' => ''));
$neu = Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung::anwenden($alt, $auftrag);
pruefe('Ersetzen in der Bildunterschrift', 'Foto: Berliner Schachverband' === $neu['de']['caption'], $fehler);
pruefe('genau ein Unterschied gemeldet', 1 === \count(Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung::unterschiede($alt, $neu)), $fehler);
pruefe('Auftrag besteht die Prüfung', array() === Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung::pruefen($auftrag), $fehler);

// 6. Template übersetzen
echo "\nTemplate\n";
$template = $bundle.'/src/Resources/contao/templates/be_metadaten.html5';
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($template).' 2>&1', $ausgabe, $status);
pruefe('be_metadaten.html5 syntaktisch in Ordnung', 0 === $status, $fehler);

// 7. Dienste, die das Bundle zur Laufzeit holt
echo "\nDienste im kompilierten Behälter\n";
$container = null;
$treffer = glob($root.'/var/cache/prod/Container*/*Container.php');

if (!$treffer)
{
	$treffer = glob($root.'/var/cache/prod/*Container.php');
}

if ($treffer)
{
	$container = $treffer[0];
}

if (null === $container)
{
	$hinweise[] = 'Kein kompilierter Behälter unter var/cache/prod gefunden — Dienste nicht geprüft.';
	echo "  (übersprungen)\n";
}
else
{
	$inhalt = file_get_contents($container);

	foreach (array('contao.intl.locales', 'contao.csrf.token_manager', 'contao.routing.scope_matcher', 'request_stack', 'router') as $dienst)
	{
		pruefe($dienst.' öffentlich', false !== strpos($inhalt, "'".$dienst."' =>"), $fehler);
	}
}

restore_error_handler();

echo "\n";

foreach ($hinweise as $hinweis)
{
	echo "Hinweis: $hinweis\n";
}

if ($fehler)
{
	echo "\nFEHLGESCHLAGEN (".\count($fehler)."):\n";

	foreach ($fehler as $f)
	{
		echo "  - $f\n";
	}

	exit(1);
}

echo "Alles in Ordnung.\n";
exit(0);
