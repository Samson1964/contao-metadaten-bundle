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
	 * Kleinste Ausdehnung eines von Hand gesetzten wichtigen Teils je Achse (Bruchteil).
	 *
	 * Bewusst klein: Auf einem Gruppenfoto nimmt ein Kopf nur wenige Prozent
	 * der Bildbreite ein. Der Editor im Browser verwendet denselben Wert.
	 */
	public const MINDESTGROESSE = 0.02;

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

		// Analysiert wird in der Größe, in der die Regel gemessen wurde
		$bild = self::vorschaubild($pfad, Bildanalyse::KANTE);

		if (null === $bild)
		{
			return null;
		}

		$teil = Bildanalyse::ausDatei($bild['pfad']);

		if (null === $teil)
		{
			return null;
		}

		return array('teil' => $teil, 'url' => $bild['url']);
	}

	/**
	 * Liefert ein Vorschaubild samt Vorschlag für den wichtigen Teil zum Nachbearbeiten von Hand.
	 *
	 * Anders als ermitteln() gibt es hier immer einen Vorschlag, solange sich
	 * das Bild überhaupt verkleinern lässt: Findet die Analyse nichts (oder
	 * fehlt GD), ist es die Bildmitte. Der Benutzer soll jedes Bild markieren
	 * können, auch ein strukturloses.
	 *
	 * @param string $pfad  Pfad der Datei relativ zum Projektverzeichnis, wie in tl_files.path
	 * @param int    $kante Kantenlänge des Vorschaubildes in Pixeln
	 *
	 * @return array{url: string, breite: int, hoehe: int, teil: array, schmal: array, breit: array, zweiSpieler: bool, geschaetzt: bool}|null
	 *         Adresse und Maße des Vorschaubildes; der gewählte Vorschlag
	 *         („teil“) und beide Varianten „ein Spieler“ („schmal“) und „zwei
	 *         Spieler“ („breit“), jeweils als array{x, y, width, height} in
	 *         Bruchteilen; ob die Analyse zwei Spieler annimmt und ob der
	 *         Vorschlag überhaupt aus der Analyse stammt. null, wenn sich kein
	 *         Vorschaubild erzeugen lässt
	 */
	public static function vorschlag(string $pfad, int $kante): ?array
	{
		$bild = self::vorschaubild($pfad, $kante);

		if (null === $bild)
		{
			return null;
		}

		$analyse = Bildanalyse::verfuegbar() ? Bildanalyse::vorschlaegeAusDatei($bild['pfad']) : null;
		$mitte = array('x' => 0.25, 'y' => 0.25, 'width' => 0.5, 'height' => 0.5);

		return array(
			'url'         => $bild['url'],
			'breite'      => $bild['breite'],
			'hoehe'       => $bild['hoehe'],
			'teil'        => $analyse['gewaehlt'] ?? $mitte,
			'schmal'      => $analyse['schmal'] ?? $mitte,
			'breit'       => $analyse['breit'] ?? array('x' => 0.05, 'y' => 0.02, 'width' => 0.93, 'height' => 0.88),
			'zweiSpieler' => (bool) ($analyse['zweiSpieler'] ?? false),
			'geschaetzt'  => null !== $analyse,
		);
	}

	/**
	 * Prüft einen von Hand gesetzten wichtigen Teil und bringt ihn in die gespeicherte Form.
	 *
	 * Die Werte kommen aus dem Formular des Bildteil-Editors, sind also
	 * Benutzereingaben: Sie müssen Zahlen sein und ein Rechteck innerhalb des
	 * Bildes beschreiben. Kleine Überstände durch Rundung im Browser werden
	 * abgeschnitten, alles andere wird abgewiesen.
	 *
	 * @param mixed $eingabe Erwartet ein Feld mit den Schlüsseln x, y, width, height
	 *                       als Bruchteile der Bildgröße (Zahlen oder Zahlentexte)
	 *
	 * @return array{x: float, y: float, width: float, height: float}|null
	 *         Das bereinigte Rechteck auf vier Nachkommastellen, oder null bei
	 *         fehlenden, nicht numerischen oder unsinnigen Werten
	 */
	public static function bereinigen($eingabe): ?array
	{
		if (!\is_array($eingabe))
		{
			return null;
		}

		$werte = array();

		foreach (array('x', 'y', 'width', 'height') as $schluessel)
		{
			$wert = $eingabe[$schluessel] ?? null;

			if (\is_string($wert))
			{
				$wert = str_replace(',', '.', trim($wert));
			}

			if (!is_numeric($wert))
			{
				return null;
			}

			$werte[$schluessel] = (float) $wert;
		}

		if ($werte['x'] < 0 || $werte['y'] < 0 || $werte['x'] >= 1 || $werte['y'] >= 1)
		{
			return null;
		}

		// Rundungsüberstand am rechten und unteren Rand abschneiden
		$werte['width'] = min($werte['width'], 1 - $werte['x']);
		$werte['height'] = min($werte['height'], 1 - $werte['y']);

		if ($werte['width'] < self::MINDESTGROESSE || $werte['height'] < self::MINDESTGROESSE)
		{
			return null;
		}

		return array(
			'x'      => round($werte['x'], 4),
			'y'      => round($werte['y'], 4),
			'width'  => round($werte['width'], 4),
			'height' => round($werte['height'], 4),
		);
	}

	/**
	 * Erzeugt über Contaos Bildfabrik ein in ein Quadrat eingepasstes Vorschaubild.
	 *
	 * Seiteneffekt: Das Vorschaubild wird, falls noch nicht vorhanden, im
	 * Bildcache von Contao angelegt.
	 *
	 * @param string $pfad  Pfad der Datei relativ zum Projektverzeichnis
	 * @param int    $kante Kantenlänge des Quadrats in Pixeln
	 *
	 * @return array{pfad: string, url: string, breite: int, hoehe: int}|null
	 *         Absoluter Pfad, relative Adresse und Maße des Vorschaubildes;
	 *         null, wenn die Datei fehlt, zu groß für den Speicher ist oder
	 *         sich nicht verarbeiten lässt
	 */
	public static function vorschaubild(string $pfad, int $kante): ?array
	{
		$container = System::getContainer();
		$projekt = (string) $container->getParameter('kernel.project_dir');
		$quelle = $projekt . '/' . $pfad;

		if (!is_file($quelle) || !self::speicherReicht($quelle))
		{
			return null;
		}

		try
		{
			$bild = $container->get('contao.image.factory')->create($quelle, array($kante, $kante, 'box'));

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

			// Achtung Contao 4.13 mit GD: Bilder über gdMaxImgWidth/-Height
			// (Vorgabe 3000 Pixel) verkleinert der Kern nicht und gibt das
			// Original zurück. Es wird dann unverkleinert angezeigt und
			// analysiert — der Speicher dafür ist oben bereits geprüft.
			$groesse = $bild->getDimensions()->getSize();

			return array(
				'pfad'   => $bild->getPath(),
				'url'    => $bild->getUrl($projekt),
				'breite' => (int) $groesse->getWidth(),
				'hoehe'  => (int) $groesse->getHeight(),
			);
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
