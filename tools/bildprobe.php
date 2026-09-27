<?php

declare(strict_types=1);

/*
 * Praxisprobe für das automatische Markieren des wichtigen Bildteils.
 *
 * Aufruf:  php tools/bildprobe.php <pfad-zur-contao-installation> <bild> [<bild> ...]
 *
 * Anders als der Prüfstand braucht diese Probe eine laufende Datenbank: Sie
 * startet den Contao-Kern der Testinstallation, legt die übergebenen Bilder
 * in einem eigenen Ordner unter files/ ab, meldet sie in tl_files an und
 * lässt das Modul genau den Weg gehen, den es im Backend geht — Kandidaten
 * suchen, Vorschaubild über die Bildfabrik holen, analysieren, mit Version
 * speichern.
 *
 * Die Probe räumt hinter sich auf: Ordner, Zeilen in tl_files und tl_version
 * verschwinden wieder. Zurück bleiben nur die Vorschaubilder im Bildcache
 * (assets/images), die Contao selbst verwaltet. Das Bundle muss in der
 * Installation nicht installiert sein, die Tabelle tl_metadaten wird nicht
 * gebraucht.
 */

use Contao\BackendUser;
use Contao\Database;
use Contao\Dbafs;
use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Schachbulle\ContaoMetadatenBundle\Classes\Bildteil;
use Schachbulle\ContaoMetadatenBundle\Modules\Metadaten;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

$bundle = str_replace('\\', '/', dirname(__DIR__));
$root = rtrim(str_replace('\\', '/', $argv[1] ?? ''), '/');
$bilder = array_slice($argv, 2);

if (!is_dir($root) || !$bilder)
{
	fwrite(STDERR, "Aufruf: php tools/bildprobe.php <installation> <bild> [<bild> ...]\n");
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

// Contao-Meldungen unter PHP 8.4 (Deprecations aus Symfony und Twig) sollen
// die Ausgabe nicht fluten
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$kernel = ContaoKernel::fromRequest($root, Request::create('/contao'));

// Im Produktivmodus steckt der Kern in der HTTP-Cache-Hülle
if (method_exists($kernel, 'getKernel'))
{
	$kernel = $kernel->getKernel();
}

$kernel->boot();
$container = $kernel->getContainer();
$container->get('contao.framework')->initialize();

echo "Contao-Installation: $root\n";
echo 'PHP: '.PHP_VERSION.', Bildbibliothek: '.(extension_loaded('imagick') ? 'Imagick' : (extension_loaded('gmagick') ? 'Gmagick' : 'GD'))."\n\n";

$db = Database::getInstance();
$ids = array();

// Reste früherer, abgebrochener Läufe beseitigen
foreach (glob($root.'/files/metadaten-bildprobe-*', GLOB_ONLYDIR) ?: array() as $alt)
{
	$altPfad = 'files/'.basename($alt);
	$altIds = $db->prepare('SELECT id FROM tl_files WHERE path LIKE ?')->execute($altPfad.'%')->fetchEach('id');

	if ($altIds)
	{
		$db->execute("DELETE FROM tl_version WHERE fromTable='tl_files' AND pid IN (".implode(',', array_map('intval', $altIds)).')');
	}

	Dbafs::deleteResource($altPfad);
	array_map('unlink', glob($alt.'/*') ?: array());
	@rmdir($alt);
	echo "Rest eines früheren Laufs entfernt: $altPfad\n";
}

$ordner = 'files/metadaten-bildprobe-'.bin2hex(random_bytes(4));

// Ein erschöpfter Speicher oder ein anderer fataler Fehler überspringt den
// finally-Block; die Meldung soll trotzdem sichtbar sein
ini_set('display_errors', '1');
register_shutdown_function(
	static function (): void
	{
		$letzter = error_get_last();

		if (null !== $letzter && \in_array($letzter['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE), true))
		{
			echo "\nABBRUCH: ".$letzter['message'].' in '.$letzter['file'].':'.$letzter['line']."\n";
			echo "Der Probeordner wird beim nächsten Lauf entfernt.\n";
		}
	}
);

try
{
	// 1. Bilder ablegen und in der Dateiverwaltung anmelden
	echo "Vorbereitung\n";
	mkdir($root.'/'.$ordner, 0777, true);

	foreach ($bilder as $bild)
	{
		$ziel = $ordner.'/'.preg_replace('/[^a-z0-9._-]+/i', '-', basename($bild));
		copy($bild, $root.'/'.$ziel);
		$modell = Dbafs::addResource($ziel);
		$ids[(int) $modell->id] = $ziel;
	}

	pruefe(\count($ids).' Bilder in tl_files angemeldet', \count($ids) === \count($bilder), $fehler);

	// 2. Angemeldeten Backend-Benutzer nachbilden — Versions::create() fragt
	//    ihn über security.helper ab, genau wie im Backend
	$admin = $db->execute("SELECT username FROM tl_user WHERE admin='1' ORDER BY id LIMIT 1");
	$user = BackendUser::getInstance();
	$user->findBy('username', $admin->username);
	$container->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'contao_backend', $user->getRoles()));
	pruefe('Backend-Benutzer „'.$admin->username.'“ angemeldet', (bool) $user->isAdmin, $fehler);

	// Versions::create() baut die Bearbeitungsadresse aus der laufenden
	// Anfrage; auf der Kommandozeile gibt es keine, also wird eine nachgebildet
	$anfrage = Request::create('/contao?do=metadaten&key=vorschau&id=1');
	$anfrage->attributes->set('_scope', 'backend');
	$container->get('request_stack')->push($anfrage);

	// 3. Kandidaten suchen, wie das Modul es tut
	echo "\nKandidaten\n";
	$refDateien = new ReflectionMethod(Metadaten::class, 'dateien');
	$refDateien->setAccessible(true);
	$modul = new Metadaten();
	$zeilen = $refDateien->invoke($modul, array('unterordner' => '1', 'endungen' => ''), $ordner, null);
	$kandidaten = Bildteil::kandidaten($zeilen);
	pruefe('Abfrage liefert alle Bilder des Ordners', \count($zeilen) === \count($ids), $fehler);
	pruefe('alle sind Kandidaten (noch kein wichtiger Teil)', \count($kandidaten) === \count($ids), $fehler);

	// 4. Einzelnes Bild: Vorschaubild aus der Bildfabrik und Analyse
	echo "\nBildfabrik und Analyse\n";

	foreach ($kandidaten as $kandidat)
	{
		$start = microtime(true);
		$fund = Bildteil::ermitteln($kandidat['path']);
		$ms = (int) round((microtime(true) - $start) * 1000);

		pruefe(basename($kandidat['path']).' ausgewertet ('.$ms.' ms)', null !== $fund, $fehler);

		if (null !== $fund)
		{
			printf("         Teil x=%.4f y=%.4f b=%.4f h=%.4f, Vorschaubild %s\n", $fund['teil']['x'], $fund['teil']['y'], $fund['teil']['width'], $fund['teil']['height'], $fund['url']);
			pruefe('         Vorschaubild liegt auf der Platte', is_file($root.'/'.rawurldecode($fund['url'])) || is_file($root.'/public/'.rawurldecode($fund['url'])) || is_file($root.'/web/'.rawurldecode($fund['url'])), $fehler);
		}
	}

	// 5. Ausführen: schreibt wichtigen Teil und Version
	echo "\nAusführen\n";
	$refAusfuehren = new ReflectionMethod(Metadaten::class, 'ausfuehren');
	$refAusfuehren->setAccessible(true);
	$ergebnis = $refAusfuehren->invoke($modul, array(), $kandidaten);
	pruefe('alle Kandidaten markiert', $ergebnis['markiert'] === \count($ids), $fehler);

	$nachher = $db->execute('SELECT id, importantPartX, importantPartY, importantPartWidth, importantPartHeight FROM tl_files WHERE id IN ('.implode(',', array_keys($ids)).')');

	while ($nachher->next())
	{
		$gesetzt = (float) $nachher->importantPartWidth > 0 && (float) $nachher->importantPartHeight > 0
			&& (float) $nachher->importantPartX + (float) $nachher->importantPartWidth <= 1.0001
			&& (float) $nachher->importantPartY + (float) $nachher->importantPartHeight <= 1.0001;

		pruefe(sprintf('tl_files %d: x=%s y=%s b=%s h=%s', $nachher->id, $nachher->importantPartX, $nachher->importantPartY, $nachher->importantPartWidth, $nachher->importantPartHeight), $gesetzt, $fehler);
	}

	$versionen = $db->execute("SELECT COUNT(*) AS anzahl FROM tl_version WHERE fromTable='tl_files' AND pid IN (".implode(',', array_keys($ids)).')');
	pruefe('Versionen angelegt ('.$versionen->anzahl.', je Datei Ausgangsstand und Änderung)', (int) $versionen->anzahl >= 2 * \count($ids), $fehler);

	// 6. Zweiter Durchgang: Markierte Bilder dürfen keine Kandidaten mehr sein
	echo "\nZweiter Durchgang\n";
	$zeilen = $refDateien->invoke($modul, array('unterordner' => '1', 'endungen' => ''), $ordner, null);
	pruefe('keine Kandidaten mehr', array() === Bildteil::kandidaten($zeilen), $fehler);

	// 7. Contao selbst muss den wichtigen Teil jetzt liefern. Die Registry
	//    hält noch das Model aus der Anmeldung der Datei, also mit dem alten
	//    Stand; im Backend beginnt jede Anfrage mit einer leeren Registry.
	Contao\Model\Registry::getInstance()->reset();
	$teil = $container->get('contao.image.factory')->create($root.'/'.reset($ids))->getImportantPart();
	pruefe('Contaos Bildfabrik liefert den gespeicherten wichtigen Teil', $teil->getWidth() < 1 && $teil->getHeight() < 1, $fehler);
}
catch (Throwable $e)
{
	$fehler[] = get_class($e).': '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine();
}
finally
{
	// Aufräumen, auch nach einem Fehler
	echo "\nAufräumen\n";

	if ($ids)
	{
		$db->execute("DELETE FROM tl_version WHERE fromTable='tl_files' AND pid IN (".implode(',', array_keys($ids)).')');
	}

	try
	{
		Dbafs::deleteResource($ordner);
	}
	catch (Throwable $e)
	{
		$fehler[] = 'Aufräumen in tl_files: '.$e->getMessage();
	}

	foreach (glob($root.'/'.$ordner.'/*') ?: array() as $datei)
	{
		unlink($datei);
	}

	@rmdir($root.'/'.$ordner);

	$rest = $db->prepare('SELECT COUNT(*) AS anzahl FROM tl_files WHERE path LIKE ?')->execute($ordner.'%');
	pruefe('Ordner und Zeilen entfernt', !is_dir($root.'/'.$ordner) && 0 === (int) $rest->anzahl, $fehler);
}

echo "\n";

if ($fehler)
{
	echo 'FEHLGESCHLAGEN ('.\count($fehler)."):\n";

	foreach ($fehler as $f)
	{
		echo "  - $f\n";
	}

	exit(1);
}

echo "Alles in Ordnung.\n";
exit(0);
