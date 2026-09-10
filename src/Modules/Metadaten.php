<?php

declare(strict_types=1);

/*
 * Metadaten für Contao Open Source CMS
 *
 * @author    Frank Hoppe
 * @license   LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoMetadatenBundle\Modules;

use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Database;
use Contao\FilesModel;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\Versions;
use Schachbulle\ContaoMetadatenBundle\Classes\Auftrag;
use Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung;
use Schachbulle\ContaoMetadatenBundle\Classes\Helfer;

/**
 * Backend-Modul „Metadaten“: Suchen, Ersetzen und Setzen in den Metadaten der Dateiverwaltung.
 *
 * Contao ruft die Klasse über den Eintrag 'callback' in $GLOBALS['BE_MOD']
 * auf: Sie wird mit dem (hier stets leeren) DataContainer erzeugt, und
 * generate() liefert das HTML für den Hauptbereich. Das gilt in 4.13 und
 * 5.x gleichermaßen (Backend::getBackendModule()).
 *
 * Ablauf einer Anfrage:
 * 1. Ordnerliste und Sprachen zusammenstellen, Formularwerte ermitteln
 *    (Vorgaben, Sitzung nach einer Ausführung oder die POST-Eingaben).
 * 2. Bei einem abgeschickten Formular den Auftrag prüfen, die betroffenen
 *    Dateien lesen und die Änderungen berechnen.
 * 3. „Vorschau“ zeigt die Änderungen nur an. „Ausführen“ schreibt sie mit
 *    einer Version je Datei in die Datenbank, merkt sich Formular und
 *    Ergebnis in der Sitzung und leitet auf dieselbe Adresse um, damit ein
 *    Neuladen der Seite die Änderung nicht ein zweites Mal ausführt.
 *
 * Rechte: Wer das Modul sieht, entscheidet Contao über die Modulrechte der
 * Benutzergruppe. Zusätzlich bekommen Nicht-Administratoren nur die Ordner
 * ihrer Dateifreigaben angeboten, und die Dateiauswahl bleibt auf diese
 * Freigaben beschränkt.
 */
class Metadaten
{
	/**
	 * Wert von FORM_SUBMIT, an dem das eigene Formular erkannt wird
	 */
	private const FORMULAR = 'tl_metadaten';

	/**
	 * Schlüssel in der Backend-Sitzung für Formular und Ergebnis nach dem Ausführen
	 */
	private const SITZUNG = 'metadaten_formular';

	/**
	 * Höchstzahl der Dateien, deren Änderungen in der Vorschau aufgelistet werden
	 */
	private const VORSCHAU_MAX = 200;

	/**
	 * Nimmt den DataContainer entgegen, den Contao beim Aufruf übergibt.
	 *
	 * Das Modul arbeitet ohne Tabelle, der Parameter ist deshalb immer null.
	 * Die Signatur muss ihn trotzdem annehmen, weil Backend::getBackendModule()
	 * ihn in beiden Contao-Fassungen übergibt.
	 *
	 * @param mixed $dc Der DataContainer oder null
	 */
	public function __construct($dc = null)
	{
	}

	/**
	 * Baut die Modulseite und verarbeitet ein abgeschicktes Formular.
	 *
	 * @return string Das HTML des Backend-Templates be_metadaten
	 *
	 * @throws RedirectResponseException nach einer erfolgreichen Ausführung
	 */
	public function generate(): string
	{
		System::loadLanguageFile('default');

		$container = System::getContainer();
		$user = BackendUser::getInstance();
		$request = $container->get('request_stack')->getCurrentRequest();
		$sitzung = $container->get('request_stack')->getSession()->getBag('contao_backend');
		$texte = $GLOBALS['TL_LANG']['METADATEN'] ?? array();

		$freigaben = $this->freigaben($user);
		$ordner = $this->ordner($freigaben);
		$sprachen = $container->get('contao.intl.locales')->getEnabledLocales();

		$werte = $this->vorgaben();
		$vorschau = null;
		$ergebnis = null;

		// Nach einer Ausführung: Formular wiederherstellen und Ergebnis zeigen
		if ($sitzung->has(self::SITZUNG))
		{
			$gemerkt = (array) $sitzung->get(self::SITZUNG);
			$sitzung->remove(self::SITZUNG);
			$werte = array_merge($werte, (array) ($gemerkt['formular'] ?? array()));
			$ergebnis = (array) ($gemerkt['ergebnis'] ?? array());
		}

		if (self::FORMULAR === Input::post('FORM_SUBMIT'))
		{
			$werte = $this->eingaben();
			$auftrag = $this->auftrag($werte);
			$fehler = $this->pruefen($auftrag, $werte, $ordner, $sprachen, $freigaben);

			if (!$fehler)
			{
				$dateien = $this->dateien($werte, $freigaben);
				$aenderungen = $this->aenderungen($dateien, $auftrag, $fehler);

				if (!$fehler && Input::post('ausfuehren'))
				{
					$anzahl = $this->schreiben($aenderungen);

					Message::addConfirmation(sprintf($texte['erledigt'] ?? '%d Dateien geändert.', $anzahl));

					$sitzung->set(self::SITZUNG, array(
						'formular' => $werte,
						'ergebnis' => array_column($aenderungen, 'path'),
					));

					throw new RedirectResponseException($request->getRequestUri());
				}

				$vorschau = array(
					'gesamt'   => \count($dateien),
					'anzahl'   => \count($aenderungen),
					'zeilen'   => \array_slice($aenderungen, 0, self::VORSCHAU_MAX),
					'gekuerzt' => \count($aenderungen) > self::VORSCHAU_MAX,
				);
			}

			foreach ($fehler as $meldung)
			{
				Message::addError($meldung);
			}
		}

		$template = new BackendTemplate('be_metadaten');
		$template->texte = $texte;
		$template->werte = $werte;
		$template->ordner = $ordner;
		$template->sprachen = $sprachen;
		$template->felder = $this->feldbezeichnungen();
		$template->vorschau = $vorschau;
		$template->ergebnis = $ergebnis;
		$template->meldungen = Message::generate();
		$template->requestToken = Helfer::requestToken();
		$template->action = StringUtil::ampersand($request->getRequestUri());
		$template->zurueck = $this->dateiverwaltungUrl();
		$template->formular = self::FORMULAR;

		return $template->parse();
	}

	/**
	 * Liefert die Vorgabewerte des Formulars beim ersten Aufruf.
	 *
	 * Bewusst zurückhaltend: kein Feld vorausgewählt, „nur leere Felder
	 * füllen“ eingeschaltet, Groß-/Kleinschreibung beachtet. Wer überschreiben
	 * will, muss das ausdrücklich anwählen.
	 *
	 * @return array<string, mixed> Formularwerte mit denselben Schlüsseln wie eingaben()
	 */
	private function vorgaben(): array
	{
		return array(
			'ordner'      => '',
			'unterordner' => true,
			'endungen'    => '',
			'sprache'     => '',
			'felder'      => array(),
			'modus'       => Auftrag::MODUS_ERSETZEN,
			'suche'       => '',
			'ersatz'      => '',
			'regex'       => false,
			'gross'       => true,
			'nurLeere'    => true,
			'werte'       => array_fill_keys(Bearbeitung::FELDER, ''),
		);
	}

	/**
	 * Liest die Formulareingaben aus der POST-Anfrage.
	 *
	 * Such-, Ersatz- und Wertetexte kommen über Input::postRaw(), damit sie
	 * so in der Datenbank landen, wie Contaos MetaWizard sie ablegt (das Feld
	 * meta erlaubt HTML). Auswahlwerte laufen über Input::post() und werden
	 * anschließend ohnehin gegen die erlaubten Listen geprüft.
	 *
	 * @return array<string, mixed> Formularwerte mit denselben Schlüsseln wie vorgaben()
	 */
	private function eingaben(): array
	{
		$werte = array();

		foreach (Bearbeitung::FELDER as $feld)
		{
			$werte[$feld] = (string) Input::postRaw('wert_'.$feld);
		}

		return array(
			'ordner'      => (string) Input::post('ordner'),
			'unterordner' => (bool) Input::post('unterordner'),
			'endungen'    => (string) Input::post('endungen'),
			'sprache'     => (string) Input::post('sprache'),
			'felder'      => array_values(array_map('strval', (array) Input::post('felder'))),
			'modus'       => (string) Input::post('modus'),
			'suche'       => (string) Input::postRaw('suche'),
			'ersatz'      => (string) Input::postRaw('ersatz'),
			'regex'       => (bool) Input::post('regex'),
			'gross'       => (bool) Input::post('gross'),
			'nurLeere'    => (bool) Input::post('nurLeere'),
			'werte'       => $werte,
		);
	}

	/**
	 * Übersetzt die Formularwerte in einen Auftrag für die Bearbeitung.
	 *
	 * @param array<string, mixed> $werte Formularwerte aus eingaben()
	 *
	 * @return Auftrag Der ungeprüfte Auftrag
	 */
	private function auftrag(array $werte): Auftrag
	{
		$auftrag = new Auftrag();
		$auftrag->modus = $werte['modus'];
		$auftrag->felder = $werte['felder'];
		$auftrag->sprache = $werte['sprache'];
		$auftrag->suche = $werte['suche'];
		$auftrag->ersatz = $werte['ersatz'];
		$auftrag->regex = $werte['regex'];
		$auftrag->gross = $werte['gross'];
		$auftrag->werte = $werte['werte'];
		$auftrag->nurLeere = $werte['nurLeere'];

		return $auftrag;
	}

	/**
	 * Prüft Auftrag und Auswahl und liefert fertige Fehlermeldungen.
	 *
	 * Neben den formalen Prüfungen der Bearbeitung wird hier sichergestellt,
	 * dass Ordner und Sprache aus den angebotenen Listen stammen — der Ordner
	 * ist die einzige Stelle, an der eine manipulierte Eingabe sonst Dateien
	 * außerhalb der eigenen Freigaben erreichen könnte.
	 *
	 * @param Auftrag               $auftrag   Der zu prüfende Auftrag
	 * @param array<string, mixed>  $werte     Formularwerte
	 * @param array<string, string> $ordner    Erlaubte Ordnerpfade als Schlüssel
	 * @param array<string, string> $sprachen  Aktivierte Sprachen als Schlüssel
	 * @param string[]|null         $freigaben Dateifreigaben des Benutzers, null ohne Beschränkung
	 *
	 * @return string[] Übersetzte Fehlermeldungen; leer, wenn alles stimmt
	 */
	private function pruefen(Auftrag $auftrag, array $werte, array $ordner, array $sprachen, ?array $freigaben): array
	{
		$texte = $GLOBALS['TL_LANG']['METADATEN']['fehler'] ?? array();
		$fehler = array();

		foreach (Bearbeitung::pruefen($auftrag) as $schluessel)
		{
			$fehler[] = $texte[$schluessel] ?? $schluessel;
		}

		if ('' !== $werte['ordner'] && !isset($ordner[$werte['ordner']]))
		{
			$fehler[] = $texte['ordnerUnbekannt'] ?? 'ordnerUnbekannt';
		}

		if ('' === $werte['ordner'] && null !== $freigaben && !$freigaben)
		{
			$fehler[] = $texte['keineFreigabe'] ?? 'keineFreigabe';
		}

		if ('' !== $werte['sprache'] && !isset($sprachen[$werte['sprache']]))
		{
			$fehler[] = $texte['spracheUnbekannt'] ?? 'spracheUnbekannt';
		}

		return $fehler;
	}

	/**
	 * Ermittelt die Dateifreigaben des angemeldeten Benutzers als Pfade.
	 *
	 * @param BackendUser $user Der angemeldete Benutzer
	 *
	 * @return string[]|null Pfade der freigegebenen Ordner (ohne Schrägstrich
	 *                       am Ende); null für Administratoren, die alles sehen;
	 *                       leer, wenn der Benutzer keine Freigaben hat
	 */
	private function freigaben(BackendUser $user): ?array
	{
		if ($user->isAdmin)
		{
			return null;
		}

		$uuids = array_filter((array) $user->filemounts);

		if (!$uuids)
		{
			return array();
		}

		$pfade = array();
		$modelle = FilesModel::findMultipleByUuids(array_values($uuids));

		if (null !== $modelle)
		{
			foreach ($modelle as $modell)
			{
				$pfade[] = rtrim((string) $modell->path, '/');
			}
		}

		return $pfade;
	}

	/**
	 * Stellt die Ordnerliste für das Auswahlfeld zusammen.
	 *
	 * Grundlage ist tl_files, nicht das Dateisystem — nur registrierte Ordner
	 * haben Dateien mit Metadaten. Für Benutzer mit Freigaben bleiben nur die
	 * freigegebenen Ordner samt Unterordnern übrig.
	 *
	 * @param string[]|null $freigaben Freigegebene Pfade, null ohne Beschränkung
	 *
	 * @return array<string, string> Pfad => Pfad, sortiert
	 */
	private function ordner(?array $freigaben): array
	{
		$liste = array();
		$result = Database::getInstance()->execute("SELECT path FROM tl_files WHERE type='folder' ORDER BY path");

		while ($result->next())
		{
			$pfad = (string) $result->path;

			if (null !== $freigaben && !$this->innerhalb($pfad, $freigaben))
			{
				continue;
			}

			$liste[$pfad] = $pfad;
		}

		return $liste;
	}

	/**
	 * Prüft, ob ein Pfad in einem der freigegebenen Ordner liegt.
	 *
	 * @param string   $pfad      Zu prüfender Pfad
	 * @param string[] $freigaben Freigegebene Ordnerpfade
	 *
	 * @return bool true, wenn der Pfad einer Freigabe entspricht oder darunter liegt
	 */
	private function innerhalb(string $pfad, array $freigaben): bool
	{
		foreach ($freigaben as $freigabe)
		{
			if ($pfad === $freigabe || 0 === strpos($pfad, $freigabe.'/'))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Liest die Dateien, auf die der Auftrag angewendet werden soll.
	 *
	 * Die Eingrenzung geschieht ausschließlich über die Spalte path:
	 * „Ordner mit Unterordnern“ ist ein LIKE auf den Pfadanfang, „nur der
	 * Ordner selbst“ schließt zusätzlich alles mit einem weiteren Schrägstrich
	 * aus. Ohne gewählten Ordner zählen alle Dateien bzw. alle Freigaben.
	 *
	 * @param array<string, mixed> $werte     Formularwerte (ordner, unterordner, endungen)
	 * @param string[]|null        $freigaben Freigegebene Pfade, null ohne Beschränkung
	 *
	 * @return array<int, array{id: int|string, path: string, meta: string|null}>
	 *         Die Dateien mit ihren rohen Metadaten, nach Pfad sortiert
	 */
	private function dateien(array $werte, ?array $freigaben): array
	{
		$bedingungen = array("type='file'");
		$parameter = array();

		if ('' !== $werte['ordner'])
		{
			$bedingungen[] = 'path LIKE ?';
			$parameter[] = $this->likeMuster($werte['ordner']).'/%';

			if (!$werte['unterordner'])
			{
				$bedingungen[] = 'path NOT LIKE ?';
				$parameter[] = $this->likeMuster($werte['ordner']).'/%/%';
			}
		}
		elseif (null !== $freigaben)
		{
			if (!$freigaben)
			{
				return array();
			}

			$teile = array();

			foreach ($freigaben as $freigabe)
			{
				$teile[] = 'path LIKE ?';
				$parameter[] = $this->likeMuster($freigabe).'/%';
			}

			$bedingungen[] = '('.implode(' OR ', $teile).')';
		}

		$endungen = $this->endungen($werte['endungen']);

		if ($endungen)
		{
			$bedingungen[] = 'LOWER(extension) IN ('.implode(',', array_fill(0, \count($endungen), '?')).')';
			$parameter = array_merge($parameter, $endungen);
		}

		$result = Database::getInstance()
			->prepare('SELECT id, path, meta FROM tl_files WHERE '.implode(' AND ', $bedingungen).' ORDER BY path')
			->execute($parameter);

		return $result->fetchAllAssoc();
	}

	/**
	 * Maskiert einen Pfad für den Einsatz in einem LIKE-Muster.
	 *
	 * Prozentzeichen und Unterstriche haben in LIKE eine Sonderbedeutung;
	 * ein Ordner „2024_bilder“ soll aber nur sich selbst treffen.
	 *
	 * @param string $pfad Der Ordnerpfad
	 *
	 * @return string Der maskierte Pfad ohne angehängtes Muster
	 */
	private function likeMuster(string $pfad): string
	{
		return addcslashes(rtrim($pfad, '/'), '\\%_');
	}

	/**
	 * Zerlegt die Eingabe der Dateiendungen in eine bereinigte Liste.
	 *
	 * Erlaubt sind Komma, Semikolon und Leerzeichen als Trenner; führende
	 * Punkte werden entfernt, alles wird kleingeschrieben.
	 *
	 * @param string $eingabe Text aus dem Formularfeld
	 *
	 * @return string[] Endungen in Kleinschreibung, ohne Doppelte; leer für „alle“
	 */
	private function endungen(string $eingabe): array
	{
		$teile = preg_split('/[\s,;]+/', mb_strtolower(trim($eingabe))) ?: array();
		$liste = array();

		foreach ($teile as $teil)
		{
			$teil = ltrim(trim($teil), '.');

			if ('' !== $teil)
			{
				$liste[$teil] = $teil;
			}
		}

		return array_values($liste);
	}

	/**
	 * Berechnet für jede Datei die neuen Metadaten und behält nur echte Änderungen.
	 *
	 * Schlägt ein regulärer Ausdruck bei einer Datei fehl (etwa wegen
	 * ungültigem UTF-8 im Wert), landet die Meldung samt Pfad in der
	 * Fehlerliste; die übrigen Dateien werden weiter berechnet, ausgeführt
	 * wird dann aber nichts.
	 *
	 * @param array<int, array{id: int|string, path: string, meta: string|null}> $dateien Dateien aus dateien()
	 * @param Auftrag                                                            $auftrag Der geprüfte Auftrag
	 * @param string[]                                                           $fehler  Fehlerliste, wird ergänzt
	 *
	 * @return array<int, array{id: int, path: string, neu: array, unterschiede: array}>
	 *         Nur Dateien, bei denen sich mindestens ein Wert ändert
	 */
	private function aenderungen(array $dateien, Auftrag $auftrag, array &$fehler): array
	{
		$liste = array();

		foreach ($dateien as $datei)
		{
			$alt = Bearbeitung::lesen($datei['meta']);

			try
			{
				$neu = Bearbeitung::anwenden($alt, $auftrag);
			}
			catch (\RuntimeException $e)
			{
				$fehler[] = $datei['path'].': '.$e->getMessage();
				continue;
			}

			$unterschiede = Bearbeitung::unterschiede($alt, $neu);

			if (!$unterschiede)
			{
				continue;
			}

			$liste[] = array(
				'id'           => (int) $datei['id'],
				'path'         => (string) $datei['path'],
				'neu'          => $neu,
				'unterschiede' => $unterschiede,
			);
		}

		return $liste;
	}

	/**
	 * Schreibt die berechneten Änderungen in tl_files.
	 *
	 * Vor jeder Änderung wird mit Contaos Versions-Klasse der bisherige Stand
	 * gesichert und danach eine neue Version angelegt — so lässt sich jede
	 * Datei in der Dateiverwaltung über die Versionsauswahl zurücksetzen,
	 * genau wie nach einer Bearbeitung von Hand. tl_files hat die
	 * Versionierung im Kern eingeschaltet.
	 *
	 * @param array<int, array{id: int, path: string, neu: array, unterschiede: array}> $aenderungen Änderungen aus aenderungen()
	 *
	 * @return int Anzahl der geänderten Dateien
	 */
	private function schreiben(array $aenderungen): int
	{
		$db = Database::getInstance();
		$anzahl = 0;

		foreach ($aenderungen as $aenderung)
		{
			$versionen = new Versions('tl_files', $aenderung['id']);
			$versionen->initialize();

			$db->prepare('UPDATE tl_files SET tstamp=?, meta=? WHERE id=?')
				->execute(time(), serialize($aenderung['neu']), $aenderung['id']);

			$versionen->create();
			++$anzahl;
		}

		return $anzahl;
	}

	/**
	 * Liefert die Bezeichnungen der Metadaten-Felder aus Contaos Sprachdatei.
	 *
	 * Der MetaWizard beschriftet seine Felder mit MSC.aw_<feld>; dieselben
	 * Texte hier zu verwenden, hält das Modul mit der Dateiverwaltung
	 * deckungsgleich, auch wenn Contao die Bezeichnungen einmal ändert.
	 *
	 * @return array<string, string> Feldschlüssel => Bezeichnung
	 */
	private function feldbezeichnungen(): array
	{
		$liste = array();

		foreach (Bearbeitung::FELDER as $feld)
		{
			$liste[$feld] = (string) ($GLOBALS['TL_LANG']['MSC']['aw_'.$feld] ?? $feld);
		}

		return $liste;
	}

	/**
	 * Baut die Adresse der Dateiverwaltung für den Zurück-Knopf.
	 *
	 * Über den Router, damit auch Installationen in einem Unterverzeichnis
	 * die richtige Adresse bekommen; ohne Router bleibt die relative Form,
	 * die im Backend beider Fassungen funktioniert.
	 *
	 * @return string Die Adresse mit Anfrage-Token
	 */
	private function dateiverwaltungUrl(): string
	{
		$container = System::getContainer();

		if (null !== $container && $container->has('router'))
		{
			$url = $container->get('router')->generate('contao_backend', array('do' => 'files', 'rt' => Helfer::requestToken()));

			return StringUtil::ampersand($url);
		}

		return 'contao?do=files&amp;rt='.Helfer::requestToken();
	}
}
