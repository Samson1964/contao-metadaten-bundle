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
 * Beschreibt einen Bearbeitungsauftrag für die Metadaten von Dateien.
 *
 * Der Auftrag ist eine reine Datenhülle ohne Verhalten. Er wird aus einem
 * Datensatz der Tabelle tl_metadaten gebaut (ausDatensatz()), und die Klasse
 * Bearbeitung wendet ihn auf das Metadaten-Feld einer einzelnen Datei an.
 * Dadurch lässt sich die eigentliche Logik ohne Contao und ohne Datenbank
 * prüfen.
 *
 * Zwei Betriebsarten:
 *
 * - „ersetzen“: In den gewählten Feldern wird ein Suchtext durch einen
 *   Ersatztext ausgetauscht, wahlweise per regulärem Ausdruck.
 * - „setzen“: Die gewählten Felder bekommen feste Werte, wahlweise nur dort,
 *   wo sie bislang leer sind.
 */
final class Auftrag
{
	public const MODUS_ERSETZEN = 'ersetzen';
	public const MODUS_SETZEN = 'setzen';

	/**
	 * Betriebsart, eine der MODUS_-Konstanten
	 *
	 * @var string
	 */
	public $modus = self::MODUS_ERSETZEN;

	/**
	 * Zu bearbeitende Felder, Teilmenge von Bearbeitung::FELDER
	 *
	 * @var string[]
	 */
	public $felder = array();

	/**
	 * Sprachkürzel, auf das sich der Auftrag beschränkt.
	 *
	 * Leer bedeutet beim Ersetzen „alle in der Datei vorhandenen Sprachen“.
	 * Beim Setzen ist eine Sprache Pflicht, weil sonst nicht klar wäre,
	 * unter welchem Schlüssel die Werte abgelegt werden sollen.
	 *
	 * @var string
	 */
	public $sprache = '';

	/**
	 * Suchtext bzw. regulärer Ausdruck ohne Begrenzer (nur „ersetzen“)
	 *
	 * @var string
	 */
	public $suche = '';

	/**
	 * Ersatztext; bei regulären Ausdrücken sind Rückverweise wie $1 erlaubt
	 *
	 * @var string
	 */
	public $ersatz = '';

	/**
	 * Suchtext als regulären Ausdruck (PCRE) auswerten
	 *
	 * @var bool
	 */
	public $regex = false;

	/**
	 * Groß- und Kleinschreibung beim Suchen beachten
	 *
	 * @var bool
	 */
	public $gross = true;

	/**
	 * Neue Werte je Feld (nur „setzen“), Schlüssel wie in Bearbeitung::FELDER
	 *
	 * @var array<string, string>
	 */
	public $werte = array();

	/**
	 * Beim Setzen nur Felder füllen, die bislang leer sind
	 *
	 * @var bool
	 */
	public $nurLeere = false;

	/**
	 * Baut einen Auftrag aus einem Datensatz der Tabelle tl_metadaten.
	 *
	 * Erwartet die Spalten so, wie sie aus der Datenbank kommen: Checkboxen
	 * als '1' oder '', das Feld felder serialisiert (oder bereits als Feld),
	 * die Werte in den Spalten wert_<feld>. Fehlende Spalten führen zu den
	 * Vorgaben, nicht zu Fehlern — die Prüfung übernimmt Bearbeitung::pruefen().
	 *
	 * @param array<string, mixed> $row Der Datensatz
	 *
	 * @return self Der ungeprüfte Auftrag
	 */
	public static function ausDatensatz(array $row): self
	{
		$auftrag = new self();
		$auftrag->modus = (string) ($row['modus'] ?? self::MODUS_ERSETZEN);
		$auftrag->sprache = (string) ($row['sprache'] ?? '');
		$auftrag->suche = (string) ($row['suche'] ?? '');
		$auftrag->ersatz = (string) ($row['ersatz'] ?? '');
		$auftrag->regex = !empty($row['regex']);
		$auftrag->gross = !empty($row['gross']);
		$auftrag->nurLeere = !empty($row['nurLeere']);

		$felder = $row['felder'] ?? array();

		if (\is_string($felder))
		{
			$entpackt = '' === $felder ? array() : @unserialize($felder, array('allowed_classes' => false));
			$felder = \is_array($entpackt) ? $entpackt : array();
		}

		$auftrag->felder = array_values(array_map('strval', array_filter((array) $felder, 'is_scalar')));

		foreach (Bearbeitung::FELDER as $feld)
		{
			$auftrag->werte[$feld] = (string) ($row['wert_' . $feld] ?? '');
		}

		return $auftrag;
	}
}
