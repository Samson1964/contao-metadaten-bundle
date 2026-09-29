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
use Schachbulle\ContaoMetadatenBundle\Classes\Dateiname;

/**
 * Prüft die Bereinigung von Dateinamen.
 */
class DateinameTest extends TestCase
{
	/**
	 * @dataProvider beispiele
	 *
	 * @param string[] $regeln
	 */
	public function testBereinigen(string $alt, array $regeln, string $neu): void
	{
		$this->assertSame($neu, Dateiname::bereinigen($alt, $regeln));
	}

	/**
	 * @return array<string, array{0: string, 1: string[], 2: string}>
	 */
	public static function beispiele(): array
	{
		$alle = Dateiname::REGELN;

		return array(
			'alles'                       => array('Siegerehrung Jörg Müller (2).JPG', $alle, 'siegerehrung-joerg-mueller-2.jpg'),
			'nur klein, auch Endung'      => array('DSC_0042.JPG', array(Dateiname::KLEIN), 'dsc_0042.jpg'),
			'nur Umlaute'                 => array('Größe Übersicht.png', array(Dateiname::UMLAUTE), 'Groesse Uebersicht.png'),
			'nur Leerzeichen'             => array('Foto vom  Brett.jpg', array(Dateiname::LEERZEICHEN), 'Foto-vom-Brett.jpg'),
			'nur Sonderzeichen'           => array('Café & Co!.jpg', array(Dateiname::SONDERZEICHEN), 'Cafe - Co.jpg'),
			'Umlaute vor Sonderzeichen'   => array('Käse.jpg', array(Dateiname::SONDERZEICHEN, Dateiname::UMLAUTE), 'Kaese.jpg'),
			'ohne Umlautregel wird ä zu a' => array('Käse.jpg', array(Dateiname::SONDERZEICHEN), 'Kase.jpg'),
			'Großes ß'                    => array('STRAẞE.JPG', array(Dateiname::UMLAUTE, Dateiname::KLEIN), 'strasse.jpg'),
			'Punkte im Namen bleiben'     => array('turnier.2026.runde 1.jpg', $alle, 'turnier.2026.runde-1.jpg'),
			'Unterstrich bleibt'          => array('dssam_6019.jpg', $alle, 'dssam_6019.jpg'),
			'Ränder werden aufgeräumt'    => array(' -Foto- .jpg', $alle, 'foto.jpg'),
			'schon sauber'                => array('dssam-6019.jpg', $alle, 'dssam-6019.jpg'),
			'keine Regel'                 => array('Jörg Müller.JPG', array(), 'Jörg Müller.JPG'),
			'ohne Endung unverändert'     => array('LIESMICH', $alle, 'LIESMICH'),
			'versteckte Datei unverändert' => array('.htaccess', $alle, '.htaccess'),
			'nichts bleibt übrig'         => array('!!!.jpg', $alle, '!!!.jpg'),
			'Sonderzeichen in der Endung' => array('bild.jp#g', $alle, 'bild.jp-g'),
		);
	}

	public function testNurSchreibweise(): void
	{
		$this->assertTrue(Dateiname::nurSchreibweise('files/a/Foto.JPG', 'files/a/foto.jpg'));
		$this->assertFalse(Dateiname::nurSchreibweise('files/a/foto.jpg', 'files/a/foto.jpg'));
		$this->assertFalse(Dateiname::nurSchreibweise('files/a/Foto 1.jpg', 'files/a/foto-1.jpg'));
	}
}
