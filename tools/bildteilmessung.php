<?php

declare(strict_types=1);

/*
 * Misst die Schätzung des wichtigen Bildteils an Markierungen von Hand.
 *
 * Aufruf:  php tools/bildteilmessung.php <markierungen.csv> <fotoordner> [<bericht.csv>]
 *
 * markierungen.csv enthält je Zeile „dateiname;x;y;breite;höhe“, die Werte
 * als Bruchteile der Bildgröße, so wie Contao sie in tl_files speichert
 * (importantPartX, -Y, -Width, -Height). Kopfzeilen und Leerzeilen werden
 * übersprungen.
 *
 * Gemessen wird die Überdeckung (Schnittfläche durch Vereinigungsfläche)
 * zwischen Schätzung und Markierung, 1 heißt deckungsgleich. Zum Vergleich
 * steht daneben das ganze Bild als Vorschlag. Das Werkzeug braucht nur GD,
 * weder Contao noch eine Datenbank; die Fotos werden wie im Backend auf
 * Bildanalyse::KANTE Pixel verkleinert.
 */

use Schachbulle\ContaoMetadatenBundle\Classes\Bildanalyse;

require dirname(__DIR__).'/src/Classes/Bildanalyse.php';

$csv = $argv[1] ?? '';
$ordner = rtrim(str_replace('\\', '/', $argv[2] ?? ''), '/');
$bericht = $argv[3] ?? '';

if (!is_file($csv) || !is_dir($ordner))
{
	fwrite(STDERR, "Aufruf: php tools/bildteilmessung.php <markierungen.csv> <fotoordner> [<bericht.csv>]\n");
	exit(1);
}

if (!Bildanalyse::verfuegbar())
{
	fwrite(STDERR, "Die PHP-Erweiterung GD fehlt.\n");
	exit(1);
}

/**
 * Überdeckung zweier Rechtecke (x, y, Breite, Höhe als Bruchteile).
 */
function ueberdeckung(array $a, array $b): float
{
	$x1 = max($a[0], $b[0]);
	$y1 = max($a[1], $b[1]);
	$x2 = min($a[0] + $a[2], $b[0] + $b[2]);
	$y2 = min($a[1] + $a[3], $b[1] + $b[3]);
	$schnitt = max(0, $x2 - $x1) * max(0, $y2 - $y1);
	$vereinigung = $a[2] * $a[3] + $b[2] * $b[3] - $schnitt;

	return $vereinigung > 0 ? $schnitt / $vereinigung : 0.0;
}

$werte = array();
$ganz = array();
$einteilung = array('richtig' => 0, 'falschBreit' => 0, 'falschSchmal' => 0);
$umgeschaltet = array();
$zeilen = array("datei;markierung;schaetzung;ueberdeckung");
$fehlend = array();
$start = microtime(true);

foreach (file($csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $zeile)
{
	$t = array_map('trim', explode(';', $zeile));

	if (5 !== \count($t) || !is_numeric($t[1]))
	{
		continue;
	}

	$pfad = $ordner.'/'.$t[0];

	if (!is_file($pfad))
	{
		$fehlend[] = $t[0];
		continue;
	}

	$markierung = array((float) $t[1], (float) $t[2], (float) $t[3], (float) $t[4]);
	$analyse = Bildanalyse::vorschlaegeAusDatei($pfad);
	$teil = $analyse['gewaehlt'] ?? null;
	$schaetzung = null === $teil ? array(0.25, 0.25, 0.5, 0.5) : array($teil['x'], $teil['y'], $teil['width'], $teil['height']);
	$wert = ueberdeckung($schaetzung, $markierung);

	// Einteilung „ein oder zwei Spieler“: Als breit gilt eine Markierung ab 80 Prozent der Bildbreite
	if (null !== $analyse)
	{
		$breitMarkiert = $markierung[2] >= 0.8;
		$einteilung[$breitMarkiert === $analyse['zweiSpieler'] ? 'richtig' : ($analyse['zweiSpieler'] ? 'falschBreit' : 'falschSchmal')]++;
		$umgeschaltet[] = ueberdeckung(array_values($breitMarkiert ? $analyse['breit'] : $analyse['schmal']), $markierung);
	}

	$werte[] = $wert;
	$ganz[] = ueberdeckung(array(0, 0, 1, 1), $markierung);
	$zeilen[] = sprintf('%s;%s;%s;%.3f', $t[0], implode(',', $markierung), implode(',', $schaetzung), $wert);
}

if (!$werte)
{
	fwrite(STDERR, "Keine auswertbaren Zeilen.\n");
	exit(1);
}

sort($werte);
$n = \count($werte);
$mittel = array_sum($werte) / $n;
$median = $n % 2 ? $werte[intdiv($n, 2)] : ($werte[$n / 2 - 1] + $werte[$n / 2]) / 2;
$ab = static fn (float $s): float => 100 * \count(array_filter($werte, static fn ($w) => $w >= $s)) / $n;

printf("%d Fotos in %.1f s ausgewertet%s\n\n", $n, microtime(true) - $start, $fehlend ? ', '.\count($fehlend).' fehlen im Ordner' : '');
printf("Schätzung:       Überdeckung Mittel %.2f, Median %.2f, ab 0,7: %.0f %%, ab 0,8: %.0f %%\n", $mittel, $median, $ab(0.7), $ab(0.8));
printf("Ganzes Bild:     Überdeckung Mittel %.2f\n", array_sum($ganz) / \count($ganz));
printf("\nEinteilung ein oder zwei Spieler (breit markiert ab 80 %% der Bildbreite):\n");
printf("  richtig %d, fälschlich zwei Spieler %d, fälschlich ein Spieler %d\n", $einteilung['richtig'], $einteilung['falschBreit'], $einteilung['falschSchmal']);

if ($umgeschaltet)
{
	printf("  nach Umschalten der falsch eingeteilten im Editor: Überdeckung Mittel %.2f\n", array_sum($umgeschaltet) / \count($umgeschaltet));
}

if ('' !== $bericht)
{
	file_put_contents($bericht, implode("\n", $zeilen)."\n");
	echo "Einzelwerte in $bericht\n";
}
