# Fremddateien

Die Dateien in diesem Verzeichnis stammen nicht vom Autor des Bundles. Sie
liegen **unverändert** bei, damit die Gesichtserkennung im Bildteil-Editor
ohne Zugriff auf fremde Server auskommt.

| Datei | Herkunft | Stand | SHA-256 |
| --- | --- | --- | --- |
| `pico.js` | <https://github.com/nenadmarkus/picojs> | Commit `afffa50ec4134a47005f2cbf8112eaa69f65f37e` | `785b981cc79e5fa3f7557dc3fa7773629d7529994d7627de41b77d8687649309` |
| `facefinder.bin` | <https://github.com/nenadmarkus/pico>, dort `rnt/cascades/facefinder` | Commit `c2e81f9d23cc11d1a612fd21e4f9de0921a5d0d9` | `d8014993e7298c7b1865d1f8b855d6dbf4ec5c808bf879e2091ab6837abf90cd` |

`facefinder.bin` heißt im Original `facefinder` ohne Endung. Die Endung ist
ergänzt, damit Webserver die Datei ohne Sonderregel ausliefern; der Inhalt
ist derselbe.

`tools/pruefstand.php` vergleicht beide Prüfsummen. Wer eine Datei
aktualisiert, trägt Stand und Prüfsumme hier und im Prüfstand nach.

## Lizenz

Beide Projekte stehen unter der MIT-Lizenz. `pico.js` sagt das in seiner
ersten Zeile und in der Readme seines Repositorys, das Projekt `pico` in
seiner Lizenzdatei. Urheber ist Nenad Markuš.

```
MIT License

Copyright (c) Nenad Markuš

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
