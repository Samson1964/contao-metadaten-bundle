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
use Schachbulle\ContaoMetadatenBundle\Classes\Auftrag;
use Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung;

/**
 * Prüft die datenbankfreie Kernlogik: Lesen, Prüfen, Ersetzen, Setzen, Vergleichen.
 */
class BearbeitungTest extends TestCase
{
	/**
	 * Beispiel-Metadaten, wie Contaos MetaWizard sie ablegt.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function beispiel(): array
	{
		return array(
			'de' => array(
				'title'   => 'Berliner Meisterschaft 2024',
				'alt'     => 'Spieler am Brett',
				'link'    => '',
				'caption' => 'Foto: Berliner Schachverband',
				'license' => '',
			),
			'en' => array(
				'title'   => 'Berlin Championship 2024',
				'alt'     => '',
				'link'    => '',
				'caption' => 'Photo: Berliner Schachverband',
				'license' => '',
			),
		);
	}

	/**
	 * Baut einen Ersetzen-Auftrag mit sinnvollen Vorgaben.
	 */
	private function ersetzen(string $suche, string $ersatz, array $felder = array('title', 'caption')): Auftrag
	{
		$auftrag = new Auftrag();
		$auftrag->modus = Auftrag::MODUS_ERSETZEN;
		$auftrag->felder = $felder;
		$auftrag->suche = $suche;
		$auftrag->ersatz = $ersatz;

		return $auftrag;
	}

	/**
	 * Baut einen Setzen-Auftrag mit sinnvollen Vorgaben.
	 */
	private function setzen(string $sprache, array $werte, bool $nurLeere): Auftrag
	{
		$auftrag = new Auftrag();
		$auftrag->modus = Auftrag::MODUS_SETZEN;
		$auftrag->sprache = $sprache;
		$auftrag->felder = array_keys($werte);
		$auftrag->werte = $werte;
		$auftrag->nurLeere = $nurLeere;

		return $auftrag;
	}

	public function testLesenEntpacktSerialisierteWerte(): void
	{
		$this->assertSame($this->beispiel(), Bearbeitung::lesen(serialize($this->beispiel())));
	}

	public function testLesenLiefertBeiUnbrauchbaremInhaltEinLeeresFeld(): void
	{
		$this->assertSame(array(), Bearbeitung::lesen(null));
		$this->assertSame(array(), Bearbeitung::lesen(''));
		$this->assertSame(array(), Bearbeitung::lesen('kein serialisierter Text'));
		$this->assertSame(array(), Bearbeitung::lesen(serialize('nur ein Text')));
		$this->assertSame(array('de' => array()), Bearbeitung::lesen(array('de' => array())));
	}

	public function testPruefenMeldetFehlendeAngaben(): void
	{
		$auftrag = new Auftrag();
		$auftrag->modus = 'egal';

		$this->assertSame(array('unbekannterModus', 'keineFelder'), Bearbeitung::pruefen($auftrag));

		$auftrag = $this->ersetzen('', 'x', array('title', 'foo'));
		$this->assertSame(array('unbekanntesFeld', 'keineSuche'), Bearbeitung::pruefen($auftrag));

		$auftrag = $this->setzen('', array('title' => 'x'), true);
		$this->assertSame(array('keineSprache'), Bearbeitung::pruefen($auftrag));
	}

	public function testPruefenErkenntUngueltigeRegulaereAusdruecke(): void
	{
		$auftrag = $this->ersetzen('(unvollständig', '');
		$auftrag->regex = true;

		$this->assertSame(array('ungueltigesMuster'), Bearbeitung::pruefen($auftrag));

		$auftrag->suche = '(voll)ständig';
		$this->assertSame(array(), Bearbeitung::pruefen($auftrag));
	}

	public function testErsetzenTauschtTextNurInDenGewaehltenFeldern(): void
	{
		$neu = Bearbeitung::anwenden($this->beispiel(), $this->ersetzen('Berliner Schachverband', 'BSV', array('caption')));

		$this->assertSame('Foto: BSV', $neu['de']['caption']);
		$this->assertSame('Photo: BSV', $neu['en']['caption']);
		// Titel war nicht angewählt und bleibt, obwohl „Berliner“ darin steht
		$this->assertSame('Berliner Meisterschaft 2024', $neu['de']['title']);
	}

	public function testErsetzenLaesstSichAufEineSpracheBeschraenken(): void
	{
		$auftrag = $this->ersetzen('2024', '2025', array('title'));
		$auftrag->sprache = 'en';

		$neu = Bearbeitung::anwenden($this->beispiel(), $auftrag);

		$this->assertSame('Berliner Meisterschaft 2024', $neu['de']['title']);
		$this->assertSame('Berlin Championship 2025', $neu['en']['title']);
	}

	public function testErsetzenBeachtetGrossUndKleinschreibungAufWunschNicht(): void
	{
		$auftrag = $this->ersetzen('BERLINER', 'Hamburger', array('title'));
		$this->assertSame('Berliner Meisterschaft 2024', Bearbeitung::anwenden($this->beispiel(), $auftrag)['de']['title']);

		$auftrag->gross = false;
		$this->assertSame('Hamburger Meisterschaft 2024', Bearbeitung::anwenden($this->beispiel(), $auftrag)['de']['title']);
	}

	public function testErsetzenOhneGrossschreibungKenntUmlaute(): void
	{
		$meta = array('de' => array('title' => 'Übersicht', 'alt' => '', 'link' => '', 'caption' => '', 'license' => ''));
		$auftrag = $this->ersetzen('übersicht', 'Überblick', array('title'));
		$auftrag->gross = false;

		$this->assertSame('Überblick', Bearbeitung::anwenden($meta, $auftrag)['de']['title']);
	}

	public function testErsetzenOhneGrossschreibungNimmtErsatztextWoertlich(): void
	{
		$meta = array('de' => array('title' => 'Preis', 'alt' => '', 'link' => '', 'caption' => '', 'license' => ''));
		$auftrag = $this->ersetzen('preis', 'Preis: 5 $ und \\1', array('title'));
		$auftrag->gross = false;

		$this->assertSame('Preis: 5 $ und \\1', Bearbeitung::anwenden($meta, $auftrag)['de']['title']);
	}

	public function testErsetzenMitRegulaeremAusdruckUndRueckverweis(): void
	{
		$auftrag = $this->ersetzen('(\d{4})$', 'Saison $1', array('title'));
		$auftrag->regex = true;

		$neu = Bearbeitung::anwenden($this->beispiel(), $auftrag);

		$this->assertSame('Berliner Meisterschaft Saison 2024', $neu['de']['title']);
		$this->assertSame('Berlin Championship Saison 2024', $neu['en']['title']);
	}

	public function testErsetzenFasstLeereFelderNichtAn(): void
	{
		// ^$ würde auf einen leeren Wert passen; leere Felder sollen trotzdem leer bleiben
		$auftrag = $this->ersetzen('^$', 'gefüllt', array('alt'));
		$auftrag->regex = true;

		$neu = Bearbeitung::anwenden($this->beispiel(), $auftrag);

		$this->assertSame('', $neu['en']['alt']);
		$this->assertSame(array(), Bearbeitung::unterschiede($this->beispiel(), $neu));
	}

	public function testErsetzenKannEinenWertVollstaendigEntfernen(): void
	{
		$auftrag = $this->ersetzen('Spieler am Brett', '', array('alt'));
		$neu = Bearbeitung::anwenden($this->beispiel(), $auftrag);

		$this->assertSame('', $neu['de']['alt']);
		$this->assertSame(
			array(array('sprache' => 'de', 'feld' => 'alt', 'alt' => 'Spieler am Brett', 'neu' => '')),
			Bearbeitung::unterschiede($this->beispiel(), $neu)
		);
	}

	public function testSetzenFuelltNurLeereFelder(): void
	{
		$auftrag = $this->setzen('de', array('alt' => 'Neuer Alternativtext', 'license' => 'CC BY 4.0'), true);
		$neu = Bearbeitung::anwenden($this->beispiel(), $auftrag);

		// alt war gefüllt und bleibt; license war leer und wird gesetzt
		$this->assertSame('Spieler am Brett', $neu['de']['alt']);
		$this->assertSame('CC BY 4.0', $neu['de']['license']);
		// die andere Sprache bleibt unangetastet
		$this->assertSame($this->beispiel()['en'], $neu['en']);
	}

	public function testSetzenUeberschreibtAufWunschAlles(): void
	{
		$auftrag = $this->setzen('de', array('alt' => 'Neuer Alternativtext', 'caption' => ''), false);
		$neu = Bearbeitung::anwenden($this->beispiel(), $auftrag);

		$this->assertSame('Neuer Alternativtext', $neu['de']['alt']);
		$this->assertSame('', $neu['de']['caption']);
		$this->assertSame('Berliner Meisterschaft 2024', $neu['de']['title']);
	}

	public function testSetzenLegtEineFehlendeSpracheVollstaendigAn(): void
	{
		$auftrag = $this->setzen('fr', array('caption' => 'Photo : BSV'), true);
		$neu = Bearbeitung::anwenden($this->beispiel(), $auftrag);

		$this->assertSame(
			array('title' => '', 'alt' => '', 'link' => '', 'caption' => 'Photo : BSV', 'license' => ''),
			$neu['fr']
		);
		// Sprachen sind wie im MetaWizard sortiert
		$this->assertSame(array('de', 'en', 'fr'), array_keys($neu));
	}

	public function testSetzenInLeereDateiOhneWertHinterlaesstNichts(): void
	{
		$auftrag = $this->setzen('de', array('caption' => ''), false);
		$neu = Bearbeitung::anwenden(array(), $auftrag);

		$this->assertSame(array(), $neu);
		$this->assertSame(array(), Bearbeitung::unterschiede(array(), $neu));
	}

	public function testSetzenOhneSpracheWirftAusnahme(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		Bearbeitung::anwenden($this->beispiel(), $this->setzen('', array('title' => 'x'), false));
	}

	public function testBereinigenEntferntLeereSprachenUndSortiert(): void
	{
		$meta = array(
			'en' => array('title' => '', 'alt' => '', 'caption' => '0'),
			'de' => array('title' => 'Titel'),
		);

		$this->assertSame(array('de' => array('title' => 'Titel')), Bearbeitung::bereinigen($meta));
	}

	public function testUnterschiedeIgnorierenReineBereinigung(): void
	{
		$alt = array(
			'en' => array('title' => '', 'alt' => '', 'link' => '', 'caption' => '', 'license' => ''),
			'de' => array('title' => 'Titel'),
		);

		$this->assertSame(array(), Bearbeitung::unterschiede($alt, Bearbeitung::bereinigen($alt)));
	}

	public function testUnterschiedeListenJedeGeaenderteZelle(): void
	{
		$alt = $this->beispiel();
		$neu = $alt;
		$neu['de']['title'] = 'Neu';
		unset($neu['en']);

		$liste = Bearbeitung::unterschiede($alt, $neu);

		$this->assertCount(3, $liste);
		$this->assertSame(array('sprache' => 'de', 'feld' => 'title', 'alt' => 'Berliner Meisterschaft 2024', 'neu' => 'Neu'), $liste[0]);
		$this->assertSame('en', $liste[1]['sprache']);
		$this->assertSame('', $liste[1]['neu']);
	}

	public function testErsetzeTextMeldetNichtAnwendbaresMuster(): void
	{
		$auftrag = $this->ersetzen('a', 'b', array('title'));
		$auftrag->regex = true;

		$this->expectException(\RuntimeException::class);

		// Ungültiges UTF-8 lässt preg_replace mit dem u-Schalter scheitern
		Bearbeitung::ersetzeText("\xff\xfe a", $auftrag);
	}
}
