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
 * Schätzt den wichtigen Teil eines Fotos aus seiner Schärfeverteilung.
 *
 * Grundgedanke: Auf Reportagefotos mit geringer Schärfentiefe ist das Motiv
 * scharf, Hintergrund und Vordergrund sind unscharf. Unschärfe schluckt die
 * hohen Frequenzen; ein Laplace-Filter über die Helligkeit misst also recht
 * zuverlässig, wo die Schärfeebene liegt.
 *
 * Ablauf:
 * 1. Das Bild wird auf höchstens KANTE Pixel verkleinert und in ein Raster
 *    aus SPALTEN mal ZEILEN Zellen geteilt; je Zelle zählt der mittlere
 *    Betrag des Laplace-Filters.
 * 2. Ein Grundpegel (Perzentil GRUNDPEGEL aller Zellen) wird abgezogen,
 *    damit Rauschen und leicht unscharfe Flächen nicht mitzählen.
 * 3. Die Breite ergibt sich nur aus dem oberen Teil des Bildes (Anteil
 *    OBERER_TEIL): Dort liegen Kopf und Oberkörper. Das Brett im unteren
 *    Teil ist kontrastreich und liegt oft in der Schärfeebene; es würde das
 *    Rechteck sonst über die ganze Bildbreite ziehen.
 * 4. Links und rechts wird je RAND_QUER der Schärfemasse abgeschnitten,
 *    dann ZUGABE zugegeben und auf MINDESTBREITE aufgefüllt.
 * 5. Oben und unten gelten feste Kanten (OBEN, UNTEN): Der wichtige Teil
 *    reicht bei Spielerfotos fast immer vom Kopf bis zum Brett.
 *
 * Herkunft der Werte: eine Messung am 2026-09-29 an 206 Turnierfotos der
 * Deutschen Schnellschach-Amateurmeisterschaft, deren wichtigen Teil der
 * Benutzer von Hand markiert hatte (Kriterien: scharf abgebildet, Gesicht
 * mit Oberkörper, Brett dabei, wenn Blick oder Hand dorthin gehen).
 * Eingestellt an der einen Hälfte, geprüft an der anderen, erreichte die
 * Regel dort eine mittlere Überdeckung (Schnittfläche durch
 * Vereinigungsfläche) von 0,74. Zum Vergleich: das ganze Bild als Vorschlag
 * 0,61, die vorherige Kopferkennung 0,24. Das Messwerkzeug liegt als
 * tools/bildteilmessung.php bei.
 *
 * Grenzen: Die Regel kennt keine Motive, nur Schärfe. Bei durchgehend
 * scharfen Fotos (Gruppenbild, Bühne, Totale) liefert sie ein breites
 * Rechteck; bei anderen Bildarten als Spielerfotos passen die festen
 * Kanten oben und unten womöglich nicht.
 *
 * Die Klasse braucht die PHP-Erweiterung GD, aber weder Contao noch eine
 * Datenbank.
 */
final class Bildanalyse
{
	/**
	 * Längste Kante des Analysebildes in Pixeln.
	 *
	 * Die Werte unten sind bei dieser Größe gemessen. Kleinere Bilder
	 * verwischen die Schärfeunterschiede, größere kosten nur Rechenzeit.
	 */
	public const KANTE = 800;

	/**
	 * Rasterteilung für die Schärfekarte, unabhängig vom Seitenverhältnis
	 */
	private const SPALTEN = 48;
	private const ZEILEN = 27;

	/**
	 * Perzentil der Zellwerte, das als Grundpegel abgezogen wird
	 */
	private const GRUNDPEGEL = 0.7;

	/**
	 * Anteil der Bildhöhe von oben, aus dem die Breite bestimmt wird
	 */
	private const OBERER_TEIL = 0.6;

	/**
	 * Anteil der Schärfemasse, der links und rechts abgeschnitten wird
	 */
	private const RAND_QUER = 0.08;

	/**
	 * Zugabe links und rechts als Bruchteil der Bildbreite
	 */
	private const ZUGABE = 0.06;

	/**
	 * Mindestbreite des wichtigen Teils als Bruchteil der Bildbreite
	 */
	private const MINDESTBREITE = 0.55;

	/**
	 * Feste Ober- und Unterkante als Bruchteil der Bildhöhe
	 */
	private const OBEN = 0.02;
	private const UNTEN = 0.96;

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
	 * Die Datei sollte bereits klein sein, am besten ein Vorschaubild mit
	 * KANTE Pixeln aus Contaos Bildfabrik: GD entpackt jedes Bild vollständig
	 * in den Speicher, ein Foto mit 24 Megapixeln braucht dabei rund 100 MB.
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

		if ($breite < self::SPALTEN || $hoehe < self::ZEILEN)
		{
			return null;
		}

		$karte = self::schaerfekarte($bild, $breite, $hoehe);
		$spalten = self::spaltenmasse($karte);

		if (null === $spalten)
		{
			return null;
		}

		list($links, $rechts) = self::quergrenzen($spalten);

		return array(
			'x'      => round($links, 4),
			'y'      => self::OBEN,
			'width'  => round($rechts - $links, 4),
			'height' => round(self::UNTEN - self::OBEN, 4),
		);
	}

	/**
	 * Berechnet die Schärfekarte: je Rasterzelle den mittleren Laplace-Betrag der Helligkeit.
	 *
	 * Größere Bilder werden vorher auf KANTE Pixel verkleinert, damit die
	 * Werte zu den gemessenen Einstellungen passen und die Rechenzeit klein
	 * bleibt (rund 70 ms bei 800 mal 450 Pixeln).
	 *
	 * @param \GdImage|resource $bild   Das Bild
	 * @param int               $breite Breite in Pixeln
	 * @param int               $hoehe  Höhe in Pixeln
	 *
	 * @return array<int, array<int, float>> Zellwerte [zeile][spalte]
	 */
	private static function schaerfekarte($bild, int $breite, int $hoehe): array
	{
		$faktor = min(1, self::KANTE / max($breite, $hoehe));
		$b = max(self::SPALTEN, (int) round($breite * $faktor));
		$h = max(self::ZEILEN, (int) round($hoehe * $faktor));
		$arbeit = $bild;

		if ($b !== $breite || $h !== $hoehe)
		{
			$arbeit = imagecreatetruecolor($b, $h);

			// Transparenz weiß hinterlegen, sonst entstünde an ihrem Rand eine harte Kante
			imagefill($arbeit, 0, 0, imagecolorallocate($arbeit, 255, 255, 255));
			imagecopyresampled($arbeit, $bild, 0, 0, 0, 0, $b, $h, $breite, $hoehe);
		}

		$hell = array();

		for ($y = 0; $y < $h; ++$y)
		{
			$zeile = array();

			for ($x = 0; $x < $b; ++$x)
			{
				$rgb = imagecolorat($arbeit, $x, $y);
				$zeile[] = 0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF);
			}

			$hell[] = $zeile;
		}

		if ($arbeit !== $bild)
		{
			imagedestroy($arbeit);
		}

		$summe = array_fill(0, self::ZEILEN, array_fill(0, self::SPALTEN, 0.0));
		$anzahl = array_fill(0, self::ZEILEN, array_fill(0, self::SPALTEN, 0));

		for ($y = 1; $y < $h - 1; ++$y)
		{
			$zy = min(self::ZEILEN - 1, intdiv($y * self::ZEILEN, $h));

			for ($x = 1; $x < $b - 1; ++$x)
			{
				$zx = min(self::SPALTEN - 1, intdiv($x * self::SPALTEN, $b));
				$summe[$zy][$zx] += abs(4 * $hell[$y][$x] - $hell[$y - 1][$x] - $hell[$y + 1][$x] - $hell[$y][$x - 1] - $hell[$y][$x + 1]);
				++$anzahl[$zy][$zx];
			}
		}

		$karte = array();

		for ($zy = 0; $zy < self::ZEILEN; ++$zy)
		{
			for ($zx = 0; $zx < self::SPALTEN; ++$zx)
			{
				$karte[$zy][$zx] = $summe[$zy][$zx] / max(1, $anzahl[$zy][$zx]);
			}
		}

		return $karte;
	}

	/**
	 * Summiert die Schärfe über dem Grundpegel je Spalte, bevorzugt im oberen Bildteil.
	 *
	 * Liegt im oberen Bildteil gar keine Schärfe (etwa ein Motiv ganz unten
	 * im Bild), zählt das ganze Bild; sonst gäbe es keinen Vorschlag.
	 *
	 * @param array<int, array<int, float>> $karte Zellwerte [zeile][spalte]
	 *
	 * @return float[]|null Schärfemasse je Spalte, oder null, wenn nirgends etwas über dem Grundpegel liegt
	 */
	private static function spaltenmasse(array $karte): ?array
	{
		$alle = array_merge(...$karte);
		sort($alle);
		$grund = $alle[(int) floor(self::GRUNDPEGEL * (\count($alle) - 1))];

		foreach (array((int) ceil(self::OBERER_TEIL * self::ZEILEN), self::ZEILEN) as $zeilen)
		{
			$spalten = array_fill(0, self::SPALTEN, 0.0);

			for ($zy = 0; $zy < $zeilen; ++$zy)
			{
				foreach ($karte[$zy] as $zx => $wert)
				{
					$spalten[$zx] += max(0.0, $wert - $grund);
				}
			}

			if (array_sum($spalten) > 0.001)
			{
				return $spalten;
			}
		}

		// Eine praktisch einfarbige Fläche hat keine Schärfe über dem Grundpegel
		return null;
	}

	/**
	 * Bestimmt linke und rechte Kante aus der Schärfemasse je Spalte.
	 *
	 * @param float[] $spalten Schärfemasse je Spalte
	 *
	 * @return array{0: float, 1: float} Linke und rechte Kante als Bruchteile der Bildbreite
	 */
	private static function quergrenzen(array $spalten): array
	{
		$summe = array_sum($spalten);
		$lauf = 0.0;
		$von = 0;
		$bis = self::SPALTEN - 1;
		$gefunden = false;

		foreach ($spalten as $i => $wert)
		{
			$lauf += $wert;

			if (!$gefunden && $lauf >= $summe * self::RAND_QUER)
			{
				$von = $i;
				$gefunden = true;
			}

			if ($lauf >= $summe * (1 - self::RAND_QUER))
			{
				$bis = $i;
				break;
			}
		}

		$links = $von / self::SPALTEN - self::ZUGABE;
		$rechts = ($bis + 1) / self::SPALTEN + self::ZUGABE;

		// Auf Mindestbreite auffüllen, um die Mitte herum; am Bildrand verschieben statt kappen
		if ($rechts - $links < self::MINDESTBREITE)
		{
			$mitte = ($links + $rechts) / 2;
			$links = $mitte - self::MINDESTBREITE / 2;
			$rechts = $mitte + self::MINDESTBREITE / 2;
		}

		if ($links < 0)
		{
			$rechts -= $links;
			$links = 0.0;
		}

		if ($rechts > 1)
		{
			$links -= $rechts - 1;
			$rechts = 1.0;
		}

		return array(max(0.0, $links), min(1.0, $rechts));
	}
}
