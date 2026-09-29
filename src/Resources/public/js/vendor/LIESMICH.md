# Fremddateien

Die Dateien in diesem Verzeichnis stammen nicht vom Autor des Bundles. Sie
liegen **unverändert** bei, damit die Personenerkennung im Bildteil-Editor
ohne Zugriff auf fremde Server auskommt.

## MediaPipe Tasks Vision

| Datei | Herkunft | SHA-256 |
| --- | --- | --- |
| `mediapipe/vision_bundle.js` | npm-Paket `@mediapipe/tasks-vision` 1.0.1 | `98db72469ffb176f5e9f2687be0f70783893aca681f7789c34b872b0a764371a` |
| `mediapipe/wasm/vision_wasm_internal.js` | dasselbe Paket, Verzeichnis `wasm/` | `e170ee67dd4e16c1a6fcd8840a206687e5a59b22c20e4a902bc445b095454d73` |
| `mediapipe/wasm/vision_wasm_internal.wasm` | dasselbe Paket, Verzeichnis `wasm/` | `8da277a733926eacd0474b8704b36742d6ec3231c57a860c5b889dff8f1df886` |
| `mediapipe/efficientdet_lite0_int8.tflite` | <https://storage.googleapis.com/mediapipe-models/object_detector/efficientdet_lite0/int8/latest/efficientdet_lite0.tflite> | `0720bf247bd76e6594ea28fa9c6f7c5242be774818997dbbeffc4da460c723bb` |
| `mediapipe/LICENSE` | <https://github.com/google-ai-edge/mediapipe/blob/master/LICENSE> | `8707eef0533987efc5b155d64761eeb6e20793f50b9bd1a68dad1cf4719d0ed8` |

Das npm-Archiv `tasks-vision-1.0.1.tgz` stimmte beim Herunterladen am
2026-09-29 mit der Integritätsangabe der Registry überein
(`sha512-rvRE2FmAZ6ZxKSw7wq+e+jQDpN3t1B/tD2mJz9SmAzb1msoDkd4dMoE4wAh8Z30Um0PQwLiHr9QtomhmXk3aUQ==`).

Aus dem Paket liegen nur die Dateien bei, die der Editor braucht:

* `vision_bundle.js` ist die Fassung als klassisches Skript mit der globalen
  Variablen `Vision`. Die Modulfassung `.mjs` wurde bewusst nicht genommen:
  Manche Webserver liefern `.mjs` mit falschem Typ aus, und dann verweigert
  der Browser das Laden.
* Aus `wasm/` nur die Fassung mit SIMD. Die Fassung ohne SIMD (weitere
  11 MB) bräuchten nur Browser von vor 2021; dort schlägt die Erkennung fehl,
  und der Editor behält den Vorschlag aus der Schärfe.
* Als Modell nur EfficientDet-Lite0 in der int8-Fassung (4,6 MB). Die
  größeren Modelle EfficientDet-Lite2 und die Körperhaltungsmodelle waren
  in der Messung an 206 Turnierfotos nicht besser.

`tools/pruefstand.php` vergleicht alle Prüfsummen. Wer eine Datei
aktualisiert, trägt Stand und Prüfsumme hier und im Prüfstand nach.

## Lizenz

MediaPipe steht unter der Apache License 2.0 (Paketangabe `"license":
"Apache-2.0"`, Lizenzdatei des Repositorys `google-ai-edge/mediapipe`); der
Lizenztext liegt als `mediapipe/LICENSE` bei. Die Modellseite von Google
nennt für EfficientDet-Lite0 keine eigene Lizenz; das Modell stammt aus dem
Projekt `google/automl`, das ebenfalls unter Apache 2.0 steht.

## Datenschutz

MediaPipe Tasks sendet laut Datenschutzhinweis des Pakets von sich aus
Kennzahlen zur Nutzung an Google (Betriebssystem, Version, Rechenzeiten)
und überlässt es dem Betreiber, dafür eine Einwilligung einzuholen. Die
Bilder selbst verlassen das Gerät nicht. Der Versand läuft alle 60 Sekunden
per `fetch` an `https://odml.pa.googleapis.com/v1/log`.

`personen.js` unterbindet diesen Versand: Vor dem Laden von MediaPipe
ersetzt es `window.fetch` durch eine Hülle, die genau diese Adresse abweist.
Nach dem ersten Fehlschlag stellt MediaPipe den Versand von selbst ein. Die
Dateien hier bleiben dafür unverändert. Nachgewiesen am 2026-09-29: ein
abgefangener Versuch nach 60 Sekunden, keine Anfrage an Google im
Netzwerkprotokoll des Browsers.

## Webserver

Die `.wasm`-Datei sollte mit dem Typ `application/wasm` ausgeliefert werden.
Tut der Server das nicht, lädt MediaPipe sie auf dem langsameren Weg über
einen ArrayBuffer; die Erkennung funktioniert trotzdem.
