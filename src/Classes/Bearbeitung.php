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
 * Wendet einen Auftrag auf das Metadaten-Feld einer einzelnen Datei an.
 *
 * Contao speichert die Metadaten in tl_files.meta als serialisiertes Feld
 * nach dem Muster
 *
 *     array('de' => array('title' => …, 'alt' => …, 'link' => …, 'caption' => …, 'license' => …))
 *
 * Diese Klasse kennt nur dieses Feld — weder Datenbank noch Contao-Klassen.
 * So bleibt sie mit PHPUnit ohne Installation prüfbar, und das Backend-Modul
 * beschränkt sich auf Formular, Dateiauswahl und Speichern.
 */
final class Bearbeitung
{
	/**
	 * Alle Felder, die Contaos MetaWizard in 4.13 und 5.x kennt
	 */
	public const FELDER = array('title', 'alt', 'link', 'caption', 'license');

	/**
	 * Begrenzer für selbst gebaute reguläre Ausdrücke.
	 *
	 * Ein Steuerzeichen, damit kein Zeichen aus der Benutzereingabe
	 * maskiert werden muss — jedes gängige Zeichen wie „/“ oder „~“
	 * könnte im Suchmuster selbst vorkommen.
	 */
	private const BEGRENZER = "\x01";

	/**
	 * Wandelt den Rohwert aus tl_files.meta in ein Feld um.
	 *
	 * Nimmt sowohl den serialisierten Text aus der Datenbank als auch ein
	 * bereits entpacktes Feld entgegen. Alles Unbrauchbare — leer, kaputt,
	 * fremde Typen — wird zu einem leeren Feld, damit der Aufrufer nicht
	 * unterscheiden muss.
	 *
	 * @param mixed $roh Inhalt der Spalte meta oder ein Feld
	 *
	 * @return array<string, array<string, string>> Metadaten je Sprache
	 */
	public static function lesen($roh): array
	{
		if (\is_array($roh))
		{
			return $roh;
		}

		if (!\is_string($roh) || '' === $roh)
		{
			return array();
		}

		// Ohne Objektfreigabe, weil in dieser Spalte nie Objekte stehen
		// und fremde Klassen beim Entpacken nichts zu suchen haben
		$wert = @unserialize($roh, array('allowed_classes' => false));

		return \is_array($wert) ? $wert : array();
	}

	/**
	 * Prüft einen Auftrag auf formale Fehler, bevor er auf Dateien losgelassen wird.
	 *
	 * Geprüft wird alles, was ohne Kenntnis der Dateien entscheidbar ist:
	 * Betriebsart, Feldauswahl, Pflichtangaben und die Gültigkeit eines
	 * regulären Ausdrucks. Die Rückgabe sind Schlüssel, keine fertigen
	 * Texte — die Übersetzung übernimmt das Modul über die Sprachdatei.
	 *
	 * @param Auftrag $auftrag Der zu prüfende Auftrag
	 *
	 * @return string[] Fehlerschlüssel; leer, wenn der Auftrag brauchbar ist.
	 *                  Mögliche Werte: unbekannterModus, keineFelder,
	 *                  unbekanntesFeld, keineSuche, keineSprache, ungueltigesMuster
	 */
	public static function pruefen(Auftrag $auftrag): array
	{
		$fehler = array();

		if (!\in_array($auftrag->modus, array(Auftrag::MODUS_ERSETZEN, Auftrag::MODUS_SETZEN), true))
		{
			$fehler[] = 'unbekannterModus';
		}

		if (!$auftrag->felder)
		{
			$fehler[] = 'keineFelder';
		}
		elseif (array_diff($auftrag->felder, self::FELDER))
		{
			$fehler[] = 'unbekanntesFeld';
		}

		if (Auftrag::MODUS_ERSETZEN === $auftrag->modus)
		{
			if ('' === $auftrag->suche)
			{
				$fehler[] = 'keineSuche';
			}
			elseif (($auftrag->regex || !$auftrag->gross) && !self::musterGueltig($auftrag))
			{
				$fehler[] = 'ungueltigesMuster';
			}
		}

		if (Auftrag::MODUS_SETZEN === $auftrag->modus && '' === $auftrag->sprache)
		{
			$fehler[] = 'keineSprache';
		}

		return $fehler;
	}

	/**
	 * Wendet den Auftrag auf die Metadaten einer Datei an und liefert das Ergebnis.
	 *
	 * Das übergebene Feld bleibt unverändert; zurück kommt eine bereinigte
	 * Kopie (leere Sprachen entfernt, Sprachen sortiert — so, wie Contaos
	 * MetaWizard selbst speichert). Ob sich inhaltlich etwas geändert hat,
	 * entscheidet der Aufrufer mit unterschiede(), nicht über einen
	 * Feldvergleich: Allein die Bereinigung könnte sonst als Änderung gelten.
	 *
	 * @param array   $meta    Metadaten je Sprache, wie von lesen() geliefert
	 * @param Auftrag $auftrag Der anzuwendende Auftrag; muss pruefen() bestanden haben
	 *
	 * @return array<string, array<string, string>> Die neuen Metadaten
	 *
	 * @throws \InvalidArgumentException wenn beim Setzen keine Sprache angegeben ist
	 * @throws \RuntimeException         wenn ein regulärer Ausdruck auf den Text nicht anwendbar ist
	 */
	public static function anwenden(array $meta, Auftrag $auftrag): array
	{
		if (Auftrag::MODUS_SETZEN === $auftrag->modus)
		{
			return self::setzen($meta, $auftrag);
		}

		foreach ($meta as $sprache => $eintrag)
		{
			if (!\is_array($eintrag))
			{
				continue;
			}

			if ('' !== $auftrag->sprache && (string) $sprache !== $auftrag->sprache)
			{
				continue;
			}

			foreach ($auftrag->felder as $feld)
			{
				// Leere Felder überspringen: Ein Ersetzen in „nichts“ kann
				// nichts treffen, und ein Muster wie ^$ soll nicht plötzlich
				// Inhalte in unbeschriftete Dateien schreiben
				if (!isset($eintrag[$feld]) || !\is_string($eintrag[$feld]) || '' === $eintrag[$feld])
				{
					continue;
				}

				$meta[$sprache][$feld] = self::ersetzeText($eintrag[$feld], $auftrag);
			}
		}

		return self::bereinigen($meta);
	}

	/**
	 * Ermittelt die Unterschiede zwischen zwei Metadaten-Feldern auf Feldebene.
	 *
	 * Verglichen wird je Sprache und Feld der Textwert; fehlende Einträge
	 * gelten als leerer Text. Dadurch zählt eine entfernte leere Sprache
	 * nicht als Änderung, ein gelöschter Wert aber sehr wohl.
	 *
	 * @param array $alt Metadaten vor der Bearbeitung
	 * @param array $neu Metadaten nach der Bearbeitung
	 *
	 * @return array<int, array{sprache: string, feld: string, alt: string, neu: string}>
	 *         Eine Zeile je geändertem Wert; leer, wenn nichts abweicht
	 */
	public static function unterschiede(array $alt, array $neu): array
	{
		$liste = array();
		$sprachen = array_unique(array_merge(array_keys($alt), array_keys($neu)));

		foreach ($sprachen as $sprache)
		{
			$altEintrag = isset($alt[$sprache]) && \is_array($alt[$sprache]) ? $alt[$sprache] : array();
			$neuEintrag = isset($neu[$sprache]) && \is_array($neu[$sprache]) ? $neu[$sprache] : array();
			$felder = array_unique(array_merge(self::FELDER, array_keys($altEintrag), array_keys($neuEintrag)));

			foreach ($felder as $feld)
			{
				$a = self::alsText($altEintrag[$feld] ?? '');
				$n = self::alsText($neuEintrag[$feld] ?? '');

				if ($a !== $n)
				{
					$liste[] = array('sprache' => (string) $sprache, 'feld' => (string) $feld, 'alt' => $a, 'neu' => $n);
				}
			}
		}

		return $liste;
	}

	/**
	 * Bereinigt ein Metadaten-Feld so, wie Contaos MetaWizard es beim Speichern tut.
	 *
	 * Sprachen ohne einen einzigen nicht leeren Wert fliegen heraus (Contao
	 * #7569), die verbleibenden werden nach Sprachkürzel sortiert (Contao
	 * #3818). Wie im Kern gilt „0“ dabei als leer.
	 *
	 * @param array $meta Metadaten je Sprache
	 *
	 * @return array<string, array<string, string>> Das bereinigte Feld
	 */
	public static function bereinigen(array $meta): array
	{
		$meta = array_filter(
			$meta,
			static function ($eintrag): bool
			{
				if (!\is_array($eintrag))
				{
					return false;
				}

				foreach ($eintrag as $wert)
				{
					if (!empty($wert))
					{
						return true;
					}
				}

				return false;
			}
		);

		ksort($meta);

		return $meta;
	}

	/**
	 * Ersetzt den Suchtext des Auftrags in einem einzelnen Textwert.
	 *
	 * Drei Wege, je nach Auftrag:
	 * - regulärer Ausdruck: preg_replace mit dem Muster des Benutzers,
	 *   Rückverweise im Ersatztext bleiben wirksam;
	 * - Klartext mit Groß-/Kleinschreibung: schlichtes str_replace;
	 * - Klartext ohne Groß-/Kleinschreibung: preg_replace mit maskiertem
	 *   Suchtext und den Schaltern „iu“, weil str_ireplace Umlaute
	 *   (Ä/ä) nicht als gleich erkennt. Der Ersatztext wird dabei
	 *   maskiert, damit $ und \ wörtlich bleiben.
	 *
	 * @param string  $text    Der bisherige Wert
	 * @param Auftrag $auftrag Auftrag mit Suchtext, Ersatztext und Schaltern
	 *
	 * @return string Der neue Wert; unverändert, wenn nichts gefunden wurde
	 *
	 * @throws \RuntimeException wenn PCRE das Muster auf diesen Text nicht anwenden
	 *                           kann, etwa bei ungültigem UTF-8 im Wert
	 */
	public static function ersetzeText(string $text, Auftrag $auftrag): string
	{
		if (!$auftrag->regex && $auftrag->gross)
		{
			return str_replace($auftrag->suche, $auftrag->ersatz, $text);
		}

		$ersatz = $auftrag->regex ? $auftrag->ersatz : addcslashes($auftrag->ersatz, '\\$');
		$ergebnis = @preg_replace(self::muster($auftrag), $ersatz, $text);

		if (null === $ergebnis)
		{
			throw new \RuntimeException(self::pregFehler());
		}

		return $ergebnis;
	}

	/**
	 * Setzt feste Werte in einer Sprache (Betriebsart „setzen“).
	 *
	 * Fehlt die Sprache bislang, wird sie angelegt; fehlende Felder werden
	 * mit leeren Werten ergänzt, damit der Eintrag dieselbe Gestalt hat wie
	 * einer aus dem MetaWizard. Mit nurLeere bleiben vorhandene Werte stehen.
	 *
	 * @param array   $meta    Metadaten je Sprache
	 * @param Auftrag $auftrag Auftrag mit Sprache, Feldern und Werten
	 *
	 * @return array<string, array<string, string>> Die bereinigten neuen Metadaten
	 *
	 * @throws \InvalidArgumentException wenn keine Sprache angegeben ist
	 */
	private static function setzen(array $meta, Auftrag $auftrag): array
	{
		if ('' === $auftrag->sprache)
		{
			throw new \InvalidArgumentException('Zum Setzen von Werten muss eine Sprache angegeben werden.');
		}

		$eintrag = isset($meta[$auftrag->sprache]) && \is_array($meta[$auftrag->sprache]) ? $meta[$auftrag->sprache] : array();

		foreach (self::FELDER as $feld)
		{
			if (!\array_key_exists($feld, $eintrag))
			{
				$eintrag[$feld] = '';
			}
		}

		foreach ($auftrag->felder as $feld)
		{
			$bisher = self::alsText($eintrag[$feld] ?? '');

			if ($auftrag->nurLeere && '' !== trim($bisher))
			{
				continue;
			}

			$eintrag[$feld] = (string) ($auftrag->werte[$feld] ?? '');
		}

		$meta[$auftrag->sprache] = $eintrag;

		return self::bereinigen($meta);
	}

	/**
	 * Baut aus dem Auftrag ein vollständiges PCRE-Muster samt Begrenzern und Schaltern.
	 *
	 * Bei regulären Ausdrücken wird die Eingabe unverändert übernommen, im
	 * Klartextfall maskiert. Der Schalter „u“ sorgt für UTF-8-Verarbeitung,
	 * „i“ kommt hinzu, wenn Groß-/Kleinschreibung nicht zählen soll.
	 *
	 * @param Auftrag $auftrag Auftrag mit Suchtext und Schaltern
	 *
	 * @return string Das fertige Muster
	 */
	private static function muster(Auftrag $auftrag): string
	{
		$kern = $auftrag->regex ? $auftrag->suche : preg_quote($auftrag->suche, self::BEGRENZER);

		return self::BEGRENZER.$kern.self::BEGRENZER.'u'.($auftrag->gross ? '' : 'i');
	}

	/**
	 * Prüft, ob sich aus dem Auftrag ein gültiges PCRE-Muster bauen lässt.
	 *
	 * Ein Steuerzeichen im Suchtext würde mit dem Begrenzer kollidieren und
	 * gilt deshalb ebenfalls als ungültig — in echten Metadaten kommt es
	 * ohnehin nicht vor.
	 *
	 * @param Auftrag $auftrag Auftrag mit Suchtext und Schaltern
	 *
	 * @return bool true, wenn preg_match das Muster übersetzt
	 */
	private static function musterGueltig(Auftrag $auftrag): bool
	{
		if (false !== strpos($auftrag->suche, self::BEGRENZER))
		{
			return false;
		}

		return false !== @preg_match(self::muster($auftrag), '');
	}

	/**
	 * Liefert die letzte PCRE-Fehlermeldung in lesbarer Form.
	 *
	 * preg_last_error_msg() gibt es erst ab PHP 8; darunter bleibt nur der
	 * Zahlencode.
	 *
	 * @return string Meldung für den Benutzer
	 */
	private static function pregFehler(): string
	{
		if (\function_exists('preg_last_error_msg'))
		{
			return 'Regulärer Ausdruck nicht anwendbar: '.preg_last_error_msg();
		}

		return 'Regulärer Ausdruck nicht anwendbar (PCRE-Fehler '.preg_last_error().')';
	}

	/**
	 * Macht aus einem beliebigen Metadaten-Wert einen Text für Vergleiche.
	 *
	 * Fremde Typen (etwa Felder aus Erweiterungen, die den MetaWizard
	 * erweitern) werden nicht verworfen, sondern serialisiert verglichen.
	 *
	 * @param mixed $wert Der Rohwert
	 *
	 * @return string Der Vergleichstext
	 */
	private static function alsText($wert): string
	{
		if (\is_string($wert))
		{
			return $wert;
		}

		if (null === $wert || \is_scalar($wert))
		{
			return (string) $wert;
		}

		return serialize($wert);
	}
}
