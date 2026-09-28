/*
 * Bildteil-Editor des Metadaten-Bundles.
 *
 * Macht das Rechteck des wichtigen Bildteils auf jeder Karte beweglich:
 *
 *   - am Rechteck ziehen        verschieben
 *   - an einer Ecke ziehen      Größe ändern
 *   - daneben ins Bild ziehen   neues Rechteck aufziehen
 *   - Pfeiltasten               verschieben, mit Umschalttaste Größe ändern
 *
 * Das Skript kommt ohne Bibliothek aus und hängt sich nur an document. Damit
 * läuft es unter Contao 4.13 (MooTools) wie unter Contao 5 (Stimulus und
 * Turbo): Turbo tauscht beim Seitenwechsel den Seiteninhalt aus, ohne das
 * Skript neu zu laden — Ereignisse an document überleben das, eine
 * Initialisierung je Element gäbe es dann nicht.
 *
 * Schnittstelle zum Template (be_metadaten_bildteile.html5):
 *
 *   [data-metadaten-karte]    Karte eines Bildes
 *   [data-metadaten-editor]   Bildfläche; data-min = Mindestgröße,
 *                             data-vorschlag = "x,y,width,height"
 *   [data-metadaten-teil]     das Rechteck
 *   [data-ecke]               Griff, Wert nw | ne | sw | se
 *   input[data-feld]          versteckte Felder x, y, width, height
 *   [data-metadaten-haken]    Häkchen „übernehmen“
 *   [data-metadaten-zurueck]  Knopf „Vorschlag wiederherstellen“
 *   [data-metadaten-alle]     Knopf, Wert 1 = alle anhaken, 0 = keines
 *
 * Alle Werte sind Bruchteile der Bildgröße von 0 bis 1.
 */
(function () {
	'use strict';

	// Das Skript darf mehrfach eingebunden werden, aber nur einmal wirken
	if (window.MetadatenBildteile) {
		return;
	}

	// Schnittstelle für gesichter.js: einen neuen Vorschlag setzen, ohne die
	// Karte als von Hand geändert zu kennzeichnen. Die Funktionen selbst
	// stehen weiter unten; Funktionsdeklarationen gelten im ganzen Block.
	window.MetadatenBildteile = {
		setzeVorschlag: function (karte, r) {
			var editor = karte.querySelector('[data-metadaten-editor]');

			schreibe(karte, r, false);

			// Nach dem Schreiben stehen in den Feldern die begrenzten Werte
			var g = lies(karte);
			editor.setAttribute('data-vorschlag', [g.x, g.y, g.w, g.h].map(function (w) { return w.toFixed(4); }).join(','));
		},
		istGeaendert: function (karte) {
			return karte.classList.contains('metadaten-geaendert');
		}
	};

	/** Laufender Zug mit der Maus oder dem Finger, sonst null */
	var zug = null;

	/** Schrittweite der Pfeiltasten */
	var SCHRITT = 0.01;

	/** Ab dieser Strecke gilt ein Ziehen im freien Bild als neues Rechteck */
	var SCHWELLE = 0.01;

	function begrenze(wert, von, bis) {
		return Math.min(bis, Math.max(von, wert));
	}

	function feld(karte, name) {
		return karte.querySelector('input[data-feld="' + name + '"]');
	}

	function mindestgroesse(editor) {
		var wert = parseFloat(editor.getAttribute('data-min'));

		return isNaN(wert) ? 0.02 : wert;
	}

	/** Liest das Rechteck aus den versteckten Feldern der Karte */
	function lies(karte) {
		return {
			x: parseFloat(feld(karte, 'x').value) || 0,
			y: parseFloat(feld(karte, 'y').value) || 0,
			w: parseFloat(feld(karte, 'width').value) || 0,
			h: parseFloat(feld(karte, 'height').value) || 0
		};
	}

	/**
	 * Schreibt das Rechteck in Felder und Darstellung.
	 *
	 * Bringt es vorher auf Mindestgröße und ins Bild. Mit geaendert = true
	 * wird zugleich das Häkchen „übernehmen“ gesetzt: Wer ein Rechteck
	 * anfasst, will es auch speichern.
	 */
	function schreibe(karte, r, geaendert) {
		var editor = karte.querySelector('[data-metadaten-editor]');
		var teil = karte.querySelector('[data-metadaten-teil]');
		var min = mindestgroesse(editor);

		r.w = begrenze(r.w, min, 1);
		r.h = begrenze(r.h, min, 1);
		r.x = begrenze(r.x, 0, 1 - r.w);
		r.y = begrenze(r.y, 0, 1 - r.h);

		feld(karte, 'x').value = r.x.toFixed(4);
		feld(karte, 'y').value = r.y.toFixed(4);
		feld(karte, 'width').value = r.w.toFixed(4);
		feld(karte, 'height').value = r.h.toFixed(4);

		teil.style.left = (r.x * 100).toFixed(2) + '%';
		teil.style.top = (r.y * 100).toFixed(2) + '%';
		teil.style.width = (r.w * 100).toFixed(2) + '%';
		teil.style.height = (r.h * 100).toFixed(2) + '%';

		if (geaendert) {
			hake(karte, true);
			karte.classList.add('metadaten-geaendert');
		}
	}

	function hake(karte, an) {
		var haken = karte.querySelector('[data-metadaten-haken]');

		if (haken) {
			haken.checked = an;
		}

		karte.classList.toggle('metadaten-angehakt', an);
	}

	/** Position des Zeigers als Bruchteil der Bildfläche */
	function zeiger(ereignis, flaeche) {
		return {
			x: begrenze((ereignis.clientX - flaeche.left) / flaeche.width, 0, 1),
			y: begrenze((ereignis.clientY - flaeche.top) / flaeche.height, 0, 1)
		};
	}

	/** Rechteck zwischen zwei Punkten, unabhängig von der Ziehrichtung */
	function zwischen(a, b) {
		return {
			x: Math.min(a.x, b.x),
			y: Math.min(a.y, b.y),
			w: Math.abs(a.x - b.x),
			h: Math.abs(a.y - b.y)
		};
	}

	document.addEventListener('pointerdown', function (ereignis) {
		var editor = ereignis.target.closest ? ereignis.target.closest('[data-metadaten-editor]') : null;

		if (!editor || ereignis.button !== 0) {
			return;
		}

		var karte = editor.closest('[data-metadaten-karte]');
		var flaeche = editor.getBoundingClientRect();

		if (!karte || flaeche.width === 0 || flaeche.height === 0) {
			return;
		}

		var r = lies(karte);
		var griff = ereignis.target.closest('[data-ecke]');
		var art = 'neu';
		var anker = null;

		if (griff) {
			// Beim Ziehen an einer Ecke bleibt die gegenüberliegende Ecke stehen
			art = 'ecke';
			var ecke = griff.getAttribute('data-ecke');

			anker = {
				x: ecke.indexOf('w') !== -1 ? r.x + r.w : r.x,
				y: ecke.indexOf('n') !== -1 ? r.y + r.h : r.y
			};
		} else if (ereignis.target.closest('[data-metadaten-teil]')) {
			art = 'verschieben';
		}

		zug = {
			karte: karte,
			editor: editor,
			flaeche: flaeche,
			art: art,
			anker: anker,
			start: zeiger(ereignis, flaeche),
			ausgang: r,
			begonnen: art !== 'neu'
		};

		editor.setPointerCapture(ereignis.pointerId);
		editor.classList.add('metadaten-zieht');
		ereignis.preventDefault();
	});

	document.addEventListener('pointermove', function (ereignis) {
		if (!zug) {
			return;
		}

		var p = zeiger(ereignis, zug.flaeche);
		var r;

		if (zug.art === 'verschieben') {
			r = {
				x: zug.ausgang.x + p.x - zug.start.x,
				y: zug.ausgang.y + p.y - zug.start.y,
				w: zug.ausgang.w,
				h: zug.ausgang.h
			};
		} else if (zug.art === 'ecke') {
			r = zwischen(zug.anker, p);
		} else {
			// Ein bloßer Klick ins Bild soll den Vorschlag nicht zerstören
			if (!zug.begonnen && Math.abs(p.x - zug.start.x) < SCHWELLE && Math.abs(p.y - zug.start.y) < SCHWELLE) {
				return;
			}

			zug.begonnen = true;
			r = zwischen(zug.start, p);
		}

		schreibe(zug.karte, r, true);
		ereignis.preventDefault();
	});

	function beende(ereignis) {
		if (!zug) {
			return;
		}

		zug.editor.classList.remove('metadaten-zieht');

		if (zug.editor.hasPointerCapture && zug.editor.hasPointerCapture(ereignis.pointerId)) {
			zug.editor.releasePointerCapture(ereignis.pointerId);
		}

		zug = null;
	}

	document.addEventListener('pointerup', beende);
	document.addEventListener('pointercancel', beende);

	document.addEventListener('keydown', function (ereignis) {
		var editor = ereignis.target.closest ? ereignis.target.closest('[data-metadaten-editor]') : null;
		var richtung = {ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1]}[ereignis.key];

		if (!editor || !richtung) {
			return;
		}

		var karte = editor.closest('[data-metadaten-karte]');
		var r = lies(karte);

		if (ereignis.shiftKey) {
			r.w += richtung[0] * SCHRITT;
			r.h += richtung[1] * SCHRITT;
		} else {
			r.x += richtung[0] * SCHRITT;
			r.y += richtung[1] * SCHRITT;
		}

		schreibe(karte, r, true);
		ereignis.preventDefault();
	});

	document.addEventListener('click', function (ereignis) {
		if (!ereignis.target.closest) {
			return;
		}

		var zurueck = ereignis.target.closest('[data-metadaten-zurueck]');

		if (zurueck) {
			var karte = zurueck.closest('[data-metadaten-karte]');
			var werte = (karte.querySelector('[data-metadaten-editor]').getAttribute('data-vorschlag') || '').split(',').map(parseFloat);

			if (werte.length === 4 && !werte.some(isNaN)) {
				schreibe(karte, {x: werte[0], y: werte[1], w: werte[2], h: werte[3]}, false);
				karte.classList.remove('metadaten-geaendert');
			}

			ereignis.preventDefault();

			return;
		}

		var alle = ereignis.target.closest('[data-metadaten-alle]');

		if (alle) {
			var an = alle.getAttribute('data-metadaten-alle') === '1';
			var formular = alle.closest('form') || document;

			Array.prototype.forEach.call(formular.querySelectorAll('[data-metadaten-karte]'), function (karte) {
				hake(karte, an);
			});

			ereignis.preventDefault();
		}
	});

	// Das Häkchen von Hand färbt die Karte genauso wie das Anhaken per Skript
	document.addEventListener('change', function (ereignis) {
		if (ereignis.target.matches && ereignis.target.matches('[data-metadaten-haken]')) {
			hake(ereignis.target.closest('[data-metadaten-karte]'), ereignis.target.checked);
		}
	});
})();
