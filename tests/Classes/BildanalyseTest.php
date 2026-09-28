<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoMetadatenBundle\Tests\Classes;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoMetadatenBundle\Classes\Bildanalyse;
use Schachbulle\ContaoMetadatenBundle\Classes\Bildteil;

/**
 * Prüft die Schätzung des wichtigen Bildteils an künstlichen Bildern.
 *
 * Echte Fotos gehören nicht ins Repository; die Bilder entstehen deshalb im
 * Test: eine ruhige graue Fläche mit einem unruhigen, hautfarbenen Fleck an
 * bekannter Stelle. Der geschätzte Teil muss diesen Fleck enthalten.
 */
class BildanalyseTest extends TestCase
{
	protected function setUp(): void
	{
		if (!Bildanalyse::verfuegbar())
		{
			$this->markTestSkipped('Die PHP-Erweiterung GD ist nicht geladen.');
		}
	}

	/**
	 * Erzeugt ein graues Bild mit einem auffälligen Fleck.
	 *
	 * @param int $breite Bildbreite
	 * @param int $hoehe  Bildhöhe
	 * @param int $fx     Linke Kante des Flecks
	 * @param int $fy     Obere Kante des Flecks
	 * @param int $fb     Breite des Flecks
	 * @param int $fh     Höhe des Flecks
	 *
	 * @return \GdImage|resource
	 */
	private function bildMitFleck(int $breite, int $hoehe, int $fx, int $fy, int $fb, int $fh)
	{
		$bild = imagecreatetruecolor($breite, $hoehe);
		imagefill($bild, 0, 0, imagecolorallocate($bild, 128, 128, 128));

		$haut = imagecolorallocate($bild, 224, 172, 138);
		$dunkel = imagecolorallocate($bild, 60, 40, 30);
		imagefilledrectangle($bild, $fx, $fy, $fx + $fb - 1, $fy + $fh - 1, $haut);

		// Schachbrettartige Störung, damit der Fleck auch Kanten hat
		$schritt = max(4, (int) ($fb / 8));

		for ($y = $fy; $y < $fy + $fh; $y += 2 * $schritt)
		{
			for ($x = $fx; $x < $fx + $fb; $x += 2 * $schritt)
			{
				imagefilledrectangle($bild, $x, $y, min($fx + $fb, $x + $schritt) - 1, min($fy + $fh, $y + $schritt) - 1, $dunkel);
			}
		}

		return $bild;
	}

	/**
	 * Prüft, ob der Mittelpunkt des Flecks im geschätzten Teil liegt.
	 */
	private function assertTeilEnthaelt(array $teil, float $x, float $y): void
	{
		$this->assertGreaterThanOrEqual($teil['x'], $x, 'Punkt liegt links vom wichtigen Teil');
		$this->assertLessThanOrEqual($teil['x'] + $teil['width'], $x, 'Punkt liegt rechts vom wichtigen Teil');
		$this->assertGreaterThanOrEqual($teil['y'], $y, 'Punkt liegt über dem wichtigen Teil');
		$this->assertLessThanOrEqual($teil['y'] + $teil['height'], $y, 'Punkt liegt unter dem wichtigen Teil');
	}

	public function testFleckObenRechtsWirdGefunden(): void
	{
		// Fleck von 70 bis 90 Prozent der Breite, 10 bis 35 Prozent der Höhe
		$teil = Bildanalyse::wichtigerTeil($this->bildMitFleck(1200, 800, 840, 80, 240, 200));

		$this->assertNotNull($teil);
		$this->assertTeilEnthaelt($teil, 0.8, 0.225);
		// Ein einzelner Fleck führt zu einem engen Fenster, nicht zum ganzen Bild
		$this->assertLessThanOrEqual(0.5, $teil['width']);
		$this->assertLessThanOrEqual(0.5, $teil['height']);
	}

	public function testFleckUntenLinksImHochformat(): void
	{
		$teil = Bildanalyse::wichtigerTeil($this->bildMitFleck(600, 900, 60, 600, 180, 180));

		$this->assertNotNull($teil);
		$this->assertTeilEnthaelt($teil, 0.25, 0.7667);
	}

	public function testErgebnisLiegtImmerImBild(): void
	{
		foreach (array(array(0, 0), array(1000, 0), array(0, 600), array(1000, 600)) as $ecke)
		{
			$teil = Bildanalyse::wichtigerTeil($this->bildMitFleck(1200, 800, $ecke[0], $ecke[1], 200, 200));

			$this->assertNotNull($teil);
			$this->assertGreaterThanOrEqual(0.0, $teil['x']);
			$this->assertGreaterThanOrEqual(0.0, $teil['y']);
			$this->assertLessThanOrEqual(1.0001, $teil['x'] + $teil['width']);
			$this->assertLessThanOrEqual(1.0001, $teil['y'] + $teil['height']);
			$this->assertGreaterThanOrEqual(0.29, $teil['width']);
			$this->assertGreaterThanOrEqual(0.29, $teil['height']);
		}
	}

	public function testEinfarbigesBildHatKeinenWichtigenTeil(): void
	{
		$bild = imagecreatetruecolor(800, 600);
		imagefill($bild, 0, 0, imagecolorallocate($bild, 128, 128, 128));

		$this->assertNull(Bildanalyse::wichtigerTeil($bild));
	}

	public function testWinzigesBildWirdNichtBewertet(): void
	{
		$this->assertNull(Bildanalyse::wichtigerTeil(imagecreatetruecolor(4, 4)));
	}

	public function testAusDateiLiestEineBilddatei(): void
	{
		$pfad = tempnam(sys_get_temp_dir(), 'meta').'.png';
		imagepng($this->bildMitFleck(400, 300, 40, 30, 120, 100), $pfad);

		try
		{
			$teil = Bildanalyse::ausDatei($pfad);
		}
		finally
		{
			@unlink($pfad);
			@unlink(substr($pfad, 0, -4));
		}

		$this->assertNotNull($teil);
		$this->assertTeilEnthaelt($teil, 0.25, 0.2667);
	}

	public function testAusDateiVerkraftetFehlendeUndKaputteDateien(): void
	{
		$this->assertNull(Bildanalyse::ausDatei(__DIR__.'/gibt-es-nicht.jpg'));
		// Eine PHP-Datei ist kein Bild
		$this->assertNull(Bildanalyse::ausDatei(__FILE__));
	}

	public function testBereinigenNimmtGueltigeRechteckeAn(): void
	{
		$this->assertSame(
			array('x' => 0.41, 'y' => 0.12, 'width' => 0.2, 'height' => 0.25),
			Bildteil::bereinigen(array('x' => '0.4100', 'y' => '0.1200', 'width' => '0.2000', 'height' => '0.2500'))
		);

		// Zahlen statt Text, Dezimalkomma und Rundung auf vier Stellen
		$this->assertSame(
			array('x' => 0.1235, 'y' => 0.0, 'width' => 0.5, 'height' => 1.0),
			Bildteil::bereinigen(array('x' => 0.12346, 'y' => 0, 'width' => '0,5', 'height' => 1))
		);
	}

	public function testBereinigenSchneidetRundungsueberstandAb(): void
	{
		// 0.7 + 0.3001 ragt durch Rundung im Browser über den rechten Rand
		$teil = Bildteil::bereinigen(array('x' => '0.7', 'y' => '0.9', 'width' => '0.3001', 'height' => '0.2'));

		$this->assertNotNull($teil);
		$this->assertEqualsWithDelta(0.3, $teil['width'], 0.00001);
		$this->assertEqualsWithDelta(0.1, $teil['height'], 0.00001);
	}

	/**
	 * @dataProvider unbrauchbareEingaben
	 *
	 * @param mixed $eingabe
	 */
	public function testBereinigenWeistUnbrauchbaresAb($eingabe): void
	{
		$this->assertNull(Bildteil::bereinigen($eingabe));
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function unbrauchbareEingaben(): array
	{
		return array(
			'kein Feld'            => array('0.1,0.1,0.5,0.5'),
			'null'                 => array(null),
			'Schlüssel fehlt'      => array(array('x' => '0.1', 'y' => '0.1', 'width' => '0.5')),
			'keine Zahl'           => array(array('x' => 'links', 'y' => '0.1', 'width' => '0.5', 'height' => '0.5')),
			'SQL im Wert'          => array(array('x' => '0.1; DROP TABLE tl_files', 'y' => '0.1', 'width' => '0.5', 'height' => '0.5')),
			'verschachteltes Feld' => array(array('x' => array('0.1'), 'y' => '0.1', 'width' => '0.5', 'height' => '0.5')),
			'negativ'              => array(array('x' => '-0.1', 'y' => '0.1', 'width' => '0.5', 'height' => '0.5')),
			'außerhalb des Bildes' => array(array('x' => '1.2', 'y' => '0.1', 'width' => '0.5', 'height' => '0.5')),
			'Breite null'          => array(array('x' => '0.1', 'y' => '0.1', 'width' => '0', 'height' => '0.5')),
			'zu klein'             => array(array('x' => '0.1', 'y' => '0.1', 'width' => '0.01', 'height' => '0.5')),
			'am Rand ohne Platz'   => array(array('x' => '0.995', 'y' => '0.1', 'width' => '0.5', 'height' => '0.5')),
		);
	}

	public function testKandidatenSindNurBilderOhneWichtigenTeil(): void
	{
		$dateien = array(
			array('id' => 1, 'path' => 'files/a.jpg', 'extension' => 'jpg', 'importantPartWidth' => '0', 'importantPartHeight' => '0'),
			array('id' => 2, 'path' => 'files/b.JPG', 'extension' => 'JPG', 'importantPartWidth' => 0.0, 'importantPartHeight' => 0.0),
			// hat bereits einen wichtigen Teil
			array('id' => 3, 'path' => 'files/c.png', 'extension' => 'png', 'importantPartWidth' => '0.5', 'importantPartHeight' => '0.4'),
			// nur eine Ausdehnung gesetzt gilt in Contao als „nicht markiert“
			array('id' => 4, 'path' => 'files/d.webp', 'extension' => 'webp', 'importantPartWidth' => '0.5', 'importantPartHeight' => '0'),
			// keine Pixelbilder
			array('id' => 5, 'path' => 'files/e.svg', 'extension' => 'svg', 'importantPartWidth' => '0', 'importantPartHeight' => '0'),
			array('id' => 6, 'path' => 'files/f.pdf', 'extension' => 'pdf', 'importantPartWidth' => '0', 'importantPartHeight' => '0'),
		);

		$this->assertSame(
			array(
				array('id' => 1, 'path' => 'files/a.jpg'),
				array('id' => 2, 'path' => 'files/b.JPG'),
				array('id' => 4, 'path' => 'files/d.webp'),
			),
			Bildteil::kandidaten($dateien)
		);
	}
}
