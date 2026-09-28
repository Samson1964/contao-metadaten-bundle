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
use Schachbulle\ContaoMetadatenBundle\Classes\Bildanalyse;
use Schachbulle\ContaoMetadatenBundle\Classes\Bildteil;
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
 * Ist im Auftrag „Wichtigen Bildteil automatisch markieren“ gesetzt, kommen
 * die Bilder ohne wichtigen Teil hinzu: Die Vorschau zeigt für die ersten
 * davon das geschätzte Rechteck, die Ausführung markiert so viele, wie in
 * das Zeitbudget passen. Bilder mit wichtigem Teil werden nie angefasst.
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
	 * Höchstzahl der Bilder, für die die Vorschau den geschätzten wichtigen Teil zeigt
	 */
	private const BEISPIELE_MAX = 12;

	/**
	 * Obergrenze in Sekunden für die Bildanalyse je Ausführung
	 */
	private const ZEITBUDGET = 20.0;

	/**
	 * Wert von FORM_SUBMIT, an dem das Formular des Bildteil-Editors erkannt wird
	 */
	private const FORMULAR_BILDTEILE = 'tl_metadaten_bildteile';

	/**
	 * Zahl der Bilder je Seite im Bildteil-Editor.
	 *
	 * Jedes Bild braucht beim ersten Mal ein Vorschaubild aus der Bildfabrik;
	 * mehr Bilder je Seite machten den Seitenaufbau entsprechend träge.
	 */
	private const BILDER_JE_SEITE = 12;

	/**
	 * Kantenlänge der Vorschaubilder im Bildteil-Editor in Pixeln
	 */
	private const EDITOR_KANTE = 480;

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

		$row = $this->auftragszeile();
		$id = (int) $row['id'];
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

		$bildteil = null;

		if (!$fehler)
		{
			$dateien = $this->dateien($row, $ordnerPfad, $freigaben);
			$aenderungen = $this->aenderungen($dateien, $auftrag, $fehler);
			$kandidaten = $auftrag->wichtigerTeil ? Bildteil::kandidaten($dateien) : array();

			if (!$fehler && self::FORMULAR === Input::post('FORM_SUBMIT'))
			{
				$geschrieben = $this->ausfuehren($aenderungen, $kandidaten);

				$this->meldeErgebnis($geschrieben, \count($kandidaten));

				$sitzung->set(self::SITZUNG, array(
					'id'      => $id,
					'dateien' => $geschrieben['dateien'],
				));

				throw new RedirectResponseException($request->getRequestUri());
			}

			$vorschau = array(
				'gesamt'   => \count($dateien),
				'anzahl'   => \count($aenderungen),
				'zeilen'   => \array_slice($aenderungen, 0, self::VORSCHAU_MAX),
				'gekuerzt' => \count($aenderungen) > self::VORSCHAU_MAX,
				'metadaten' => Auftrag::MODUS_KEINE !== $auftrag->modus,
			);

			if ($auftrag->wichtigerTeil)
			{
				$bildteil = array(
					'anzahl'     => \count($kandidaten),
					'verfuegbar' => Bildanalyse::verfuegbar(),
					'beispiele'  => $this->beispiele($kandidaten),
				);
			}
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
		$template->bildteil = $bildteil;
		$template->ausfuehrbar = null !== $vorschau && ($vorschau['anzahl'] > 0 || (null !== $bildteil && $bildteil['anzahl'] > 0 && $bildteil['verfuegbar']));
		$template->ergebnis = $ergebnis;
		$template->meldungen = Message::generate();
		$template->requestToken = Helfer::requestToken();
		$template->action = StringUtil::ampersand($request->getRequestUri());
		$template->zurueck = $this->listenUrl();
		$template->bearbeiten = $this->bearbeitenUrl($id);
		$template->bildteileUrl = $this->backendUrl(array('do' => 'metadaten', 'key' => 'bildteile', 'id' => $id));
		$template->formular = self::FORMULAR;

		return $template->parse();
	}

	/**
	 * Baut den Bildteil-Editor: Bilder ohne wichtigen Teil ansehen und von Hand markieren.
	 *
	 * Contao ruft die Methode über den Eintrag 'bildteile' in
	 * $GLOBALS['BE_MOD'] auf (&key=bildteile&id=…, Operation „Wichtige
	 * Bildteile markieren“ in der Auftragsliste). Vom Auftrag zählt hier nur
	 * die Dateiauswahl — Ordner, Unterordner, Dateiendungen. Betriebsart und
	 * Metadaten spielen keine Rolle.
	 *
	 * Die Seite zeigt je Durchgang eine begrenzte Zahl von Bildern mit einem
	 * Vorschlag aus der Bildanalyse. Der Vorschlag ist nur der Ausgangspunkt:
	 * Das Rechteck lässt sich im Browser verschieben, in der Größe ändern oder
	 * neu aufziehen (bildteile.js). Gespeichert werden nur Bilder, deren
	 * Häkchen „übernehmen“ gesetzt ist; alle anderen bleiben unmarkiert und
	 * erscheinen beim nächsten Aufruf wieder.
	 *
	 * @param DataContainer|null $dc Der DataContainer von tl_metadaten (von Contao übergeben)
	 *
	 * @return string Das HTML des Backend-Templates be_metadaten_bildteile
	 *
	 * @throws RedirectResponseException nach dem Speichern oder bei unbekanntem Auftrag
	 */
	public function bildteile($dc = null): string
	{
		System::loadLanguageFile('default');
		System::loadLanguageFile('tl_metadaten');

		$container = System::getContainer();
		$request = $container->get('request_stack')->getCurrentRequest();
		$texte = $GLOBALS['TL_LANG']['METADATEN'] ?? array();

		$row = $this->auftragszeile();
		$id = (int) $row['id'];
		$freigaben = $this->freigaben(BackendUser::getInstance());
		$fehler = array();
		$ordnerPfad = $this->ordnerPfad($row, $freigaben, $fehler);
		$kandidaten = $fehler ? array() : Bildteil::kandidaten($this->dateien($row, $ordnerPfad, $freigaben));

		if (!$fehler && self::FORMULAR_BILDTEILE === Input::post('FORM_SUBMIT'))
		{
			$ergebnis = $this->speichereBildteile($kandidaten);

			if ($ergebnis['gespeichert'] > 0)
			{
				Message::addConfirmation(sprintf($texte['markiert'] ?? '%d Bilder markiert.', $ergebnis['gespeichert']));
			}
			else
			{
				Message::addInfo($texte['bildteileNichts'] ?? 'Nichts gespeichert.');
			}

			if ($ergebnis['abgewiesen'] > 0)
			{
				Message::addError(sprintf($texte['fehler']['bildteilUngueltig'] ?? '%d ungültig.', $ergebnis['abgewiesen']));
			}

			throw new RedirectResponseException($request->getRequestUri());
		}

		foreach ($fehler as $meldung)
		{
			Message::addError($meldung);
		}

		// Blättern: Gespeicherte Bilder fallen aus der Liste, die übrigen
		// rücken nach — die Seitenzahl wird deshalb bei jedem Aufruf begrenzt
		$seiten = max(1, (int) ceil(\count($kandidaten) / self::BILDER_JE_SEITE));
		$seite = max(1, min($seiten, (int) Input::get('seite')));
		$bilder = array();
		$unlesbar = array();

		foreach (\array_slice($kandidaten, ($seite - 1) * self::BILDER_JE_SEITE, self::BILDER_JE_SEITE) as $kandidat)
		{
			$vorschlag = Bildteil::vorschlag($kandidat['path'], self::EDITOR_KANTE);

			if (null === $vorschlag)
			{
				$unlesbar[] = $kandidat['path'];
				continue;
			}

			$bilder[] = array('id' => $kandidat['id'], 'path' => $kandidat['path']) + $vorschlag;
		}

		// Der Editor ist reines JavaScript ohne Abhängigkeit von MooTools
		// (Contao 4.13) oder Stimulus (Contao 5)
		$GLOBALS['TL_JAVASCRIPT'][] = 'bundles/contaometadaten/js/bildteile.js';

		$template = new BackendTemplate('be_metadaten_bildteile');
		$template->texte = $texte;
		$template->titel = (string) $row['titel'];
		$template->ordner = '' !== $ordnerPfad ? $ordnerPfad : ($GLOBALS['TL_LANG']['tl_metadaten']['alleDateien'] ?? 'alle Dateien');
		$template->anzahl = \count($kandidaten);
		$template->bilder = $bilder;
		$template->unlesbar = $unlesbar;
		$template->seite = $seite;
		$template->seiten = $seiten;
		$template->urlZurueckSeite = $seite > 1 ? $this->backendUrl(array('do' => 'metadaten', 'key' => 'bildteile', 'id' => $id, 'seite' => $seite - 1)) : '';
		$template->urlNaechsteSeite = $seite < $seiten ? $this->backendUrl(array('do' => 'metadaten', 'key' => 'bildteile', 'id' => $id, 'seite' => $seite + 1)) : '';
		$template->mindestgroesse = Bildteil::MINDESTGROESSE;
		$template->meldungen = Message::generate();
		$template->requestToken = Helfer::requestToken();
		$template->action = StringUtil::ampersand($request->getRequestUri());
		$template->zurueck = $this->listenUrl();
		$template->vorschauUrl = $this->backendUrl(array('do' => 'metadaten', 'key' => 'vorschau', 'id' => $id));
		$template->formular = self::FORMULAR_BILDTEILE;

		return $template->parse();
	}

	/**
	 * Lädt den Auftrag, dessen ID in der Adresse steht.
	 *
	 * @return array<string, mixed> Der Datensatz aus tl_metadaten
	 *
	 * @throws RedirectResponseException zur Auftragsliste, wenn es den Auftrag nicht gibt
	 */
	private function auftragszeile(): array
	{
		$id = (int) Input::get('id');
		$model = $id > 0 ? MetadatenModel::findByPk($id) : null;

		if (null === $model)
		{
			Message::addError($GLOBALS['TL_LANG']['METADATEN']['fehler']['auftragUnbekannt'] ?? 'auftragUnbekannt');

			throw new RedirectResponseException($this->listenUrl());
		}

		return $model->row();
	}

	/**
	 * Speichert die im Bildteil-Editor von Hand gesetzten wichtigen Teile.
	 *
	 * Angenommen werden nur Bilder, die zur Dateiauswahl des Auftrags gehören
	 * und noch keinen wichtigen Teil haben — eine manipulierte Formulareingabe
	 * kann also weder fremde Dateien erreichen noch bestehende Markierungen
	 * überschreiben. Jede Datei bekommt eine Version.
	 *
	 * @param array<int, array{id: int, path: string}> $kandidaten Bilder ohne wichtigen Teil aus der Dateiauswahl
	 *
	 * @return array{gespeichert: int, abgewiesen: int} Zahl der gespeicherten Bilder
	 *         und der angehakten Bilder mit unbrauchbaren Werten
	 */
	private function speichereBildteile(array $kandidaten): array
	{
		$erlaubt = array_column($kandidaten, 'path', 'id');
		$teile = (array) Input::post('teil');
		$auftraege = array();
		$abgewiesen = 0;

		foreach (array_keys((array) Input::post('uebernehmen')) as $id)
		{
			$id = (int) $id;

			if (!isset($erlaubt[$id]))
			{
				continue;
			}

			$teil = Bildteil::bereinigen($teile[$id] ?? null);

			if (null === $teil)
			{
				++$abgewiesen;
				continue;
			}

			$auftraege[$id] = array('path' => $erlaubt[$id], 'meta' => null, 'teil' => $teil);
		}

		$this->schreiben($auftraege);

		return array('gespeichert' => \count($auftraege), 'abgewiesen' => $abgewiesen);
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
			->prepare('SELECT id, path, extension, meta, importantPartWidth, importantPartHeight FROM tl_files WHERE ' . implode(' AND ', $bedingungen) . ' ORDER BY path')
			// Mit ... entpacken: Contao 5 reicht ein übergebenes Feld nicht mehr
			// als Parameterliste durch, sondern als einen einzigen Parameter —
			// die Abfrage fände dort keine einzige Datei
			->execute(...$parameter);

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
	 * Führt den Auftrag aus: Metadaten schreiben und wichtige Bildteile markieren.
	 *
	 * Die Metadaten aller Dateien werden immer vollständig geschrieben, das
	 * ist reine Datenbankarbeit. Die Bildanalyse dagegen kostet je Bild
	 * Rechenzeit (Vorschaubild erzeugen) und läuft deshalb nur, solange das
	 * Zeitbudget reicht. Was übrig bleibt, holt ein erneutes Ausführen nach:
	 * Erledigte Bilder haben dann einen wichtigen Teil und sind keine
	 * Kandidaten mehr.
	 *
	 * Hat eine Datei sowohl neue Metadaten als auch einen neuen wichtigen
	 * Teil, entsteht daraus eine einzige Änderung mit einer Version.
	 *
	 * @param array<int, array{id: int, path: string, neu: array, unterschiede: array}> $aenderungen Änderungen aus aenderungen()
	 * @param array<int, array{id: int, path: string}>                                  $kandidaten  Bilder ohne wichtigen Teil
	 *
	 * @return array{metadaten: int, markiert: int, versucht: int, dateien: string[]}
	 *         Anzahl der Dateien mit geänderten Metadaten, der markierten und
	 *         der insgesamt untersuchten Bilder sowie die Pfade aller
	 *         geänderten Dateien
	 */
	private function ausfuehren(array $aenderungen, array $kandidaten): array
	{
		$auftraege = array();

		foreach ($aenderungen as $aenderung)
		{
			$auftraege[$aenderung['id']] = array('path' => $aenderung['path'], 'meta' => $aenderung['neu'], 'teil' => null);
		}

		$ende = microtime(true) + $this->zeitbudget();
		$versucht = 0;
		$markiert = 0;

		foreach ($kandidaten as $kandidat)
		{
			if (microtime(true) >= $ende)
			{
				break;
			}

			++$versucht;
			$fund = Bildteil::ermitteln($kandidat['path']);

			if (null === $fund)
			{
				continue;
			}

			++$markiert;

			if (!isset($auftraege[$kandidat['id']]))
			{
				$auftraege[$kandidat['id']] = array('path' => $kandidat['path'], 'meta' => null, 'teil' => null);
			}

			$auftraege[$kandidat['id']]['teil'] = $fund['teil'];
		}

		$this->schreiben($auftraege);

		return array(
			'metadaten' => \count($aenderungen),
			'markiert'  => $markiert,
			'versucht'  => $versucht,
			'dateien'   => array_column($auftraege, 'path'),
		);
	}

	/**
	 * Schreibt die Änderungen in tl_files.
	 *
	 * Vor jeder Änderung wird mit Contaos Versions-Klasse der bisherige Stand
	 * gesichert und danach eine neue Version angelegt — so lässt sich jede
	 * Datei in der Dateiverwaltung über die Versionsauswahl zurücksetzen,
	 * genau wie nach einer Bearbeitung von Hand. tl_files hat die
	 * Versionierung im Kern eingeschaltet.
	 *
	 * @param array<int, array{path: string, meta: array|null, teil: array|null}> $auftraege
	 *        Je Datei-ID die neuen Metadaten und/oder der neue wichtige Teil;
	 *        null heißt „unverändert lassen“
	 */
	private function schreiben(array $auftraege): void
	{
		$db = Database::getInstance();

		foreach ($auftraege as $id => $auftrag)
		{
			$werte = array('tstamp' => time());

			if (null !== $auftrag['meta'])
			{
				$werte['meta'] = serialize($auftrag['meta']);
			}

			if (null !== $auftrag['teil'])
			{
				// Als Text mit Dezimalpunkt übergeben: Unter PHP 7.4 hängt die
				// Umwandlung von Kommazahlen in Text von der Locale ab und
				// könnte sonst „0,4583“ in die Abfrage schreiben
				$werte['importantPartX'] = number_format($auftrag['teil']['x'], 4, '.', '');
				$werte['importantPartY'] = number_format($auftrag['teil']['y'], 4, '.', '');
				$werte['importantPartWidth'] = number_format($auftrag['teil']['width'], 4, '.', '');
				$werte['importantPartHeight'] = number_format($auftrag['teil']['height'], 4, '.', '');
			}

			$versionen = new Versions('tl_files', $id);
			$versionen->initialize();

			$db->prepare('UPDATE tl_files %s WHERE id=?')
				->set($werte)
				->execute($id);

			$versionen->create();
		}
	}

	/**
	 * Legt fest, wie viele Sekunden die Bildanalyse beim Ausführen laufen darf.
	 *
	 * Höchstens 20 Sekunden, und nie mehr als die Hälfte der erlaubten
	 * Laufzeit des Skripts — die andere Hälfte bleibt für das Schreiben der
	 * Versionen und den Seitenaufbau.
	 *
	 * @return float Zeitbudget in Sekunden
	 */
	private function zeitbudget(): float
	{
		$grenze = (int) \ini_get('max_execution_time');

		if ($grenze <= 0)
		{
			return self::ZEITBUDGET;
		}

		return min(self::ZEITBUDGET, $grenze / 2);
	}

	/**
	 * Schätzt für die ersten Kandidaten den wichtigen Teil, damit die Vorschau Beispiele zeigen kann.
	 *
	 * Gespeichert wird dabei nichts außer den Vorschaubildern im Bildcache.
	 * Die Zahl der Beispiele und die Rechenzeit sind begrenzt, damit die
	 * Vorschau auch bei tausenden Bildern zügig erscheint.
	 *
	 * @param array<int, array{id: int, path: string}> $kandidaten Bilder ohne wichtigen Teil
	 *
	 * @return array<int, array{path: string, url: string, teil: array{x: float, y: float, width: float, height: float}}>
	 *         Die Beispiele mit Adresse des Vorschaubildes und wichtigem Teil
	 */
	private function beispiele(array $kandidaten): array
	{
		$liste = array();
		$ende = microtime(true) + self::ZEITBUDGET / 2;

		foreach ($kandidaten as $kandidat)
		{
			if (\count($liste) >= self::BEISPIELE_MAX || microtime(true) >= $ende)
			{
				break;
			}

			$fund = Bildteil::ermitteln($kandidat['path']);

			if (null !== $fund)
			{
				$liste[] = array('path' => $kandidat['path'], 'url' => $fund['url'], 'teil' => $fund['teil']);
			}
		}

		return $liste;
	}

	/**
	 * Meldet dem Benutzer, was die Ausführung bewirkt hat.
	 *
	 * @param array{metadaten: int, markiert: int, versucht: int, dateien: string[]} $geschrieben Ergebnis von ausfuehren()
	 * @param int                                                                   $kandidaten  Zahl der Bilder ohne wichtigen Teil vor der Ausführung
	 */
	private function meldeErgebnis(array $geschrieben, int $kandidaten): void
	{
		$texte = $GLOBALS['TL_LANG']['METADATEN'] ?? array();

		if ($geschrieben['metadaten'] > 0)
		{
			Message::addConfirmation(sprintf($texte['erledigt'] ?? '%d Dateien geändert.', $geschrieben['metadaten']));
		}

		if ($geschrieben['markiert'] > 0)
		{
			Message::addConfirmation(sprintf($texte['markiert'] ?? '%d Bilder markiert.', $geschrieben['markiert']));
		}

		$ohneErgebnis = $geschrieben['versucht'] - $geschrieben['markiert'];

		if ($ohneErgebnis > 0)
		{
			Message::addInfo(sprintf($texte['nichtAuswertbar'] ?? '%d Bilder nicht auswertbar.', $ohneErgebnis));
		}

		$offen = $kandidaten - $geschrieben['versucht'];

		if ($offen > 0)
		{
			Message::addInfo(sprintf($texte['nochOffen'] ?? '%d Bilder noch offen.', $offen));
		}
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
			array('label' => $lang['modus'][0] ?? 'Betriebsart', 'wert' => (string) ($lang['modusOptionen'][$auftrag->modus] ?? $auftrag->modus)),
			array('label' => $lang['wichtigerTeil'][0] ?? 'Wichtigen Bildteil markieren', 'wert' => $auftrag->wichtigerTeil ? $ja : $nein),
		);

		// Ohne Änderung der Metadaten gibt es weder Sprache noch Felder zu zeigen
		if (Auftrag::MODUS_KEINE === $auftrag->modus)
		{
			return $zeilen;
		}

		$zeilen[] = array('label' => $lang['sprache'][0] ?? 'Sprache', 'wert' => '' !== $auftrag->sprache ? $auftrag->sprache : ($lang['alleSprachen'] ?? 'alle'));
		$zeilen[] = array('label' => $lang['felder'][0] ?? 'Felder', 'wert' => implode(', ', $felder));

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
