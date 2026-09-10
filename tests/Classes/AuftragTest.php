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
 * Prüft die Abbildung eines tl_metadaten-Datensatzes auf einen Auftrag.
 */
class AuftragTest extends TestCase
{
	public function testAusDatensatzUebernimmtAlleSpalten(): void
	{
		$row = array(
			'modus'        => 'setzen',
			'sprache'      => 'de',
			'suche'        => 'alt',
			'ersatz'       => 'neu',
			'regex'        => '1',
			'gross'        => '',
			'nurLeere'     => '1',
			'felder'       => serialize(array('caption', 'title')),
			'wert_title'   => 'Titel',
			'wert_alt'     => '',
			'wert_link'    => '{{link_url::1}}',
			'wert_caption' => 'Foto: BSV',
			'wert_license' => '',
		);

		$auftrag = Auftrag::ausDatensatz($row);

		$this->assertSame(Auftrag::MODUS_SETZEN, $auftrag->modus);
		$this->assertSame('de', $auftrag->sprache);
		$this->assertSame('alt', $auftrag->suche);
		$this->assertSame('neu', $auftrag->ersatz);
		$this->assertTrue($auftrag->regex);
		$this->assertFalse($auftrag->gross);
		$this->assertTrue($auftrag->nurLeere);
		$this->assertSame(array('caption', 'title'), $auftrag->felder);
		// Alle fünf Felder sind belegt, auch die leeren
		$this->assertSame(Bearbeitung::FELDER, array_keys($auftrag->werte));
		$this->assertSame('', $auftrag->werte['alt']);
		$this->assertSame('Foto: BSV', $auftrag->werte['caption']);
		$this->assertSame('{{link_url::1}}', $auftrag->werte['link']);
		$this->assertSame(array(), Bearbeitung::pruefen($auftrag));
	}

	public function testAusDatensatzKommtMitLeeremDatensatzAus(): void
	{
		$auftrag = Auftrag::ausDatensatz(array());

		$this->assertSame(Auftrag::MODUS_ERSETZEN, $auftrag->modus);
		$this->assertSame(array(), $auftrag->felder);
		$this->assertFalse($auftrag->gross);
		$this->assertSame(Bearbeitung::FELDER, array_keys($auftrag->werte));
		// Ohne Felder und Suchtext fällt der Auftrag durch die Prüfung
		$this->assertSame(array('keineFelder', 'keineSuche'), Bearbeitung::pruefen($auftrag));
	}

	public function testAusDatensatzVerkraftetKaputtesFelderFeld(): void
	{
		$this->assertSame(array(), Auftrag::ausDatensatz(array('felder' => 'kein serialisierter Text'))->felder);
		$this->assertSame(array('alt'), Auftrag::ausDatensatz(array('felder' => array('alt')))->felder);
		$this->assertSame(array('alt'), Auftrag::ausDatensatz(array('felder' => array('alt', array('verschachtelt'))))->felder);
	}
}
