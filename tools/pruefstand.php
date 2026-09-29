<?php

declare(strict_types=1);

/*
 * Prüfstand für das Metadaten-Bundle.
 *
 * Aufruf:  php tools/pruefstand.php <pfad-zur-contao-installation>
 *
 * Der Prüfstand kommt ohne Composer-Installation des Bundles und ohne
 * Datenbank aus: Er stellt dem Autoloader der Testinstallation einen eigenen
 * voran, lädt Konfiguration, Sprachdateien, DCA und Klassen des Bundles und
 * prüft, ob alle benutzten Kern-Klassen, -Methoden und -Dienste in dieser
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
	'Contao\Backend' => null,
	'Contao\BackendTemplate' => 'parse',
	'Contao\BackendUser' => 'getInstance',
	'Contao\Config' => 'get',
	'Contao\DataContainer' => null,
	'Contao\DC_Table' => null,
	'Contao\Database' => 'getInstance',
	'Contao\Database\Statement' => 'execute',
	'Contao\Database\Result' => 'fetchAllAssoc',
	'Contao\FilesModel' => 'findByUuid',
	'Contao\Input' => 'get',
	'Contao\Message' => 'addConfirmation',
	'Contao\Model' => 'findByPk',
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

pruefe('FilesModel::findMultipleByUuids()', method_exists('Contao\FilesModel', 'findMultipleByUuids'), $fehler);
pruefe('Message::addInfo()', method_exists('Contao\Message', 'addInfo'), $fehler);
pruefe('Database\Statement::set()', method_exists('Contao\Database\Statement', 'set'), $fehler);

// Bildfabrik und Resizer für die Vorschaubilder der Bildanalyse
pruefe('ImageFactory::create()', method_exists('Contao\CoreBundle\Image\ImageFactory', 'create'), $fehler);
pruefe('Schnittstelle DeferredImageInterface', interface_exists('Contao\Image\DeferredImageInterface'), $fehler);
pruefe('DeferredResizerInterface::resizeDeferredImage()', interface_exists('Contao\Image\DeferredResizerInterface') && method_exists('Contao\Image\DeferredResizerInterface', 'resizeDeferredImage'), $fehler);
pruefe('ImageInterface::getUrl() und getPath()', method_exists('Contao\Image\ImageInterface', 'getUrl') && method_exists('Contao\Image\ImageInterface', 'getPath'), $fehler);
pruefe('Größenmodus box vorhanden', 'box' === Contao\Image\ResizeConfiguration::MODE_BOX, $fehler);
pruefe('PHP-Erweiterung GD für die Bildanalyse', Schachbulle\ContaoMetadatenBundle\Classes\Bildanalyse::verfuegbar(), $fehler);
pruefe('Input::post()', method_exists('Contao\Input', 'post'), $fehler);
pruefe('Message::addError()', method_exists('Contao\Message', 'addError'), $fehler);
pruefe('Message::generate()', method_exists('Contao\Message', 'generate'), $fehler);
pruefe('StringUtil::specialchars()', method_exists('Contao\StringUtil', 'specialchars'), $fehler);
pruefe('Versions::create()', method_exists('Contao\Versions', 'create'), $fehler);

// Message::addError() ohne Scope-Argument: in 4.13 ist der Vorgabewert TL_MODE,
// in 5.7 null — ein selbst übergebenes TL_MODE wäre unter Contao 5 tödlich
$refMessage = new ReflectionMethod('Contao\Message', 'addError');
pruefe('Message::addError() ohne zweites Argument aufrufbar', $refMessage->getNumberOfRequiredParameters() <= 1, $fehler);

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

pruefe('globale Operation tl_files.metadaten beschriftet', isset($GLOBALS['TL_LANG']['tl_files']['metadaten'][0]), $fehler);
pruefe('kein eigener Menüeintrag mehr (MOD.metadaten)', !isset($GLOBALS['TL_LANG']['MOD']['metadaten']), $fehler);
pruefe('Regeln für Dateinamen beschriftet', array() === array_diff(Schachbulle\ContaoMetadatenBundle\Classes\Dateiname::REGELN, array_keys($GLOBALS['TL_LANG']['tl_metadaten']['dateinamenOptionen'] ?? array())), $fehler);
pruefe('METADATEN.erledigt vorhanden', isset($GLOBALS['TL_LANG']['METADATEN']['erledigt']), $fehler);
pruefe('tl_metadaten.vorschau (Operation) beschriftet', isset($GLOBALS['TL_LANG']['tl_metadaten']['vorschau'][0]), $fehler);

$fehlerschluessel = array('nichtsZuTun', 'keinGd', 'auftragUnbekannt', 'unbekannterModus', 'keineFelder', 'unbekanntesFeld', 'keineSuche', 'keineSprache', 'ungueltigesMuster', 'ordnerUnbekannt', 'ordnerGesperrt', 'keineFreigabe');

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
pruefe('tl_metadaten als zweite Tabelle der Dateiverwaltung', array('tl_files', 'tl_metadaten') === ($GLOBALS['BE_MOD']['system']['files']['tables'] ?? null), $fehler);
pruefe('key=metadaten_vorschau an der Dateiverwaltung', isset($GLOBALS['BE_MOD']['system']['files']['metadaten_vorschau'][1]), $fehler);
pruefe('key=metadaten_bildteile an der Dateiverwaltung', isset($GLOBALS['BE_MOD']['system']['files']['metadaten_bildteile'][1]), $fehler);
pruefe('kein eigenes Modul mehr in der Gruppe System', array('files', 'log') === array_keys($GLOBALS['BE_MOD']['system']), $fehler);
pruefe('Model in TL_MODELS eingetragen', isset($GLOBALS['TL_MODELS']['tl_metadaten']) && is_subclass_of($GLOBALS['TL_MODELS']['tl_metadaten'], 'Contao\Model'), $fehler);
pruefe('Stylesheet außerhalb des Backends nicht geladen', array() === $GLOBALS['TL_CSS'], $fehler);
pruefe('Helfer::requestToken() ohne Dienst liefert leeren Text', '' === Schachbulle\ContaoMetadatenBundle\Classes\Helfer::requestToken(), $fehler);

// 4. DCA laden. Dabei werden DC_Table::class, die DataContainer-Konstanten
//    und die Rückrufklasse (extends Backend) tatsächlich aufgelöst.
echo "\nDCA tl_metadaten\n";
$GLOBALS['TL_DCA'] = array();
require $bundle.'/src/Resources/contao/dca/tl_metadaten.php';
$dca = $GLOBALS['TL_DCA']['tl_metadaten'] ?? array();

pruefe('DCA geladen', array() !== $dca, $fehler);
pruefe('dataContainer ist DC_Table::class', 'Contao\DC_Table' === ($dca['config']['dataContainer'] ?? ''), $fehler);
pruefe('Ordner ist ein fileTree nur für Ordner', 'fileTree' === ($dca['fields']['ordner']['inputType'] ?? '') && false === ($dca['fields']['ordner']['eval']['files'] ?? null), $fehler);
pruefe('keine children-Operation', !isset($dca['list']['operations']['children']), $fehler);
pruefe('Operation vorschau zeigt auf key=metadaten_vorschau', 'key=metadaten_vorschau' === ($dca['list']['operations']['vorschau']['href'] ?? ''), $fehler);
pruefe('Operation bildteile zeigt auf key=metadaten_bildteile', 'key=metadaten_bildteile' === ($dca['list']['operations']['bildteile']['href'] ?? ''), $fehler);
pruefe('jede Operation ist beschriftet', array() === array_diff(array_keys($dca['list']['operations']), array_keys($GLOBALS['TL_LANG']['tl_metadaten'])), $fehler);
pruefe('Knopf zurück zur Dateiverwaltung', 'table=tl_files' === ($dca['list']['global_operations']['dateiverwaltung']['href'] ?? ''), $fehler);
pruefe('Rechteprüfung beim Laden', array(array('tl_metadaten', 'pruefeRecht')) === ($dca['config']['onload_callback'] ?? null), $fehler);
pruefe('Feld dateinamen mit allen Regeln', Schachbulle\ContaoMetadatenBundle\Classes\Dateiname::REGELN === ($dca['fields']['dateinamen']['options'] ?? null), $fehler);

// Globale Operation in der Dateiverwaltung
$GLOBALS['TL_DCA']['tl_files'] = array('config' => array(), 'list' => array('global_operations' => array('all' => array('href' => 'act=select'))));
require $bundle.'/src/Resources/contao/dca/tl_files.php';
$dateiDca = $GLOBALS['TL_DCA']['tl_files'];
pruefe('tl_files: globale Operation metadaten führt zu table=tl_metadaten', 'table=tl_metadaten' === ($dateiDca['list']['global_operations']['metadaten']['href'] ?? ''), $fehler);
pruefe('tl_files: bestehende globale Operationen bleiben', isset($dateiDca['list']['global_operations']['all']), $fehler);
pruefe('tl_files: Symbol vorhanden', is_file($bundle.'/src/Resources/public/img/metadaten.svg'), $fehler);

// Ohne Sicherheitsdienst gilt „kein Recht“: Die Operation muss verschwinden
foreach ($dateiDca['config']['onload_callback'] ?? array() as $rueckruf)
{
	if ($rueckruf instanceof Closure)
	{
		$rueckruf(null);
	}
}

pruefe('tl_files: ohne Recht „Dateien bearbeiten“ keine Operation', !isset($GLOBALS['TL_DCA']['tl_files']['list']['global_operations']['metadaten']), $fehler);

$ohneSql = array();

foreach ($dca['fields'] as $feld => $definition)
{
	if (!isset($definition['sql']))
	{
		$ohneSql[] = $feld;
	}
}

pruefe('jedes Feld hat eine sql-Definition', array() === $ohneSql, $fehler);

$palettenfelder = array();

foreach (array_merge(array($dca['palettes']['default']), array_values($dca['subpalettes'])) as $palette)
{
	foreach (preg_split('/[;,]/', preg_replace('/\{[^}]+\}/', '', $palette)) as $feld)
	{
		if ('' !== $feld)
		{
			$palettenfelder[] = $feld;
		}
	}
}

pruefe('alle Palettenfelder sind definiert', array() === array_diff($palettenfelder, array_keys($dca['fields'])), $fehler);
pruefe('Subpaletten für beide Betriebsarten', isset($dca['subpalettes']['modus_ersetzen'], $dca['subpalettes']['modus_setzen']), $fehler);
pruefe('Betriebsart „keine“ wählbar, ohne eigene Subpalette', \in_array('keine', $dca['fields']['modus']['options'], true) && !isset($dca['subpalettes']['modus_keine']), $fehler);
pruefe('jede Betriebsart ist beschriftet', array() === array_diff($dca['fields']['modus']['options'], array_keys($GLOBALS['TL_LANG']['tl_metadaten']['modusOptionen'])), $fehler);
pruefe('Häkchen wichtigerTeil in der Grundpalette', false !== strpos($dca['palettes']['default'], 'wichtigerTeil') && 'checkbox' === ($dca['fields']['wichtigerTeil']['inputType'] ?? ''), $fehler);

// Rückrufklasse: öffentlicher Konstruktor, keine Kollision mit statischen
// Kern-Methoden (bricht unter 4.13 schon beim Laden ab)
pruefe('Klasse tl_metadaten vorhanden', class_exists('tl_metadaten', false), $fehler);
$refKlasse = new ReflectionClass('tl_metadaten');
pruefe('tl_metadaten::__construct() öffentlich', $refKlasse->getConstructor()->isPublic(), $fehler);

$kollisionen = array();

foreach ($refKlasse->getMethods() as $methode)
{
	if ($methode->getDeclaringClass()->getName() !== 'tl_metadaten' || $methode->isStatic())
	{
		continue;
	}

	foreach (array('Contao\Backend', 'Contao\Controller', 'Contao\System') as $kern)
	{
		if (method_exists($kern, $methode->getName()) && (new ReflectionMethod($kern, $methode->getName()))->isStatic())
		{
			$kollisionen[] = $methode->getName();
		}
	}
}

pruefe('keine Kollision mit statischen Kern-Methoden', array() === $kollisionen, $fehler);

foreach (array('getSprachen', 'getFelder', 'label') as $rueckruf)
{
	pruefe('Rückruf tl_metadaten::'.$rueckruf.'() vorhanden', method_exists('tl_metadaten', $rueckruf), $fehler);
}

// Die MSC.aw_*-Labels kommen im Backend aus Contaos default.xlf; hier
// werden sie vorgegeben, damit getFelder() keine Sprachdatei laden muss
foreach (Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung::FELDER as $feld)
{
	$GLOBALS['TL_LANG']['MSC']['aw_'.$feld] = ucfirst($feld);
}

$objRueckruf = $refKlasse->newInstanceWithoutConstructor();
$felder = $objRueckruf->getFelder();
pruefe('getFelder() liefert die fünf Metadaten-Felder', Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung::FELDER === array_keys($felder), $fehler);

// 5. Modulklasse: Contao erzeugt sie mit System::importStatic() ohne Argumente
echo "\nModulklasse\n";
$modul = $GLOBALS['BE_MOD']['system']['files']['metadaten_vorschau'][0];
pruefe('Klasse '.$modul.' ladbar', class_exists($modul), $fehler);
pruefe('vorschau() vorhanden', method_exists($modul, 'vorschau'), $fehler);
pruefe('bildteile() vorhanden', method_exists($modul, 'bildteile'), $fehler);
pruefe('parameterlos erzeugbar (System::importStatic)', null === (new ReflectionClass($modul))->getConstructor(), $fehler);

// 6. Kernlogik ohne Datenbank
echo "\nKernlogik\n";
$auftrag = Schachbulle\ContaoMetadatenBundle\Classes\Auftrag::ausDatensatz(array(
	'modus'  => 'ersetzen',
	'felder' => serialize(array('caption')),
	'suche'  => 'Foto: BSV',
	'ersatz' => 'Foto: Berliner Schachverband',
	'gross'  => '1',
));

$alt = array('de' => array('title' => 'Titel', 'alt' => '', 'link' => '', 'caption' => 'Foto: BSV', 'license' => ''));
$neu = Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung::anwenden($alt, $auftrag);
pruefe('Auftrag aus Datensatz besteht die Prüfung', array() === Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung::pruefen($auftrag), $fehler);
pruefe('Ersetzen in der Bildunterschrift', 'Foto: Berliner Schachverband' === $neu['de']['caption'], $fehler);
pruefe('genau ein Unterschied gemeldet', 1 === \count(Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung::unterschiede($alt, $neu)), $fehler);

// 7. Template übersetzen
echo "\nTemplate\n";
foreach (array('be_metadaten', 'be_metadaten_bildteile') as $name)
{
	$template = $bundle.'/src/Resources/contao/templates/'.$name.'.html5';
	exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($template).' 2>&1', $ausgabe, $status);
	pruefe($name.'.html5 syntaktisch in Ordnung', 0 === $status, $fehler);
}

// Skript und Template des Bildteil-Editors hängen über data-Attribute
// zusammen; fehlt eines im Template, bleibt das Rechteck unbeweglich
echo "\nBildteil-Editor\n";
$skript = (string) file_get_contents($bundle.'/src/Resources/public/js/bildteile.js');
$editor = (string) file_get_contents($bundle.'/src/Resources/contao/templates/be_metadaten_bildteile.html5');
pruefe('bildteile.js vorhanden', '' !== $skript, $fehler);

foreach (array('data-metadaten-karte', 'data-metadaten-editor', 'data-metadaten-teil', 'data-ecke', 'data-feld', 'data-metadaten-haken', 'data-metadaten-zurueck', 'data-metadaten-alle', 'data-vorschlag', 'data-min', 'data-metadaten-spieler', 'data-schmal', 'data-breit', 'data-zwei') as $attribut)
{
	pruefe($attribut.' in Skript und Template', false !== strpos($skript, $attribut) && false !== strpos($editor, $attribut), $fehler);
}

// Personenerkennung: eigenes Skript und die unverändert beigelegten MediaPipe-Dateien
$personen = (string) file_get_contents($bundle.'/src/Resources/public/js/personen.js');
pruefe('personen.js vorhanden', '' !== $personen, $fehler);

foreach (array('data-metadaten-mediapipe', 'data-wasm', 'data-modell', 'data-schaerfe', 'data-schmal', 'data-metadaten-fund', 'data-text-suche', 'data-text-eins', 'data-text-viele', 'data-text-keins') as $attribut)
{
	pruefe($attribut.' in personen.js und Template', false !== strpos($personen, $attribut) && false !== strpos($editor, $attribut), $fehler);
}

// Die Sperre für den Versand von Nutzungsdaten an Google muss vor MediaPipe greifen
pruefe('personen.js sperrt odml.pa.googleapis.com', false !== strpos($personen, 'odml\.pa\.googleapis\.com'), $fehler);

$fremd = array(
	'mediapipe/vision_bundle.js'               => '98db72469ffb176f5e9f2687be0f70783893aca681f7789c34b872b0a764371a',
	'mediapipe/wasm/vision_wasm_internal.js'   => 'e170ee67dd4e16c1a6fcd8840a206687e5a59b22c20e4a902bc445b095454d73',
	'mediapipe/wasm/vision_wasm_internal.wasm' => '8da277a733926eacd0474b8704b36742d6ec3231c57a860c5b889dff8f1df886',
	'mediapipe/efficientdet_lite0_int8.tflite' => '0720bf247bd76e6594ea28fa9c6f7c5242be774818997dbbeffc4da460c723bb',
	'mediapipe/LICENSE'                        => '8707eef0533987efc5b155d64761eeb6e20793f50b9bd1a68dad1cf4719d0ed8',
);

foreach ($fremd as $datei => $summe)
{
	$pfad = $bundle.'/src/Resources/public/js/vendor/'.$datei;
	pruefe('vendor/'.$datei.' unverändert (SHA-256)', is_file($pfad) && $summe === hash_file('sha256', $pfad), $fehler);
}

// Das WebAssembly-Verzeichnis und das Modell werden über data-Attribute gefunden, das Bundle per Skript-Tag
pruefe('MediaPipe-Bibliothek im Template eingebunden', false !== strpos($editor, 'js/vendor/mediapipe/vision_bundle.js'), $fehler);
pruefe('WebAssembly-Verzeichnis im Template', false !== strpos($editor, 'data-wasm="bundles/contaometadaten/js/vendor/mediapipe/wasm"'), $fehler);
pruefe('Modell im Template', false !== strpos($editor, 'data-modell="bundles/contaometadaten/js/vendor/mediapipe/efficientdet_lite0_int8.tflite"'), $fehler);
pruefe('keine Reste von pico.js', !is_file($bundle.'/src/Resources/public/js/vendor/pico.js') && false === strpos($editor, 'pico'), $fehler);
pruefe('Herkunft und Lizenz der Fremddateien dokumentiert', is_file($bundle.'/src/Resources/public/js/vendor/LIESMICH.md'), $fehler);

// Die Reihenfolge zählt: MediaPipe und bildteile.js vor personen.js
$reihenfolge = array(strpos($editor, 'js/vendor/mediapipe/vision_bundle.js'), strpos($editor, 'js/bildteile.js'), strpos($editor, 'js/personen.js'));
pruefe('Skripte im Template in der richtigen Reihenfolge', false !== $reihenfolge[0] && false !== $reihenfolge[2] && $reihenfolge[0] < $reihenfolge[2] && $reihenfolge[1] < $reihenfolge[2], $fehler);

pruefe('Mindestgröße in PHP und Skript gleich', false !== strpos($skript, (string) Schachbulle\ContaoMetadatenBundle\Classes\Bildteil::MINDESTGROESSE), $fehler);

// 8. Dienste, die das Bundle zur Laufzeit holt
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

	foreach (array('contao.intl.locales', 'contao.csrf.token_manager', 'contao.routing.scope_matcher', 'contao.image.factory', 'contao.image.resizer', 'request_stack', 'router') as $dienst)
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
