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
 * Der Auftrag ist eine reine Datenhülle ohne Verhalten. Das Backend-Modul
 * füllt ihn aus den Formulareingaben, die Klasse Bearbeitung wendet ihn auf
 * das Metadaten-Feld einer einzelnen Datei an. Dadurch lässt sich die
 * eigentliche Logik ohne Contao und ohne Datenbank prüfen.
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
}
