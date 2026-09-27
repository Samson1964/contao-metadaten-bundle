<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoMetadatenBundle\Classes;

use Contao\Image\DeferredImageInterface;
use Contao\System;

/**
 * Bindeglied zwischen der Dateiverwaltung und der Bildanalyse.
 *
 * Sucht unter den ausgewählten Dateien die Bilder ohne wichtigen Teil und
 * lässt für ein einzelnes Bild den wichtigen Teil schätzen. Analysiert wird
 * nie das Original, sondern ein Vorschaubild aus Contaos Bildfabrik:
 *
 * - Die Bildfabrik dreht Fotos nach ihren EXIF-Angaben, genau wie später bei
 *   der Ausgabe — die Koordinaten passen also zu dem Bild, das Contao sieht.
 * - Das Vorschaubild landet im Bildcache (assets/images) und wird bei der
 *   nächsten Vorschau nicht neu berechnet.
 * - Mit Imagick als Bildbibliothek braucht selbst ein sehr großes Foto kaum
 *   PHP-Speicher; GD lädt danach nur noch das kleine Vorschaubild.
 *
 * Die Dienste contao.image.factory und contao.image.resizer sind in Contao
 * 4.13 und 5.x öffentlich.
 */
final class Bildteil
{
	/**
	 * Dateiendungen, die als Bild gelten und die GD lesen kann.
	 *
	 * SVG fehlt bewusst: Vektorgrafiken haben keine Pixel zum Auswerten.
	 */
	public const ENDUNGEN = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp');

	/**
	 * Kantenlänge des Vorschaubildes in Pixeln (Modus „box“, also eingepasst)
	 */
	private const VORSCHAU = 240;

	/**
	 * Wählt aus den Dateien die Bilder aus, die noch keinen wichtigen Teil haben.
	 *
	 * „Kein wichtiger Teil“ heißt in Contao: Breite oder Höhe des Teils ist 0
	 * (so prüft es auch ImageFactory::getImportantPart()).
	 *
	 * @param array<int, array<string, mixed>> $dateien Zeilen aus tl_files mit id, path,
	 *                                                  extension, importantPartWidth, importantPartHeight
	 *
	 * @return array<int, array{id: int, path: string}> Die Kandidaten in der Reihenfolge der Eingabe
	 */
	public static function kandidaten(array $dateien): array
	{
		$liste = array();

		foreach ($dateien as $datei)
		{
			$endung = strtolower((string) ($datei['extension'] ?? ''));

			if (!\in_array($endung, self::ENDUNGEN, true))
			{
				continue;
			}

			if ((float) ($datei['importantPartWidth'] ?? 0) > 0 && (float) ($datei['importantPartHeight'] ?? 0) > 0)
			{
				continue;
			}

			$liste[] = array('id' => (int) $datei['id'], 'path' => (string) $datei['path']);
		}

		return $liste;
	}

	/**
	 * Schätzt den wichtigen Teil eines Bildes aus der Dateiverwaltung.
	 *
	 * Seiteneffekt: Das Vorschaubild wird, falls noch nicht vorhanden, im
	 * Bildcache von Contao angelegt.
	 *
	 * @param string $pfad Pfad der Datei relativ zum Projektverzeichnis, wie in tl_files.path
	 *
	 * @return array{teil: array{x: float, y: float, width: float, height: float}, url: string}|null
	 *         Der wichtige Teil in Bruchteilen der Bildgröße samt Adresse des
	 *         Vorschaubildes; null, wenn die Datei fehlt, zu groß für den
	 *         verfügbaren Speicher ist, sich nicht lesen lässt oder keine
	 *         Struktur hat
	 */
	public static function ermitteln(string $pfad): ?array
	{
		if (!Bildanalyse::verfuegbar())
		{
			return null;
		}

		$container = System::getContainer();
		$projekt = (string) $container->getParameter('kernel.project_dir');
		$quelle = $projekt . '/' . $pfad;

		if (!is_file($quelle) || !self::speicherReicht($quelle))
		{
			return null;
		}

		try
		{
			$bild = $container->get('contao.image.factory')->create($quelle, array(self::VORSCHAU, self::VORSCHAU, 'box'));

			// Die Bildfabrik liefert für noch nicht berechnete Größen nur ein
			// Versprechen; hier wird das Bild sofort gebraucht
			if ($bild instanceof DeferredImageInterface)
			{
				$bild = $container->get('contao.image.resizer')->resizeDeferredImage($bild);
			}

			if (null === $bild)
			{
				return null;
			}

			$teil = Bildanalyse::ausDatei($bild->getPath());

			if (null === $teil)
			{
				return null;
			}

			return array('teil' => $teil, 'url' => $bild->getUrl($projekt));
		}
		catch (\Throwable $e)
		{
			// Beschädigte oder exotische Bilddateien sollen den Lauf über
			// hunderte Dateien nicht abbrechen
			return null;
		}
	}

	/**
	 * Schätzt, ob der PHP-Speicher zum Verkleinern des Bildes reicht.
	 *
	 * Nur GD entpackt das Original in den PHP-Speicher (rund 5 Byte je
	 * Pixel). Ist Imagick oder Gmagick geladen, nimmt Contao diese
	 * Bibliothek, und die Frage stellt sich nicht. Ein erschöpfter Speicher
	 * ließe sich nicht abfangen und bräche die ganze Seite ab — deshalb wird
	 * ein zu großes Bild lieber übersprungen.
	 *
	 * @param string $quelle Absoluter Pfad der Bilddatei
	 *
	 * @return bool false, wenn das Bild voraussichtlich nicht in den Speicher passt
	 */
	private static function speicherReicht(string $quelle): bool
	{
		if (\extension_loaded('imagick') || \extension_loaded('gmagick'))
		{
			return true;
		}

		$grenze = self::speichergrenze();

		if ($grenze <= 0)
		{
			return true;
		}

		$groesse = @getimagesize($quelle);

		if (false === $groesse)
		{
			// Unbekanntes Format: Die Bildfabrik soll selbst entscheiden
			return true;
		}

		$bedarf = (int) ($groesse[0] * $groesse[1] * 5 * 1.2);

		return $bedarf < $grenze - memory_get_usage(true);
	}

	/**
	 * Liest memory_limit aus der PHP-Konfiguration in Bytes.
	 *
	 * @return int Die Grenze in Bytes; 0 oder negativ bedeutet „unbegrenzt“
	 */
	private static function speichergrenze(): int
	{
		$wert = trim((string) \ini_get('memory_limit'));

		if ('' === $wert || '-1' === $wert)
		{
			return -1;
		}

		$zahl = (int) $wert;

		switch (strtolower(substr($wert, -1)))
		{
			case 'g':
				return $zahl * 1024 * 1024 * 1024;

			case 'm':
				return $zahl * 1024 * 1024;

			case 'k':
				return $zahl * 1024;
		}

		return $zahl;
	}
}
