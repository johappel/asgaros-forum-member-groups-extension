# Suche

Die Suche ist in AFSpaces ein eigener Funktionsbereich und nicht nur eine Variante der Asgaros-Standardansicht. Ziel war es, die technische Schwäche der Asgaros-Bestandssuche zu beseitigen: Treffer werden nicht mehr auf Themenebene zusammengefasst, sondern als konkrete Beiträge mit Deep-Link, Snippet und Kontext dargestellt.

> Diese Seite erklärt Konzept und Architektur der Suche. Die konkrete Komponentenkarte, alle REST-Parameter und die Erweiterungspunkte stehen in [referenz/BEREICH-suche.md](referenz/BEREICH-suche.md) und [referenz/REST-API.md](referenz/REST-API.md).

## Zielbild

Ein Suchtreffer ist ein gefundener Beitrag oder ein passender WordPress-Inhalt. Für Forumstreffer muss der Link auf den exakten Beitrag zeigen, inklusive Teilseite und Post-Anker.

Beispiel:

- Thema anzeigen.
- Autor und Datum anzeigen.
- Snippet mit hervorgehobener Fundstelle anzeigen.
- Direkt auf ?part=N#postid-ID verlinken.

## Hauptbausteine

### Keyword-Suche im Forum

Zentrale Klassen:

- Search/FulltextQuery
- Search/SnippetBuilder
- Application/ForumSearchService
- Adapters/Asgaros/AsgarosAdapter

Wesentliche Eigenschaften:

- Suche auf Beitragsebene statt Topic-Collapse.
- FULLTEXT auf Beitragsinhalt und Thementitel.
- LIKE-Fallback für kurze Tokens unterhalb der FULLTEXT-Mindestlänge.
- Phrase-Support und Wortmodi all oder any.
- title-only-Suche.
- Ausschluss nicht freigegebener oder nicht sichtbarer Inhalte.

### Hybrid-Suche über Forum und WordPress

Zentrale Klassen:

- Application/HybridSearchService
- Search/WpPostSearch
- Search/ResultFusion

Wesentliche Eigenschaften:

- Fusion von Foren- und WordPress-Treffern.
- Bereichswahl forum, wp oder all.
- Reciprocal Rank Fusion als deterministische, robuste Zusammenführung.
- Beibehaltung echter SQL-Pagination, wo keine Fusion nötig ist.

### Semantische Suche

Zentrale Klassen:

- Search/EmbeddingClient
- Application/SearchIndexer
- Search/VectorMath
- Search/VectorSearch
- Adapters/Database/SearchIndexRepository
- Search/SearchSettings

Wesentliche Eigenschaften:

- Standardmäßig deaktiviert.
- Nutzt eine OpenRouter-kompatible Embedding-API.
- Speichert Embeddings lokal in wp_afspaces_search_index.
- Führt Live-Zugriffsfilter bei jeder Abfrage aus.
- Degradiert sauber zu Keyword- oder Hybrid-Suche, wenn kein API-Key konfiguriert ist.

## Nutzeroberflächen

### SearchView im Hub

- Route über afspaces_view=search.
- Serverseitig gerenderte Suchseite.
- Unabhängige Pagination für Suchergebnisse.
- Filter für Wortmodus, Suchbereich, Autor, Arbeitsgruppe und Zeitraum.

### SearchModal site-weit

- Barrierearmes Overlay mit Fokusfalle und Escape.
- Live-Suche über die REST-Route.
- Trigger über Shortcodes, Asgaros-Suchformular und optional normale WordPress-Suchformulare.
- Progressive Enhancement: ohne JavaScript bleibt die Suchseite erreichbar.

## REST-API

Aktueller Suchendpunkt:

- GET /wp-json/afspaces/v1/search

Wichtige Parameter:

- q
- scope
- sort
- mode
- in
- page
- per_page
- semantic
- author
- author_name
- forum
- date_from
- date_to

Wichtige Antwortfelder:

- results
- total
- page
- per_page
- total_pages
- semantic_used

Autorisierung:

- Keyword-Suche ist öffentlich erreichbar.
- Semantische Suche bleibt auf eingeloggte Benutzer begrenzt.
- Sichtbarkeitsfilter greifen serverseitig unabhängig vom Clientzustand.

## Konfiguration

Backend-Seite:

- Einstellungen -> AFSpaces Suche

Konfigurierbar sind unter anderem:

- Embedding-API-Endpunkt.
- API-Key.
- Modellname.
- Aktivierung der semantischen Suche.
- Einbezug öffentlicher WordPress-Beitragstypen.
- Optionales Einbetten privater Inhalte.
- Gewichte für Hybrid-Fusion.
- Mindest-Score für semantische Treffer.
- Optionales Ersetzen normaler WordPress-Suchformulare.

## Sicherheits- und Datenschutzregeln

- Keine privaten Inhalte ohne explizites Opt-in an den Embedding-Anbieter senden.
- Keine Zugriffsentscheidung im Browser treffen.
- Keine Token oder sensiblen Einladungsdetails in Suchtreffern ausgeben.
- Semantische Suche nur dann aktivieren, wenn Betreiber die externe Datenübertragung bewusst freigeben.

## Relevante bekannte Entscheidungen

- Die Asgaros-Originalsuche wird per Redirect auf die AFSpaces-Suche umgelenkt.
- Deep-Links werden über die Asgaros-Rewrite-Logik erzeugt, damit Paging und Anker mit der echten Themenansicht übereinstimmen.
- Die Suche ist ein Querschnittsthema zwischen Adapter, Application, Search und Interface. Änderungen sind fast nie auf eine einzige Klasse beschränkt.

## Offene Weiterentwicklung

- Strengeres Rate-Limiting für öffentliche Suchanfragen.
- Zusätzliche Qualitätsstrategien für deutsche Wortformen und Stemming.
- Weitere Relevanz- und Snippet-Feinabstimmung.
- Zusätzliche automatisierte Tests für Suchfilter und Overlay-Interaktionen, wenn neue Filter hinzukommen.