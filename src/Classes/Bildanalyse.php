<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoMetadatenBundle\Classes;

/**
 * Schätzt den wichtigen Teil eines Bildes aus seinem Inhalt.
 *
 * Das Verfahren ist eine Heuristik ohne fremde Bibliotheken, angelehnt an
 * „smartcrop“: Das Bild wird stark verkleinert, jedes Pixel bekommt eine
 * Wertung aus drei Anteilen —
 *
 * - Detail: Kantenstärke der Helligkeit (Laplace-Filter). Gesichter,
 *   Figuren und Schrift haben viele Kanten, Wände und Himmel kaum welche.
 * - Hautton: Nähe der Farbe zu einem mittleren Hautton. Hebt Gesichter und
 *   Hände hervor, das Wichtigste auf Personenfotos.
 * - Sättigung: kräftige Farben ziehen den Blick an.
 *
 * Anschließend wird das Fenster gesucht, in dem die Wertung am dichtesten
 * liegt. Geprüft werden Fenster von 30 bis 80 Prozent der Bildbreite und
 * -höhe an jeder Position; ein Integralbild macht die Summe jedes Fensters
 * zu vier Speicherzugriffen. Verglichen wird Summe / Fläche^0,85: Liegt die
 * Wertung an einer Stelle gehäuft (ein Gesicht), gewinnt ein enges Fenster
 * darum; ist sie gleichmäßig verteilt (Turniersaal, Gruppenfoto), gewinnt
 * ein großes.
 *
 * Grenzen: Das Verfahren erkennt keine Gesichter, es schätzt nur, wo im Bild
 * am meisten los ist. Deshalb wird es nur für Bilder verwendet, die noch gar
 * keinen wichtigen Teil haben, und die Vorschau zeigt jedes Ergebnis vor dem
 * Speichern.
 *
 * Die Klasse braucht die PHP-Erweiterung GD, aber weder Contao noch eine
 * Datenbank.
 */
final class Bildanalyse
{
	/**
	 * Längste Kante des Analysebildes in Pixeln.
	 *
	 * Klein genug für wenige Millisekunden Rechenzeit, groß genug, damit ein
	 * Gesicht auf einem Gruppenfoto noch mehrere Pixel einnimmt.
	 */
	private const KANTE = 96;

	/**
	 * Geprüfte Fenstergrößen je Achse als Bruchteil der Bildgröße.
	 *
	 * Die kleinste Größe ist zugleich die Mindestgröße des wichtigen Teils:
	 * Kleinere Rechtecke führten beim Zuschneiden zu extremen Ausschnitten.
	 */
	private const GROESSEN = array(0.3, 0.4, 0.5, 0.6, 0.7, 0.8);

	/**
	 * Exponent, mit dem die Fensterfläche in den Vergleich eingeht.
	 *
	 * 1 hieße reine Dichte (das kleinste Fenster gewänne fast immer), 0 hieße
	 * reine Summe (das größte gewänne immer). 0,85 hat sich an Personenfotos
	 * bewährt: eng um ein Gesicht, weit bei gleichmäßig verteiltem Inhalt.
	 */
	private const EXPONENT = 0.85;

	/**
	 * Vielfaches der mittleren Wertung, das als Grundpegel abgezogen wird.
	 *
	 * Dämpft unruhige Hintergründe (Pflanzen, Publikum, Muster), deren
	 * Wertung sich über das ganze Bild verteilt.
	 */
	private const GRUNDPEGEL = 1.0;

	/**
	 * Mittlerer Hautton als normierter RGB-Vektor (aus smartcrop).
	 */
	private const HAUTTON = array(0.78, 0.57, 0.44);

	/**
	 * Stellt fest, ob die nötigen GD-Funktionen vorhanden sind.
	 *
	 * @return bool true, wenn Bilder geladen und analysiert werden können
	 */
	public static function verfuegbar(): bool
	{
		return \function_exists('imagecreatefromstring')
			&& \function_exists('imagecreatetruecolor')
			&& \function_exists('imagecopyresampled');
	}

	/**
	 * Schätzt den wichtigen Teil einer Bilddatei.
	 *
	 * Die Datei sollte bereits klein sein (etwa ein Vorschaubild aus Contaos
	 * Bildfabrik): GD entpackt jedes Bild vollständig in den Speicher, ein
	 * Foto mit 24 Megapixeln braucht dabei rund 100 MB.
	 *
	 * @param string $pfad Absoluter Pfad einer Bilddatei in einem Format, das GD lesen kann
	 *
	 * @return array{x: float, y: float, width: float, height: float}|null
	 *         Bruchteile der Bildgröße (0 bis 1), oder null, wenn die Datei
	 *         nicht lesbar ist oder das Bild keine Struktur hat
	 */
	public static function ausDatei(string $pfad): ?array
	{
		if (!self::verfuegbar() || !is_file($pfad) || !is_readable($pfad))
		{
			return null;
		}

		$inhalt = @file_get_contents($pfad);

		if (false === $inhalt || '' === $inhalt)
		{
			return null;
		}

		$bild = @imagecreatefromstring($inhalt);

		if (false === $bild)
		{
			return null;
		}

		$ergebnis = self::wichtigerTeil($bild);
		imagedestroy($bild);

		return $ergebnis;
	}

	/**
	 * Schätzt den wichtigen Teil eines bereits geladenen GD-Bildes.
	 *
	 * @param \GdImage|resource $bild Das Bild; es wird nicht verändert
	 *
	 * @return array{x: float, y: float, width: float, height: float}|null
	 *         Bruchteile der Bildgröße (0 bis 1), oder null, wenn das Bild zu
	 *         klein ist oder keine Struktur hat (etwa eine einfarbige Fläche)
	 */
	public static function wichtigerTeil($bild): ?array
	{
		$breite = imagesx($bild);
		$hoehe = imagesy($bild);

		if ($breite < 8 || $hoehe < 8)
		{
			return null;
		}

		$faktor = min(1, self::KANTE / max($breite, $hoehe));
		$b = max(8, (int) round($breite * $faktor));
		$h = max(8, (int) round($hoehe * $faktor));

		$klein = imagecreatetruecolor($b, $h);

		// Transparente Flächen (PNG, WebP) weiß hinterlegen, sonst gälten sie
		// als schwarz und erzeugten an ihrem Rand eine harte Kante
		imagefill($klein, 0, 0, imagecolorallocate($klein, 255, 255, 255));
		imagecopyresampled($klein, $bild, 0, 0, 0, 0, $b, $h, $breite, $hoehe);

		$wertung = self::wertung($klein, $b, $h);
		imagedestroy($klein);

		$gesamt = 0.0;

		foreach ($wertung as $zeile)
		{
			$gesamt += array_sum($zeile);
		}

		// Ohne nennenswerte Wertung gibt es nichts hervorzuheben
		if ($gesamt < 0.002 * $b * $h)
		{
			return null;
		}

		$integral = self::integralbild($wertung, $b, $h, self::GRUNDPEGEL * $gesamt / ($b * $h));

		if ($integral[$h][$b] <= 0)
		{
			return null;
		}

		$bestes = null;
		$bestwert = -1.0;

		foreach (self::GROESSEN as $anteilB)
		{
			$fb = max(2, (int) round($anteilB * $b));

			foreach (self::GROESSEN as $anteilH)
			{
				$fh = max(2, (int) round($anteilH * $h));
				$teiler = ($fb * $fh) ** self::EXPONENT;

				for ($y = 0; $y + $fh <= $h; ++$y)
				{
					for ($x = 0; $x + $fb <= $b; ++$x)
					{
						$summe = $integral[$y + $fh][$x + $fb] - $integral[$y][$x + $fb]
							- $integral[$y + $fh][$x] + $integral[$y][$x];

						$wert = $summe / $teiler;

						if ($wert > $bestwert)
						{
							$bestwert = $wert;
							$bestes = array($x, $y, $fb, $fh);
						}
					}
				}
			}
		}

		if (null === $bestes)
		{
			return null;
		}

		$bestes = self::zentrieren($bestes, $integral, $b, $h);

		return array(
			'x'      => round($bestes[0] / $b, 4),
			'y'      => round($bestes[1] / $h, 4),
			'width'  => round($bestes[2] / $b, 4),
			'height' => round($bestes[3] / $h, 4),
		);
	}

	/**
	 * Berechnet die Wertung jedes Pixels des Analysebildes.
	 *
	 * Die Randpixel bleiben ohne Detailwertung, weil der Laplace-Filter dort
	 * keine vollständige Nachbarschaft hat.
	 *
	 * @param \GdImage|resource $bild Das verkleinerte Bild
	 * @param int               $b    Breite in Pixeln
	 * @param int               $h    Höhe in Pixeln
	 *
	 * @return array<int, array<int, float>> Wertung [y][x], jeweils ab 0
	 */
	private static function wertung($bild, int $b, int $h): array
	{
		$hell = array();
		$farbe = array();

		for ($y = 0; $y < $h; ++$y)
		{
			for ($x = 0; $x < $b; ++$x)
			{
				$rgb = imagecolorat($bild, $x, $y);
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$bl = $rgb & 0xFF;

				$hell[$y][$x] = (0.2126 * $r + 0.7152 * $g + 0.0722 * $bl) / 255;
				$farbe[$y][$x] = array($r, $g, $bl);
			}
		}

		$wertung = array();

		for ($y = 0; $y < $h; ++$y)
		{
			for ($x = 0; $x < $b; ++$x)
			{
				$detail = 0.0;

				if ($x > 0 && $y > 0 && $x < $b - 1 && $y < $h - 1)
				{
					$detail = abs(
						4 * $hell[$y][$x]
						- $hell[$y - 1][$x] - $hell[$y + 1][$x]
						- $hell[$y][$x - 1] - $hell[$y][$x + 1]
					);
				}

				list($r, $g, $bl) = $farbe[$y][$x];
				$l = $hell[$y][$x];

				$wertung[$y][$x] = min(1.0, $detail) * 1.0
					+ self::hautton($r, $g, $bl, $l) * 1.2
					+ self::saettigung($r, $g, $bl, $l) * 0.15;
			}
		}

		return $wertung;
	}

	/**
	 * Baut das Integralbild der um den Grundpegel verminderten Wertung.
	 *
	 * Im Integralbild steht an jeder Stelle die Summe aller Werte links
	 * oberhalb davon. Die Summe eines beliebigen Rechtecks ergibt sich damit
	 * aus vier Einträgen, unabhängig von seiner Größe.
	 *
	 * @param array<int, array<int, float>> $wertung Wertung [y][x]
	 * @param int                           $b       Breite in Pixeln
	 * @param int                           $h       Höhe in Pixeln
	 * @param float                         $pegel   Abzuziehender Grundpegel; Werte darunter zählen als 0
	 *
	 * @return array<int, array<int, float>> Integralbild mit (h+1) mal (b+1) Einträgen
	 */
	private static function integralbild(array $wertung, int $b, int $h, float $pegel): array
	{
		$integral = array_fill(0, $h + 1, array_fill(0, $b + 1, 0.0));

		for ($y = 0; $y < $h; ++$y)
		{
			for ($x = 0; $x < $b; ++$x)
			{
				$wert = max(0.0, $wertung[$y][$x] - $pegel);

				$integral[$y + 1][$x + 1] = $wert
					+ $integral[$y][$x + 1]
					+ $integral[$y + 1][$x]
					- $integral[$y][$x];
			}
		}

		return $integral;
	}

	/**
	 * Verschiebt das gefundene Fenster so, dass der Schwerpunkt seiner Wertung in der Mitte liegt.
	 *
	 * Die Fenstersuche maximiert nur die Summe; ein Gesicht, das kleiner ist
	 * als das Fenster, kann deshalb an dessen Rand sitzen. Für das Zuschneiden
	 * ist ein mittig sitzendes Motiv besser. Die Größe bleibt unverändert, das
	 * Fenster bleibt im Bild.
	 *
	 * @param array{0: int, 1: int, 2: int, 3: int} $fenster  x, y, Breite, Höhe in Pixeln des Analysebildes
	 * @param array<int, array<int, float>>         $integral Integralbild der Wertung
	 * @param int                                   $b        Breite des Analysebildes
	 * @param int                                   $h        Höhe des Analysebildes
	 *
	 * @return array{0: int, 1: int, 2: int, 3: int} Das verschobene Fenster
	 */
	private static function zentrieren(array $fenster, array $integral, int $b, int $h): array
	{
		list($x, $y, $fb, $fh) = $fenster;
		$summe = 0.0;
		$sx = 0.0;
		$sy = 0.0;

		// Spalten- und Zeilensummen des Fensters aus dem Integralbild
		for ($i = $x; $i < $x + $fb; ++$i)
		{
			$spalte = $integral[$y + $fh][$i + 1] - $integral[$y][$i + 1] - $integral[$y + $fh][$i] + $integral[$y][$i];
			$sx += $spalte * ($i + 0.5);
			$summe += $spalte;
		}

		for ($j = $y; $j < $y + $fh; ++$j)
		{
			$zeile = $integral[$j + 1][$x + $fb] - $integral[$j][$x + $fb] - $integral[$j + 1][$x] + $integral[$j][$x];
			$sy += $zeile * ($j + 0.5);
		}

		if ($summe <= 0)
		{
			return $fenster;
		}

		$nx = (int) round($sx / $summe - $fb / 2);
		$ny = (int) round($sy / $summe - $fh / 2);

		return array(
			max(0, min($b - $fb, $nx)),
			max(0, min($h - $fh, $ny)),
			$fb,
			$fh,
		);
	}

	/**
	 * Bewertet, wie sehr eine Farbe einem Hautton gleicht.
	 *
	 * Verglichen wird die Richtung des Farbvektors, nicht seine Länge, damit
	 * helle und dunkle Haut gleichermaßen zählen. Sehr dunkle und sehr helle
	 * Pixel bleiben außen vor, weil ihre Farbrichtung unzuverlässig ist.
	 *
	 * @param int   $r Rot 0 bis 255
	 * @param int   $g Grün 0 bis 255
	 * @param int   $b Blau 0 bis 255
	 * @param float $l Helligkeit 0 bis 1
	 *
	 * @return float Wertung 0 bis 1
	 */
	private static function hautton(int $r, int $g, int $b, float $l): float
	{
		$laenge = sqrt($r * $r + $g * $g + $b * $b);

		if ($laenge < 1 || $l < 0.2 || $l > 0.95)
		{
			return 0.0;
		}

		$dr = $r / $laenge - self::HAUTTON[0];
		$dg = $g / $laenge - self::HAUTTON[1];
		$db = $b / $laenge - self::HAUTTON[2];
		$naehe = 1 - sqrt($dr * $dr + $dg * $dg + $db * $db);

		// Erst oberhalb der Schwelle zählt die Farbe als Hautton
		return $naehe > 0.8 ? ($naehe - 0.8) / 0.2 : 0.0;
	}

	/**
	 * Bewertet die Farbsättigung eines Pixels.
	 *
	 * @param int   $r Rot 0 bis 255
	 * @param int   $g Grün 0 bis 255
	 * @param int   $b Blau 0 bis 255
	 * @param float $l Helligkeit 0 bis 1
	 *
	 * @return float Wertung 0 bis 1; nur kräftige Farben mittlerer Helligkeit zählen
	 */
	private static function saettigung(int $r, int $g, int $b, float $l): float
	{
		$max = max($r, $g, $b);
		$min = min($r, $g, $b);

		if ($max === $min || $l < 0.05 || $l > 0.9)
		{
			return 0.0;
		}

		$summe = $max + $min;
		$s = $summe > 255 ? ($max - $min) / (510 - $summe) : ($max - $min) / $summe;

		return $s > 0.4 ? ($s - 0.4) / 0.6 : 0.0;
	}
}
