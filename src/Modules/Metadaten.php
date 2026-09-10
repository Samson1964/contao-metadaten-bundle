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
use Contao\DataContainer;
use Contao\FilesModel;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\Versions;
use Schachbulle\ContaoMetadatenBundle\Classes\Auftrag;
use Schachbulle\ContaoMetadatenBundle\Classes\Bearbeitung;
use Schachbulle\ContaoMetadatenBundle\Classes\Helfer;
use Schachbulle\ContaoMetadatenBundle\Model\MetadatenModel;

/**
 * Vorschau und Ausführung eines gespeicherten Auftrags aus tl_metadaten.
 *
 * Contao ruft vorschau() über den Eintrag 'vorschau' in $GLOBALS['BE_MOD']
 * auf, sobald in der Adresszeile &key=vorschau&id=… steht (Operation
 * „Vorschau und Ausführen“ in der Auftragsliste). Die Klasse wird dafür mit
 * System::importStatic() ohne Argumente erzeugt — in 4.13 und 5.x gleich.
 *
 * Ablauf einer Anfrage:
 * 1. Auftrag laden, Ordner-UUID in einen Pfad auflösen, Auftrag prüfen.
 * 2. Betroffene Dateien lesen und die Änderungen berechnen.
 * 3. Ohne POST: Vorschau mit alten und neuen Werten anzeigen.
 *    Mit POST „ausführen“: Änderungen mit einer Version je Datei schreiben,
 *    Ergebnis in der Sitzung merken und auf dieselbe Adresse umleiten, damit
 *    ein Neuladen der Seite die Änderung nicht ein zweites Mal ausführt.
 *
 * Rechte: Wer das Modul sieht, entscheidet Contao über die Modulrechte der
 * Benutzergruppe. Zusätzlich bleibt die Dateiauswahl für Nicht-Administratoren
 * auf ihre Dateifreigaben beschränkt — auch dann, wenn der Auftrag von einem
 * Administrator mit einem fremden Ordner angelegt wurde.
 */
class Metadaten
{
	/**
	 * Wert von FORM_SUBMIT, an dem die Ausführung erkannt wird
	 */
	private const FORMULAR = 'tl_metadaten_ausfuehren';

	/**
	 * Schlüssel in der Backend-Sitzung für das Ergebnis nach dem Ausführen
	 */
	private const SITZUNG = 'metadaten_ergebnis';

	/**
	 * Höchstzahl der Dateien, deren Änderungen in der Vorschau aufgelistet werden
	 */
	private const VORSCHAU_MAX = 200;

	/**
	 * Baut die Vorschauseite eines Auftrags und führt ihn auf Wunsch aus.
	 *
	 * @param DataContainer|null $dc Der DataContainer von tl_metadaten (von Contao übergeben)
	 *
	 * @return string Das HTML des Backend-Templates be_metadaten
	 *
	 * @throws RedirectResponseException nach einer Ausführung oder bei unbekanntem Auftrag
	 */
	public function vorschau($dc = null): string
	{
		System::loadLanguageFile('default');
		System::loadLanguageFile('tl_metadaten');

		$container = System::getContainer();
		$user = BackendUser::getInstance();
		$request = $container->get('request_stack')->getCurrentRequest();
		$sitzung = $container->get('request_stack')->getSession()->getBag('contao_backend');
		$texte = $GLOBALS['TL_LANG']['METADATEN'] ?? array();

		$id = (int) Input::get('id');
		$model = $id > 0 ? MetadatenModel::findByPk($id) : null;

		if (null === $model)
		{
			Message::addError($texte['fehler']['auftragUnbekannt'] ?? 'auftragUnbekannt');

			throw new RedirectResponseException($this->listenUrl());
		}

		$row = $model->row();
		$auftrag = Auftrag::ausDatensatz($row);
		$freigaben = $this->freigaben($user);
		$fehler = array();
		$ordnerPfad = $this->ordnerPfad($row, $freigaben, $fehler);
		$vorschau = null;
		$ergebnis = null;

		foreach (Bearbeitung::pruefen($auftrag) as $schluessel)
		{
			$fehler[] = $texte['fehler'][$schluessel] ?? $schluessel;
		}

		// Nach einer Ausführung: Ergebnis aus der Sitzung zeigen
		if ($sitzung->has(self::SITZUNG))
		{
			$gemerkt = (array) $sitzung->get(self::SITZUNG);
			$sitzung->remove(self::SITZUNG);

			if ((int) ($gemerkt['id'] ?? 0) === $id)
			{
				$ergebnis = (array) ($gemerkt['dateien'] ?? array());
			}
		}

		if (!$fehler)
		{
			$dateien = $this->dateien($row, $ordnerPfad, $freigaben);
			$aenderungen = $this->aenderungen($dateien, $auftrag, $fehler);

			if (!$fehler && self::FORMULAR === Input::post('FORM_SUBMIT'))
			{
				$anzahl = $this->schreiben($aenderungen);

				Message::addConfirmation(sprintf($texte['erledigt'] ?? '%d Dateien geändert.', $anzahl));

				$sitzung->set(self::SITZUNG, array(
					'id'      => $id,
					'dateien' => array_column($aenderungen, 'path'),
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

		$template = new BackendTemplate('be_metadaten');
		$template->texte = $texte;
		$template->titel = (string) $row['titel'];
		$template->zusammenfassung = $this->zusammenfassung($row, $auftrag, $ordnerPfad);
		$template->felder = $this->feldbezeichnungen();
		$template->vorschau = $vorschau;
		$template->ergebnis = $ergebnis;
		$template->meldungen = Message::generate();
		$template->requestToken = Helfer::requestToken();
		$template->action = StringUtil::ampersand($request->getRequestUri());
		$template->zurueck = $this->listenUrl();
		$template->bearbeiten = $this->bearbeitenUrl($id);
		$template->formular = self::FORMULAR;

		return $template->parse();
	}

	/**
	 * Löst die Ordner-UUID des Auftrags in einen Pfad auf und prüft die Freigaben.
	 *
	 * @param array<string, mixed> $row       Der Datensatz aus tl_metadaten
	 * @param string[]|null        $freigaben Freigegebene Pfade, null ohne Beschränkung
	 * @param string[]             $fehler    Fehlerliste, wird ergänzt
	 *
	 * @return string Der Ordnerpfad ohne Schrägstrich am Ende; leer für „alle Dateien“
	 */
	private function ordnerPfad(array $row, ?array $freigaben, array &$fehler): string
	{
		$texte = $GLOBALS['TL_LANG']['METADATEN']['fehler'] ?? array();

		if (empty($row['ordner']))
		{
			if (null !== $freigaben && !$freigaben)
			{
				$fehler[] = $texte['keineFreigabe'] ?? 'keineFreigabe';
			}

			return '';
		}

		$objOrdner = FilesModel::findByUuid($row['ordner']);

		if (null === $objOrdner || 'folder' !== $objOrdner->type)
		{
			$fehler[] = $texte['ordnerUnbekannt'] ?? 'ordnerUnbekannt';

			return '';
		}

		$pfad = rtrim((string) $objOrdner->path, '/');

		if (null !== $freigaben && !$this->innerhalb($pfad, $freigaben))
		{
			$fehler[] = $texte['ordnerGesperrt'] ?? 'ordnerGesperrt';
		}

		return $pfad;
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
			if ($pfad === $freigabe || 0 === strpos($pfad, $freigabe . '/'))
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
	 * aus. Ohne Ordner zählen alle Dateien bzw. alle Freigaben.
	 *
	 * @param array<string, mixed> $row        Der Datensatz (unterordner, endungen)
	 * @param string               $ordnerPfad Aufgelöster Ordnerpfad, leer für alle
	 * @param string[]|null        $freigaben  Freigegebene Pfade, null ohne Beschränkung
	 *
	 * @return array<int, array{id: int|string, path: string, meta: string|null}>
	 *         Die Dateien mit ihren rohen Metadaten, nach Pfad sortiert
	 */
	private function dateien(array $row, string $ordnerPfad, ?array $freigaben): array
	{
		$bedingungen = array("type='file'");
		$parameter = array();

		if ('' !== $ordnerPfad)
		{
			$bedingungen[] = 'path LIKE ?';
			$parameter[] = $this->likeMuster($ordnerPfad) . '/%';

			if (empty($row['unterordner']))
			{
				$bedingungen[] = 'path NOT LIKE ?';
				$parameter[] = $this->likeMuster($ordnerPfad) . '/%/%';
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
				$parameter[] = $this->likeMuster($freigabe) . '/%';
			}

			$bedingungen[] = '(' . implode(' OR ', $teile) . ')';
		}

		$endungen = $this->endungen((string) ($row['endungen'] ?? ''));

		if ($endungen)
		{
			$bedingungen[] = 'LOWER(extension) IN (' . implode(',', array_fill(0, \count($endungen), '?')) . ')';
			$parameter = array_merge($parameter, $endungen);
		}

		$result = Database::getInstance()
			->prepare('SELECT id, path, meta FROM tl_files WHERE ' . implode(' AND ', $bedingungen) . ' ORDER BY path')
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
	 * @param string $eingabe Text aus dem Feld endungen
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
				$fehler[] = $datei['path'] . ': ' . $e->getMessage();
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
	 * Stellt die Einstellungen des Auftrags für den Kopf der Vorschauseite zusammen.
	 *
	 * @param array<string, mixed> $row        Der Datensatz
	 * @param Auftrag              $auftrag    Der daraus gebaute Auftrag
	 * @param string               $ordnerPfad Aufgelöster Ordnerpfad, leer für alle
	 *
	 * @return array<int, array{label: string, wert: string}> Bezeichnung und Wert je Zeile
	 */
	private function zusammenfassung(array $row, Auftrag $auftrag, string $ordnerPfad): array
	{
		$lang = $GLOBALS['TL_LANG']['tl_metadaten'] ?? array();
		$ja = $GLOBALS['TL_LANG']['MSC']['yes'] ?? 'ja';
		$nein = $GLOBALS['TL_LANG']['MSC']['no'] ?? 'nein';
		$bezeichnungen = $this->feldbezeichnungen();

		$ordner = '' !== $ordnerPfad ? $ordnerPfad : ($lang['alleDateien'] ?? 'alle Dateien');

		if ('' !== $ordnerPfad && !empty($row['unterordner']))
		{
			$ordner .= ' ' . ($lang['mitUnterordnern'] ?? '(mit Unterordnern)');
		}

		$felder = array();

		foreach ($auftrag->felder as $feld)
		{
			$felder[] = $bezeichnungen[$feld] ?? $feld;
		}

		$zeilen = array(
			array('label' => $lang['ordner'][0] ?? 'Ordner', 'wert' => $ordner),
			array('label' => $lang['endungen'][0] ?? 'Dateiendungen', 'wert' => '' !== trim((string) $row['endungen']) ? (string) $row['endungen'] : ($lang['alleDateien'] ?? 'alle')),
			array('label' => $lang['sprache'][0] ?? 'Sprache', 'wert' => '' !== $auftrag->sprache ? $auftrag->sprache : ($lang['alleSprachen'] ?? 'alle')),
			array('label' => $lang['felder'][0] ?? 'Felder', 'wert' => implode(', ', $felder)),
			array('label' => $lang['modus'][0] ?? 'Betriebsart', 'wert' => (string) ($lang['modusOptionen'][$auftrag->modus] ?? $auftrag->modus)),
		);

		if (Auftrag::MODUS_SETZEN === $auftrag->modus)
		{
			foreach ($auftrag->felder as $feld)
			{
				$zeilen[] = array('label' => $bezeichnungen[$feld] ?? $feld, 'wert' => $auftrag->werte[$feld] ?? '');
			}

			$zeilen[] = array('label' => $lang['nurLeere'][0] ?? 'Nur leere Felder füllen', 'wert' => $auftrag->nurLeere ? $ja : $nein);
		}
		else
		{
			$zeilen[] = array('label' => $lang['suche'][0] ?? 'Suchen nach', 'wert' => $auftrag->suche);
			$zeilen[] = array('label' => $lang['ersatz'][0] ?? 'Ersetzen durch', 'wert' => $auftrag->ersatz);
			$zeilen[] = array('label' => $lang['gross'][0] ?? 'Groß-/Kleinschreibung', 'wert' => $auftrag->gross ? $ja : $nein);
			$zeilen[] = array('label' => $lang['regex'][0] ?? 'Regulärer Ausdruck', 'wert' => $auftrag->regex ? $ja : $nein);
		}

		return $zeilen;
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
			$liste[$feld] = (string) ($GLOBALS['TL_LANG']['MSC']['aw_' . $feld] ?? $feld);
		}

		return $liste;
	}

	/**
	 * Baut die Adresse der Auftragsliste für den Zurück-Knopf.
	 *
	 * Über den Router, damit auch Installationen in einem Unterverzeichnis
	 * die richtige Adresse bekommen; ohne Router bleibt die relative Form,
	 * die im Backend beider Fassungen funktioniert.
	 *
	 * @return string Die Adresse mit Anfrage-Token
	 */
	private function listenUrl(): string
	{
		return $this->backendUrl(array('do' => 'metadaten'));
	}

	/**
	 * Baut die Adresse des Bearbeitungsformulars eines Auftrags.
	 *
	 * @param int $id ID des Auftrags
	 *
	 * @return string Die Adresse mit Anfrage-Token
	 */
	private function bearbeitenUrl(int $id): string
	{
		return $this->backendUrl(array('do' => 'metadaten', 'act' => 'edit', 'id' => $id));
	}

	/**
	 * Baut eine Backend-Adresse mit Anfrage-Token.
	 *
	 * @param array<string, mixed> $parameter Abfrageparameter ohne rt
	 *
	 * @return string Die Adresse mit maskiertem Kaufmanns-Und
	 */
	private function backendUrl(array $parameter): string
	{
		$parameter['rt'] = Helfer::requestToken();
		$container = System::getContainer();

		if (null !== $container && $container->has('router'))
		{
			return StringUtil::ampersand($container->get('router')->generate('contao_backend', $parameter));
		}

		return StringUtil::ampersand('contao?' . http_build_query($parameter));
	}
}
