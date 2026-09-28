/*
 * Gesichtserkennung für den Bildteil-Editor des Metadaten-Bundles.
 *
 * Sucht in jedem Vorschaubild des Editors nach Gesichtern und setzt den
 * Vorschlag für den wichtigen Bildteil auf die gefundenen Köpfe. Die
 * eigentliche Erkennung leistet pico.js von Nenad Markuš (MIT-Lizenz, liegt
 * unverändert in vendor/); dieses Skript bereitet die Bilder auf, rechnet
 * die Funde in ein Rechteck um und reicht es an bildteile.js weiter.
 *
 * Alles geschieht im Browser. Es wird kein Bild und kein Ergebnis an einen
 * fremden Server geschickt; auch die Erkennungsdaten (facefinder.bin) kommen
 * aus dem Bundle.
 *
 * Ablauf je Bild:
 *   1. Bild auf höchstens KANTE Pixel in eine unsichtbare Zeichenfläche malen
 *   2. in Graustufen umrechnen und die Kaskade darüber laufen lassen
 *   3. Funde bündeln, schwache Funde verwerfen
 *   4. um jedes Gesicht Platz für Haare und Kinn zugeben, alle Köpfe mit
 *      einem Rechteck umschließen
 *
 * Findet sich kein Gesicht, bleibt der Vorschlag des Servers (Schätzung aus
 * dem Bildinhalt) stehen. Hat der Benutzer ein Rechteck schon von Hand
 * verändert, wird es nicht mehr angefasst.
 *
 * Schnittstelle zum Template (be_metadaten_bildteile.html5):
 *
 *   [data-metadaten-kaskade]   Element mit der Adresse der Erkennungsdaten
 *   [data-metadaten-karte]     Karte eines Bildes
 *   [data-metadaten-fund]      Zeile für das Ergebnis; die Texte stehen in
 *                              data-text-suche, data-text-eins,
 *                              data-text-viele (mit %d) und data-text-keins
 *   data-gesichter             wird an der Karte gesetzt: Zahl der Funde,
 *                              zugleich das Kennzeichen „schon untersucht“
 */
(function () {
	'use strict';

	/** Längste Kante der Zeichenfläche; größere Bilder werden verkleinert */
	var KANTE = 800;

	/**
	 * Mindestgüte des besten Fundes. Der Wert ist ein Erfahrungswert des
	 * Autors von pico.js für diese Kaskade und Einzelbilder.
	 */
	var GUETE = 5.0;

	/**
	 * Mindestgüte jedes weiteren Fundes, absolut und als Anteil des besten.
	 *
	 * Strenger als GUETE, weil ein Fehlfund das Rechteck weit vom Kopf
	 * wegzieht: Im Prüfbild erreichte ein Gesicht mit Brille und geneigtem
	 * Kopf nur Güte 8,8, ein Fehlfund an den Händen 5,3. Eine einzige
	 * Schwelle kann beide nicht trennen. Deshalb gilt: Der beste Fund zählt
	 * ab GUETE, jeder weitere nur, wenn er für sich genommen sicher ist.
	 * Der Preis: Auf Gruppenfotos können schwach erkannte Köpfe fehlen, das
	 * Rechteck wird dann von Hand erweitert.
	 */
	var GUETE_WEITERE = 20.0;
	var ANTEIL_WEITERE = 0.15;

	/** Kleinstes gesuchtes Gesicht als Anteil der kürzeren Bildkante */
	var KLEINSTES = 0.06;

	/** Zugabe um das erkannte Gesicht, jeweils als Anteil seiner Größe */
	var ZUGABE = {oben: 0.55, unten: 0.35, seite: 0.35};

	if (!window.MetadatenGesichter) {
		/** Versprechen auf die entpackte Kaskade, je Adresse nur einmal geladen */
		var kaskaden = {};

		var ladeKaskade = function (adresse) {
			if (!kaskaden[adresse]) {
				kaskaden[adresse] = fetch(adresse, {credentials: 'same-origin'})
					.then(function (antwort) {
						if (!antwort.ok) {
							throw new Error('Erkennungsdaten nicht ladbar: ' + antwort.status);
						}

						return antwort.arrayBuffer();
					})
					.then(function (puffer) {
						return window.pico.unpack_cascade(new Int8Array(puffer));
					});
			}

			return kaskaden[adresse];
		};

		/** Wartet, bis das Bild geladen ist; schlägt bei einem defekten Bild fehl */
		var wennGeladen = function (bild) {
			return new Promise(function (erfuellt, abgelehnt) {
				if (bild.complete) {
					return bild.naturalWidth > 0 ? erfuellt() : abgelehnt(new Error('Bild nicht ladbar'));
				}

				bild.addEventListener('load', function () { erfuellt(); }, {once: true});
				bild.addEventListener('error', function () { abgelehnt(new Error('Bild nicht ladbar')); }, {once: true});
			});
		};

		/**
		 * Sucht die Gesichter eines Bildes.
		 *
		 * Liefert eine Liste von Funden {x, y, g}: Mittelpunkt und Größe des
		 * Gesichts als Bruchteile von Bildbreite bzw. Bildhöhe (g doppelt,
		 * als gx und gy, weil das Gesicht im Pixelraum quadratisch ist).
		 */
		var sucheGesichter = function (bild, kaskade) {
			var faktor = Math.min(1, KANTE / Math.max(bild.naturalWidth, bild.naturalHeight));
			var breite = Math.max(1, Math.round(bild.naturalWidth * faktor));
			var hoehe = Math.max(1, Math.round(bild.naturalHeight * faktor));
			var flaeche = document.createElement('canvas');

			flaeche.width = breite;
			flaeche.height = hoehe;

			var stift = flaeche.getContext('2d', {willReadFrequently: true});

			stift.drawImage(bild, 0, 0, breite, hoehe);

			var rgba = stift.getImageData(0, 0, breite, hoehe).data;
			var grau = new Uint8Array(breite * hoehe);

			for (var i = 0, n = breite * hoehe; i < n; ++i) {
				grau[i] = (2 * rgba[4 * i] + 7 * rgba[4 * i + 1] + rgba[4 * i + 2]) / 10;
			}

			var kurz = Math.min(breite, hoehe);
			var funde = window.pico.run_cascade(
				{pixels: grau, nrows: hoehe, ncols: breite, ldim: breite},
				kaskade,
				{shiftfactor: 0.1, minsize: Math.max(20, Math.round(kurz * KLEINSTES)), maxsize: kurz, scalefactor: 1.1}
			);

			var gebuendelt = window.pico.cluster_detections(funde, 0.2)
				.sort(function (a, b) { return b[3] - a[3]; });
			var beste = gebuendelt.length > 0 ? gebuendelt[0][3] : 0;

			return gebuendelt
				.filter(function (fund, nr) {
					return nr === 0
						? fund[3] > GUETE
						: fund[3] > GUETE_WEITERE && fund[3] >= beste * ANTEIL_WEITERE;
				})
				.map(function (fund) {
					return {x: fund[1] / breite, y: fund[0] / hoehe, gx: fund[2] / breite, gy: fund[2] / hoehe, guete: fund[3]};
				});
		};

		/** Umschließt alle Köpfe mit einem Rechteck in Bruchteilen der Bildgröße */
		var umschliesse = function (gesichter) {
			var links = 1, oben = 1, rechts = 0, unten = 0;

			gesichter.forEach(function (g) {
				links = Math.min(links, g.x - g.gx * (0.5 + ZUGABE.seite));
				rechts = Math.max(rechts, g.x + g.gx * (0.5 + ZUGABE.seite));
				oben = Math.min(oben, g.y - g.gy * (0.5 + ZUGABE.oben));
				unten = Math.max(unten, g.y + g.gy * (0.5 + ZUGABE.unten));
			});

			links = Math.max(0, links);
			oben = Math.max(0, oben);
			rechts = Math.min(1, rechts);
			unten = Math.min(1, unten);

			return {x: links, y: oben, w: rechts - links, h: unten - oben};
		};

		var melde = function (karte, anzahl) {
			var zeile = karte.querySelector('[data-metadaten-fund]');

			karte.setAttribute('data-gesichter', anzahl < 0 ? 'fehler' : String(anzahl));

			if (!zeile) {
				return;
			}

			var text = anzahl < 0 ? '' : zeile.getAttribute(anzahl === 0 ? 'data-text-keins' : (anzahl === 1 ? 'data-text-eins' : 'data-text-viele')) || '';

			zeile.textContent = text.replace('%d', String(anzahl));
			zeile.classList.toggle('metadaten-fund-treffer', anzahl > 0);
		};

		var untersuche = function (karte, kaskade) {
			var bild = karte.querySelector('[data-metadaten-editor] img');

			return wennGeladen(bild).then(function () {
				var gesichter = sucheGesichter(bild, kaskade);

				// Was der Benutzer inzwischen von Hand gesetzt hat, bleibt
				if (gesichter.length > 0 && !window.MetadatenBildteile.istGeaendert(karte)) {
					window.MetadatenBildteile.setzeVorschlag(karte, umschliesse(gesichter));
				}

				melde(karte, gesichter.length);
			}).catch(function () {
				melde(karte, -1);
			});
		};

		window.MetadatenGesichter = {
			/**
			 * Untersucht alle noch nicht untersuchten Karten der Seite,
			 * eine nach der anderen, damit die Seite bedienbar bleibt.
			 */
			suche: function (versuch) {
				var quelle = document.querySelector('[data-metadaten-kaskade]');

				if (!quelle || !window.fetch || !window.Promise) {
					return;
				}

				// Nachträglich eingefügte Skripte (Turbo) laufen in der
				// Reihenfolge ihres Eintreffens, nicht in der des Templates:
				// pico.js oder bildteile.js können noch unterwegs sein
				if (!window.pico || !window.MetadatenBildteile) {
					versuch = typeof versuch === 'number' ? versuch : 0;

					if (versuch < 40) {
						setTimeout(function () { window.MetadatenGesichter.suche(versuch + 1); }, 150);
					}

					return;
				}

				var karten = Array.prototype.filter.call(
					document.querySelectorAll('[data-metadaten-karte]'),
					function (karte) { return !karte.hasAttribute('data-gesichter'); }
				);

				if (karten.length === 0) {
					return;
				}

				karten.forEach(function (karte) {
					var zeile = karte.querySelector('[data-metadaten-fund]');

					karte.setAttribute('data-gesichter', 'laeuft');

					if (zeile) {
						zeile.textContent = zeile.getAttribute('data-text-suche') || '';
					}
				});

				ladeKaskade(quelle.getAttribute('data-metadaten-kaskade')).then(function (kaskade) {
					return karten.reduce(function (kette, karte) {
						return kette.then(function () {
							return new Promise(function (weiter) { setTimeout(weiter, 0); });
						}).then(function () {
							return untersuche(karte, kaskade);
						});
					}, Promise.resolve());
				}).catch(function () {
					// Ohne Erkennungsdaten bleibt es bei den Vorschlägen des Servers
					karten.forEach(function (karte) { melde(karte, -1); });
				});
			}
		};
	}

	// Bei jedem Einbinden suchen: Contao 5 wertet das Skript im Seiteninhalt
	// nach jedem Seitenwechsel mit Turbo erneut aus
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { window.MetadatenGesichter.suche(0); }, {once: true});
	} else {
		window.MetadatenGesichter.suche(0);
	}
})();
