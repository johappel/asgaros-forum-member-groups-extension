# SUCHE Funktion erweitern

Die Suche muss mehr leisten als **eigene funktionale Baustelle mit hoher Priorität** behandelt werden. „Trefferdarstellung verbessern“ wäre zu harmlos formuliert.

Die Ursache liegt in der Asgaros-Suche selbst: Sie sucht zwar sowohl in Thementiteln als auch in Beitragstexten, fasst die Treffer anschließend aber nach `topic_id` zusammen. Dabei geht die ID des tatsächlich gefundenen Beitrags verloren. Danach wird lediglich das normale Thema-Element gerendert.

Dadurch kann Asgaros technisch gar nicht mehr wissen:

* welcher Beitrag den Suchbegriff enthält,
* auf welcher Themenseite dieser Beitrag steht,
* zu welchem Anker gesprungen werden müsste,
* welcher Textausschnitt angezeigt werden sollte.

## Was die Suche stattdessen leisten muss

Ein Suchergebnis sollte **ein gefundener Beitrag** sein, nicht bloß ein Thema.

Beispiel:

> **Arbeitsgruppe zur Digitalisierung**
> Thema: *Welche KI-Werkzeuge nutzen wir?*
> Beitrag von Maria Beispiel · 18.07.2026
>
> „…für unsere Fortbildung haben wir insbesondere **ChatGPT** und Fobizz miteinander verglichen…“
>
> **Zum gefundenen Beitrag**

Der Link muss dann direkt lauten wie:

```text
/forum/topic/welche-ki-werkzeuge-nutzen-wir/?part=3#postid-142
```

Nicht nur:

```text
/forum/topic/welche-ki-werkzeuge-nutzen-wir/
```

## Notwendige technische Logik

Für jeden gefundenen Beitrag werden mindestens benötigt:

```text
post_id
topic_id
forum_id
author_id
post_text
post_date
```

Anschließend muss die richtige Seite berechnet werden.

Vereinfacht:

```php
$position = Anzahl der älteren freigegebenen Beiträge im Thema;
$page     = floor($position / $posts_per_page) + 1;
```

Der Ziellink wird dann zusammengesetzt aus:

```php
$topic_url . '?part=' . $page . '#postid-' . $post_id;
```

Dabei muss exakt dieselbe Sortierung verwendet werden wie in der Themenansicht. Sonst stimmt die berechnete Seite erneut nicht.

## Empfohlene Sucharchitektur

Ich würde die bestehende Asgaros-Suche nicht nur per CSS verändern und auch nicht direkt im Asgaros-Plugin patchen. Besser ist ein eigenes Modul, entweder:

```text
efabinet-asgaros-search
```

oder als Bestandteil von:

```text
asgaros-forum-member-groups-extension
└── Search
```

Das Modul übernimmt die Suchansicht vollständig, während Asgaros weiterhin für Themen, Beiträge und Zugriffsrechte zuständig bleibt.

## Konkrete To-do-Liste

### MVP 1: Suche wieder benutzbar machen

* [ ] Suche auf Beitragsebene statt nur auf Themenebene durchführen.
* [ ] `post_id` jedes Treffers erhalten.
* [ ] richtige Themenseite für jeden Beitrag berechnen.
* [ ] direkt zu `#postid-{ID}` verlinken.
* [ ] Thementitel anzeigen.
* [ ] Autor:in und Beitragsdatum anzeigen.
* [ ] passenden Textausschnitt darstellen.
* [ ] Suchbegriff im Ausschnitt hervorheben.
* [ ] mehrere Treffer aus demselben Thema getrennt anzeigen.
* [ ] nur Beiträge aus zugänglichen Foren berücksichtigen.
* [ ] gelöschte, nicht freigegebene oder verborgene Beiträge ausschließen.

### MVP 2: sinnvolle Trefferliste

* [ ] Forum beziehungsweise Arbeitsgruppe anzeigen.
* [ ] Treffer nach Relevanz oder Datum sortierbar machen.
* [ ] Filter nach Arbeitsgruppe anbieten.
* [ ] Filter nach Autor:in anbieten.
* [ ] Filter nach Zeitraum anbieten.
* [ ] Suchbegriff in Seitentitel und Suchfeld erhalten.
* [ ] Suchpagination unabhängig von der Themenpagination umsetzen.

### MVP 3: bessere Suchqualität

* [ ] Wortgruppen in Anführungszeichen unterstützen.
* [ ] mehrere Suchbegriffe sinnvoll kombinieren.
* [ ] Umlaute und deutsche Wortformen testen.
* [ ] reine Titelsuche optional anbieten.
* [ ] „alle Wörter“ versus „eines der Wörter“ ermöglichen.
* [ ] sehr kurze Suchwörter verständlich behandeln.
* [ ] gegebenenfalls Relevanzgewichtung:

  * Titel stärker,
  * Beitragstext normal,
  * aktuellere Beiträge leicht bevorzugt.

## Wichtige Sonderfälle

### Erster Beitrag eines Themas

In Asgaros ist auch der Eröffnungsbeitrag ein Beitrag. Ein Treffer dort muss ebenfalls direkt auf dessen Anker führen.

### Mehrere Treffer innerhalb eines Beitrags

Der Beitrag sollte nur einmal erscheinen. Der Textausschnitt sollte die relevanteste Fundstelle zeigen.

### Mehrere Treffer innerhalb eines Themas

Diese sollten nicht zu einem einzigen Themenlink zusammengefasst werden. Sonst entsteht wieder genau das heutige Problem.

Optional könnte man sie visuell gruppieren:

```text
Thema: Feature Requests

3 passende Beiträge:
- Beitrag von Simone …
- Beitrag von Joachim …
- Beitrag von Anastasia …
```

Jeder Treffer erhält aber seinen eigenen direkten Link.

### Geschützte Arbeitsgruppen

Die Suche darf weder Titel noch Textausschnitte aus Arbeitsgruppen offenlegen, auf die die suchende Person keinen Zugriff hat. Die bestehende Suche beschränkt sich zwar auf zugängliche Kategorien, eine neue Suche muss jedoch zusätzlich die konkrete Forums- und Gruppenzugriffsprüfung zuverlässig übernehmen. Die aktuelle Abfrage arbeitet dafür mit den zugänglichen Kategorien.

