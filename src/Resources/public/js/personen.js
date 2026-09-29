/*
 * Personenerkennung für den Bildteil-Editor des Metadaten-Bundles.
 *
 * Sucht mit MediaPipe (Objekterkennung EfficientDet-Lite0, Kategorie
 * „person“) die Personen in jedem Vorschaubild und verbindet sie mit der
 * Schärfekarte, die der Server mitliefert:
 *
 *   1. Nur sichere und große Personen zählen (Güte ab GUETE, Höhe ab
 *      HOEHE der Bildhöhe).
 *   2. Je Person wird die Schärfe im oberen Teil ihres Rechtecks gemessen
 *      (Anteil OBERER_TEIL), also an Kopf und Oberkörper.
 *   3. Die schärfste Person ist die Hauptperson. Mitgenommen wird jede
 *      weitere Person, die mindestens HOEHE_REL so groß und SCHAERFE_REL so
 *      scharf ist wie sie; so kommt der Nachbar am selben Brett dazu, der
 *      unscharfe Hintergrund und der unscharfe Gegner im Vordergrund nicht.
 *   4. Das Rechteck umfasst diese Personen und den schmalen Vorschlag des
 *      Servers, seitlich um ZUGABE erweitert; die Höhe ist fest (OBEN bis
 *      UNTEN).
 *
 * Die Werte sind an 206 von Hand markierten Turnierfotos gemessen (siehe
 * README): mittlere Überdeckung 0,77 gegenüber 0,75 mit der Schärfe allein,
 * die Hälfte der Vorschläge liegt ab 0,8 statt 41 Prozent.
 *
 * Datenschutz: MediaPipe schickt von sich aus Nutzungskennzahlen an Google
 * (Betriebssystem, Version, Rechenzeiten; keine Bilder). Das unterbindet
 * dieses Skript, indem es Aufrufe an odml.pa.googleapis.com abweist, bevor
 * MediaPipe geladen wird; nach dem ersten Fehlschlag stellt MediaPipe den
 * Versand selbst ein. Die MediaPipe-Dateien bleiben dafür unverändert.
 *
 * Findet sich keine Person oder scheitert MediaPipe (etwa ein Browser ohne
 * WebAssembly-SIMD), bleibt der Vorschlag des Servers stehen. Ein von Hand
 * verändertes Rechteck wird nie angefasst.
 *
 * Schnittstelle zum Template (be_metadaten_bildteile.html5):
 *
 *   [data-metadaten-mediapipe]  Element mit data-wasm (Verzeichnis der
 *                               WebAssembly-Dateien) und data-modell
 *   [data-metadaten-karte]      Karte eines Bildes
 *   [data-metadaten-editor]     data-schmal (Vorschlag „ein Spieler“) und
 *                               data-schaerfe (Schärfekarte: Zeilen durch „;“,
 *                               Werte durch „,“ getrennt, über dem Grundpegel)
 *   [data-metadaten-fund]       Zeile für das Ergebnis; Texte in data-text-suche,
 *                               data-text-eins, data-text-viele (mit %d),
 *                               data-text-keins
 *   data-personen               wird an der Karte gesetzt: Zahl der Personen,
 *                               zugleich das Kennzeichen „schon untersucht“
 */
(function () {
	'use strict';

	var GUETE = 0.5;
	var HOEHE = 0.3;
	var OBERER_TEIL = 0.4;
	var HOEHE_REL = 0.6;
	var SCHAERFE_REL = 0.5;
	var ZUGABE = 0.06;
	var OBEN = 0.02;
	var UNTEN = 0.96;

	// Versandsperre einmal einrichten, vor jedem Laden von MediaPipe
	if (!window.MetadatenVersandsperre) {
		var original = window.fetch;

		window.MetadatenVersandsperre = {gesperrt: 0};
		window.fetch = function (eingabe) {
			var adresse = typeof eingabe === 'string' ? eingabe : (eingabe && eingabe.url) || String(eingabe || '');

			if (/^https?:\/\/odml\.pa\.googleapis\.com\//i.test(adresse)) {
				window.MetadatenVersandsperre.gesperrt++;

				return Promise.reject(new TypeError('Versand von Nutzungsdaten an Google ist gesperrt'));
			}

			return original.apply(this, arguments);
		};
	}

	if (!window.MetadatenPersonen) {
		/** Versprechen auf den Detektor, je Seite nur einmal erzeugt */
		var detektor = null;

		var ladeDetektor = function (quelle) {
			if (!detektor) {
				var wasm = new URL(quelle.getAttribute('data-wasm'), document.baseURI).href;
				var modell = new URL(quelle.getAttribute('data-modell'), document.baseURI).href;

				detektor = window.Vision.FilesetResolver.forVisionTasks(wasm).then(function (dateien) {
					return window.Vision.ObjectDetector.createFromOptions(dateien, {
						baseOptions: {modelAssetPath: modell, delegate: 'CPU'},
						runningMode: 'IMAGE',
						scoreThreshold: GUETE,
						maxResults: 15,
						categoryAllowlist: ['person']
					});
				});

				// Ein Fehlschlag soll beim nächsten Aufruf neu versucht werden
				detektor.catch(function () { detektor = null; });
			}

			return detektor;
		};

		var wennGeladen = function (bild) {
			return new Promise(function (erfuellt, abgelehnt) {
				if (bild.complete) {
					return bild.naturalWidth > 0 ? erfuellt() : abgelehnt(new Error('Bild nicht ladbar'));
				}

				bild.addEventListener('load', function () { erfuellt(); }, {once: true});
				bild.addEventListener('error', function () { abgelehnt(new Error('Bild nicht ladbar')); }, {once: true});
			});
		};

		/** Liest ein Rechteck aus einem Attribut der Form "x,y,width,height" */
		var rechteck = function (element, attribut) {
			var w = (element.getAttribute(attribut) || '').split(',').map(parseFloat);

			return w.length === 4 && !w.some(isNaN) ? {x: w[0], y: w[1], w: w[2], h: w[3]} : null;
		};

		/** Liest die Schärfekarte aus data-schaerfe */
		var schaerfekarte = function (editor) {
			var text = editor.getAttribute('data-schaerfe') || '';

			return text ? text.split(';').map(function (zeile) { return zeile.split(',').map(parseFloat); }) : null;
		};

		/** Mittlere Schärfe im oberen Teil eines Personenrechtecks (Bruchteile) */
		var personenSchaerfe = function (karte, p) {
			var zeilen = karte.length, spalten = karte[0].length;
			var x1 = Math.max(0, Math.floor(p.x * spalten)), x2 = Math.min(spalten - 1, Math.floor((p.x + p.w) * spalten));
			var y1 = Math.max(0, Math.floor(p.y * zeilen)), y2 = Math.min(zeilen - 1, Math.floor((p.y + p.h * OBERER_TEIL) * zeilen));
			var summe = 0, anzahl = 0;

			for (var y = y1; y <= y2; y++) {
				for (var x = x1; x <= x2; x++) {
					summe += karte[y][x] || 0;
					anzahl++;
				}
			}

			return anzahl ? summe / anzahl : 0;
		};

		/**
		 * Berechnet den Vorschlag aus Personen und Schärfe.
		 * Liefert null, wenn keine brauchbare Person gefunden wurde.
		 */
		var vorschlag = function (personen, karte, schmal) {
			var gross = personen.filter(function (p) { return p.h >= HOEHE; });

			if (!gross.length || !karte) {
				return null;
			}

			gross.forEach(function (p) { p.s = personenSchaerfe(karte, p); });
			gross.sort(function (a, b) { return b.s - a.s; });

			var haupt = gross[0];

			if (haupt.s <= 0) {
				return null;
			}

			var links = haupt.x, rechts = haupt.x + haupt.w;

			gross.slice(1).forEach(function (p) {
				if (p.h >= HOEHE_REL * haupt.h && p.s >= SCHAERFE_REL * haupt.s) {
					links = Math.min(links, p.x);
					rechts = Math.max(rechts, p.x + p.w);
				}
			});

			if (schmal) {
				links = Math.min(links, schmal.x);
				rechts = Math.max(rechts, schmal.x + schmal.w);
			}

			links = Math.max(0, links - ZUGABE);
			rechts = Math.min(1, rechts + ZUGABE);

			return {x: links, y: OBEN, w: rechts - links, h: UNTEN - OBEN};
		};

		var melde = function (karte, anzahl) {
			var zeile = karte.querySelector('[data-metadaten-fund]');

			karte.setAttribute('data-personen', anzahl < 0 ? 'fehler' : String(anzahl));

			if (!zeile) {
				return;
			}

			var text = anzahl < 0 ? '' : zeile.getAttribute(anzahl === 0 ? 'data-text-keins' : (anzahl === 1 ? 'data-text-eins' : 'data-text-viele')) || '';

			zeile.textContent = text.replace('%d', String(anzahl));
			zeile.classList.toggle('metadaten-fund-treffer', anzahl > 0);
		};

		var untersuche = function (karte, erkennung) {
			var editor = karte.querySelector('[data-metadaten-editor]');
			var bild = editor.querySelector('img');

			return wennGeladen(bild).then(function () {
				var b = bild.naturalWidth, h = bild.naturalHeight;
				var personen = erkennung.detect(bild).detections.map(function (d) {
					return {x: d.boundingBox.originX / b, y: d.boundingBox.originY / h, w: d.boundingBox.width / b, h: d.boundingBox.height / h};
				});
				var neu = vorschlag(personen, schaerfekarte(editor), rechteck(editor, 'data-schmal'));

				// Was der Benutzer inzwischen von Hand gesetzt hat, bleibt
				if (neu && !window.MetadatenBildteile.istGeaendert(karte)) {
					window.MetadatenBildteile.setzeVorschlag(karte, neu);
				}

				melde(karte, personen.filter(function (p) { return p.h >= HOEHE; }).length);
			}).catch(function () {
				melde(karte, -1);
			});
		};

		window.MetadatenPersonen = {
			/**
			 * Untersucht alle noch nicht untersuchten Karten der Seite, eine
			 * nach der anderen, damit die Seite bedienbar bleibt.
			 */
			suche: function (versuch) {
				var quelle = document.querySelector('[data-metadaten-mediapipe]');

				if (!quelle || !window.Promise || typeof WebAssembly !== 'object') {
					return;
				}

				// Nachträglich eingefügte Skripte (Turbo) laufen in der Reihenfolge
				// ihres Eintreffens: MediaPipe oder bildteile.js können noch fehlen
				if (!window.Vision || !window.MetadatenBildteile) {
					versuch = typeof versuch === 'number' ? versuch : 0;

					if (versuch < 40) {
						setTimeout(function () { window.MetadatenPersonen.suche(versuch + 1); }, 150);
					}

					return;
				}

				var karten = Array.prototype.filter.call(
					document.querySelectorAll('[data-metadaten-karte]'),
					function (karte) { return !karte.hasAttribute('data-personen'); }
				);

				if (!karten.length) {
					return;
				}

				karten.forEach(function (karte) {
					var zeile = karte.querySelector('[data-metadaten-fund]');

					karte.setAttribute('data-personen', 'laeuft');

					if (zeile) {
						zeile.textContent = zeile.getAttribute('data-text-suche') || '';
					}
				});

				ladeDetektor(quelle).then(function (erkennung) {
					return karten.reduce(function (kette, karte) {
						return kette.then(function () {
							return new Promise(function (weiter) { setTimeout(weiter, 0); });
						}).then(function () {
							return untersuche(karte, erkennung);
						});
					}, Promise.resolve());
				}).catch(function () {
					// Ohne MediaPipe bleibt es bei den Vorschlägen des Servers
					karten.forEach(function (karte) { melde(karte, -1); });
				});
			}
		};
	}

	// Bei jedem Einbinden suchen: Contao 5 wertet das Skript im Seiteninhalt
	// nach jedem Seitenwechsel mit Turbo erneut aus
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { window.MetadatenPersonen.suche(0); }, {once: true});
	} else {
		window.MetadatenPersonen.suche(0);
	}
})();
