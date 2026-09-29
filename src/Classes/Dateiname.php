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
 * Bereinigt Dateinamen nach wählbaren Regeln.
 *
 * Die Klasse rechnet nur mit Zeichenketten; umbenannt wird im Modul. Damit
 * lässt sich jede Regel ohne Contao und ohne Dateisystem prüfen.
 *
 * Reihenfolge der Regeln, unabhängig von der Reihenfolge der Auswahl:
 * 1. UMLAUTE:      ä → ae, ö → oe, ü → ue, ß → ss (auch Großbuchstaben)
 * 2. SONDERZEICHEN: Akzente entfernen (é → e), jedes Zeichen außer
 *                   Buchstabe, Ziffer, Leerzeichen, Punkt, Binde- und
 *                   Unterstrich durch „-“ ersetzen
 * 3. LEERZEICHEN:  Leerzeichen durch „-“ ersetzen
 * 4. KLEIN:        alles kleinschreiben, auch die Endung
 * Zum Schluss werden mehrfache Bindestriche zusammengefasst und Binde- und
 * Unterstriche am Anfang und Ende des Namens entfernt.
 *
 * Umlaute stehen vor den Sonderzeichen: Sonst würde „ä“ als Akzent-a zu
 * „a“ statt zu „ae“.
 */
final class Dateiname
{
	public const KLEIN = 'klein';
	public const UMLAUTE = 'umlaute';
	public const LEERZEICHEN = 'leerzeichen';
	public const SONDERZEICHEN = 'sonderzeichen';

	/**
	 * Alle Regeln in der Reihenfolge, in der sie angewendet werden
	 */
	public const REGELN = array(self::UMLAUTE, self::SONDERZEICHEN, self::LEERZEICHEN, self::KLEIN);

	/**
	 * Ersetzungen für Umlaute und ß
	 */
	private const UMLAUT_TABELLE = array(
		'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
		'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ẞ' => 'SS',
	);

	/**
	 * Wendet die gewählten Regeln auf einen Dateinamen an.
	 *
	 * Der Name wird an seinem letzten Punkt in Grundname und Endung geteilt;
	 * beide Teile werden bereinigt. Dateien ohne Endung und versteckte
	 * Dateien (Name beginnt mit einem Punkt) bleiben unverändert.
	 *
	 * @param string   $name   Dateiname ohne Pfad, zum Beispiel „Foto Siegerehrung.JPG“
	 * @param string[] $regeln Teilmenge von REGELN; unbekannte Werte werden ignoriert
	 *
	 * @return string Der bereinigte Name; der alte, wenn nichts zu tun ist oder
	 *                vom Grundnamen nichts übrig bliebe
	 */
	public static function bereinigen(string $name, array $regeln): string
	{
		$punkt = strrpos($name, '.');

		if (false === $punkt || 0 === $punkt || !$regeln)
		{
			return $name;
		}

		$grund = self::teil(substr($name, 0, $punkt), $regeln, true);
		$endung = self::teil(substr($name, $punkt + 1), $regeln, false);

		if ('' === $grund || '' === $endung)
		{
			return $name;
		}

		return $grund.'.'.$endung;
	}

	/**
	 * Prüft, ob zwei Dateinamen sich nur in der Groß- und Kleinschreibung unterscheiden.
	 *
	 * Wichtig für Windows- und macOS-Server: Dort gilt „Foto.JPG“ als dieselbe
	 * Datei wie „foto.jpg“. Ein solches Umbenennen ist deshalb kein Konflikt.
	 *
	 * @param string $a Erster Name oder Pfad
	 * @param string $b Zweiter Name oder Pfad
	 *
	 * @return bool true, wenn sie ohne Beachtung der Schreibweise gleich, mit ihr aber verschieden sind
	 */
	public static function nurSchreibweise(string $a, string $b): bool
	{
		return $a !== $b && mb_strtolower($a) === mb_strtolower($b);
	}

	/**
	 * Bereinigt Grundname oder Endung.
	 *
	 * @param string   $text   Der Teil des Namens
	 * @param string[] $regeln Die gewählten Regeln
	 * @param bool     $grund  true für den Grundnamen; bei der Endung werden auch Punkte ersetzt
	 *
	 * @return string Der bereinigte Teil, ohne Binde- oder Unterstriche am Rand
	 */
	private static function teil(string $text, array $regeln, bool $grund): string
	{
		if (\in_array(self::UMLAUTE, $regeln, true))
		{
			$text = strtr($text, self::UMLAUT_TABELLE);
		}

		if (\in_array(self::SONDERZEICHEN, $regeln, true))
		{
			$text = self::ohneAkzente($text);
			$erlaubt = $grund ? 'A-Za-z0-9 ._\-' : 'A-Za-z0-9';
			$text = (string) preg_replace('/[^'.$erlaubt.']+/u', '-', $text);
		}

		if (\in_array(self::LEERZEICHEN, $regeln, true))
		{
			$text = (string) preg_replace('/\s+/u', '-', $text);
		}

		if (\in_array(self::KLEIN, $regeln, true))
		{
			$text = mb_strtolower($text);
		}

		// Nur aufräumen, wenn eine der ersetzenden Regeln gewählt ist; die
		// Kleinschreibung allein soll den Namen sonst nicht verändern
		if (array_intersect(array(self::SONDERZEICHEN, self::LEERZEICHEN, self::UMLAUTE), $regeln))
		{
			$text = (string) preg_replace('/-{2,}/', '-', $text);
			$text = trim($text, '-_ ');
		}

		return $text;
	}

	/**
	 * Entfernt Akzente und andere diakritische Zeichen (é → e, ł bleibt ł).
	 *
	 * Mit der PHP-Erweiterung intl über die Normalform NFD: Buchstabe und
	 * Akzent werden getrennt, der Akzent fällt weg. Ohne intl bleibt der Text
	 * unverändert; die anschließende Ersetzung macht aus dem Zeichen dann
	 * einen Bindestrich.
	 *
	 * @param string $text Der Text
	 *
	 * @return string Der Text ohne kombinierende Zeichen
	 */
	private static function ohneAkzente(string $text): string
	{
		if (!class_exists(\Normalizer::class))
		{
			return $text;
		}

		$zerlegt = \Normalizer::normalize($text, \Normalizer::FORM_D);

		return false === $zerlegt ? $text : (string) preg_replace('/\p{Mn}+/u', '', $zerlegt);
	}
}
