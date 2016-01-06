# Modulator

Modulator hilft bei der modularen Webentwicklung für Wordpress, indem es ...

* sich in Timber einklinkt
* mithilfe von Twig die Logik von der Darstellung trennt
* ein Grundgerüst zur Verfügung stellt, mit dem die einzelnen Bestandteile einer Website (aka Module) jeweils zusammengefasst in einem eigenen Verzeichnis liegen

## Lizenz

Modulator wird von [quäntchen + glück](https://www.qundg.de) entwickelt und hier unter GPL-Lizenz veröffentlicht. Wir leisten keinen Support und behalten uns vor, die API jederzeit unangekündigt zu verändern. Du kannst dieses Plugin aber gerne forken, weiterentwickeln, deinen Bedürfnissen anpassen oder einen Kuchen daraus backen. Open Source macht's möglich :)

## Timber-Integration

Modulator arbeitet wunderbar mit [Timber](https://wordpress.org/plugins/timber-library/) zusammen. Über den Namespace `timber` kann innerhalb von Modulator-Templates auf Timber-Variablen zugegriffen werden (z.B. `{{ timber.theme.path }}`). Auch Twig-Erweiterungen aus Timber können in Modulator-Templates benutzt werden.

Leider ist es im Moment nicht möglich, die Twig-Instanz von Timber extern abzugreifen. Modulator kommt daher mit einer eigenen Twig-Instanz und jagt diese durch alle nötigen Timber-Filter, um die Variablen und Erweiterungen aus Timber verfügbar zu machen. Wenn es nach einem Timber-Update Probleme mit Modulator gibt, sind dafür höchstwahrscheinlich geänderte Filter verantwortlich. Dann muss man in Timber nachschauen, welche Filter sich geändert haben, und passt diese im Konstruktor von Modulator an.

## Globale Variablen

Modulator bietet über den Namespace `globals` Zugriff auf gemeinsam genutzte Variablen (z.B. `{{ globals.home_url }}`). Die Timber-Integration ist hier allerdings eine Einbahnstraße, d.h. die Modulator-Globals sind *nicht* in Timber verfügbar. Derzeit gibt es:

* `globals.home_url` für die URL der Startseite
* `globals.theme_url` für die URL des Theme-Verzeichnisses
* `globals.images_url` für die URL von `/assets/img/` immerhalb des Theme-Verzeichnisses

## Worauf bei Updates achten?

Da Modulator schon ein paar größere Überarbeitungen hinter sich hat, muss man bei manchen Updates Hand anlegen:

### auf 2.0.0 oder höher

In älteren Versionen kam ein selbst entwickeltes Templatesystem zum Einsatz, seit 2.0.0 läuft Modulator mit Twig. Die Versionen sind daher nicht miteinander kompatibel, d.h. beim Update von 1.x auf 2.x müssen die Views aller Module für Twig umgeschrieben werden.

### auf 2.2.0 oder höher

Vor 2.2.0 wurden die style.css und die script.js aus allen Modulen automatisch eingebunden. Seit 2.2.0 ist das nicht mehr der Fall, da die Module so flexibler in den Build-Prozess z.B. mit Gulp integriert werden können. Bei diesem Update muss man also darauf achten, dass die Assets anderweitig eingebunden werden.
