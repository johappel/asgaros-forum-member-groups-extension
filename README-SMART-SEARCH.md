# AFSpaces Smart Search — Dokumentation

## Überblick

AFSpaces Smart Search ist ein Hybrid-Suchsystem, das **Keyword-Suche** (Volltext) mit **semantischer Suche** (AI-Embeddings) kombiniert. Die Suche deckt Asgaros-Forum-Beiträge und optionale WordPress-Inhalte ab.

### Komponenten
1. **Keyword-Suche**: FULLTEXT-Index in Asgaros + WordPress, mit LIKE-Fallback für Short Words
2. **Semantische Suche**: OpenRouter Embeddings (kostenpflichtig) mit PHP-Cosine-Ähnlichkeit
3. **Hybrid-Fusion**: Reciprocal Rank Fusion (RRF) vereint beide Quellen
4. **Modal Overlay**: Site-weites Such-Overlay mit Live-Ergebnissen (Progressive Enhancement)
5. **Filter & Konfiguration**: Autor, Zeitraum, Kategorie, Wortmodus, Bereichswahl

---

## Architektur

```
RestController (/wp-json/afspaces/v1/search)
    ↓
HybridSearchService (scope: forum|wp|all)
    ├─→ ForumSearchService (AsgarosAdapter)
    └─→ WpPostSearch (WP_Query)
        ↓
    ResultFusion (RRF, K=60)
        ↓
    VectorSearch (semantisch, wenn aktiviert)
        ↓ (filtered via SearchIndexRepository)
    SearchHit[] (mit score, snippet, autor, url)
```

### Datenbankschema

#### `wp_afspaces_search_index` (Semantik-Index)
```sql
id                  BIGINT PRIMARY KEY
source_type         VARCHAR(10) [forum|wp]
source_id           BIGINT UNIQUE(source_type, source_id)
topic_id            BIGINT (Forum-Thema-ID)
category_id         BIGINT (Kategorie-ID)
is_private          TINYINT (Private Arbeitsgruppe?)
title               TEXT
context_label       VARCHAR(255) (z.B. "Forum", "Beitrag")
excerpt             LONGTEXT (für Ähnlichkeitssuche)
author_name         VARCHAR(255)
item_date           DATETIME
content_hash        CHAR(40) SHA1 des Inhalts
embedding           LONGBLOB (float32-Vektor, gepackt)
dims                INT (Dimensionen: 1024 für pplx-embed-v1)
updated_at          DATETIME
```

---

## Keyword-Suche (FULLTEXT / LIKE)

### Funktionsweise

#### 1. Tokenisierung & Query-Bau
Datei: `src/Search/FulltextQuery.php`

- **Phrase-Verarbeitung**: `"neues Forum"` → bleibt zusammen
- **Operator-Sanierung**: `+term -excl (group)~` → nur sichere Operatoren
- **UTF-8-Umlaut-Support**: `überprüfung` → `überprüfung*` (Präfix-Wildcard)
- **Word-Mode**:
  - `any`: `term1 OR term2` (Standard)
  - `all`: `+term1 +term2` (Boolean-Präfixe)

#### 2. FULLTEXT Query
- SQL: `MATCH(posts.text) AGAINST(...IN BOOLEAN MODE)`
- Mindestlänge Token: `innodb_ft_min_token_size` (default 3)
- Topics zählen doppelt (Score × 2)

#### 3. LIKE Fallback
- Bei allen Tokens < 3 Zeichen: `posts.text LIKE '%term%'`
- Langsamer, aber notwendig für Queries wie `"Fo"`, `"IT"`, `"DB"`
- Detektiv: `FulltextQuery::needs_like_fallback()`

### Suchparameter

**GET /afspaces/v1/search**

```
q              string     Suchbegriff
scope          string     all|forum|wp (default: all)
sort           string     relevance|date (default: relevance)
mode           string     any|all (Wortmodus)
in             string     all|title (Suche in: Text oder nur Titel)
page           int        Seite (1-basiert)
per_page       int        Treffer pro Seite (max 50)
semantic       bool       1|0 Semantische Suche (nur eingeloggt)
author         int        Autor-ID (Forum) / Beitrags-Autor
author_name    string     Autor-Name (Fuzzy-Match via WP_User_Query)
forum          int        Asgaros-Forum-ID
date_from      string     YYYY-MM-DD (>=)
date_to        string     YYYY-MM-DD (<=)
```

### Beispiele
```bash
# Phrase mit Forum-Scope
curl 'https://example.com/wp-json/afspaces/v1/search?q="neues%20Forum"&scope=forum'

# Alle Wörter, nur Titel
curl 'https://example.com/wp-json/afspaces/v1/search?q=WordPress%20Plugins&mode=all&in=title'

# Autor-Filter + Datum
curl '.../search?q=test&author_name=John&date_from=2024-01-01&date_to=2024-12-31'

# Mit Semantik (nur eingeloggt)
curl 'https://example.com/wp-json/afspaces/v1/search?q=workflow&semantic=1' \
  -H 'X-WP-Nonce: <nonce>'
```

---

## Semantische Suche (Embeddings)

### Aktivierung (Backend-Admin)

**Einstellungen → AFSpaces Suche**

1. ✅ "Semantische Suche aktivieren"
2. API-URL: `https://openrouter.ai/api/v1/embeddings` (vorkonfiguriert)
3. **API-Key** (geheim, nicht im Frontend): von [OpenRouter](https://openrouter.ai)
4. Modell: `perplexity/pplx-embed-v1-0.6b` (1024 dims, ~$0.02 pro 1M tokens)
5. **Mindest-Score**: `0.30` (Cosine-Ähnlichkeit: 0.0–1.0)
6. Gewichte: `semantic_weight=1.0`, `keyword_weight=1.0` (RRF-Fusion)

### Datenspeicherung

**Vektor-Speicherung: SearchIndexRepository**

1. **Quellen**:
   - `source_type='forum'`: Asgaros-Forum-Beiträge
   - `source_type='wp'`: WordPress-Beiträge (wenn `index_wp=true`)

2. **Einbettungs-Prozess**:
   - SavePost/Publish-Hooks: EmbeddingClient ruft OpenRouter auf
   - Content-Hash (SHA1) verhindert doppelte Indizierung
   - Vektor als LONGBLOB gepackt (VectorMath::pack → binary float32)

3. **Private Inhalte**:
   - `is_private=1` Flagge speichert: "Gehört zu privater AG"
   - Bei VectorSearch: nur Benutzer-sichtbare Kategorien zurückgeben
   - Default: `index_private=false` (private AGs NICHT einbetten)

4. **Batch-Indizierung** (täglich via WP-Cron):
   - Job: `afspaces_reindex_search`
   - `SearchIndexer::reindex_all()` verarbeitet alle fehlenden/geänderten Beiträge
   - Pro Batch: max. 10 Items (rate-limiting)

### Welche Userdaten werden weitergegeben?

**An OpenRouter API (beim Embedding):**
```json
{
  "model": "perplexity/pplx-embed-v1-0.6b",
  "input": "Der tatsächliche Beitragstext, Titel, Autorenname"
}
```

**NICHT weitergegeben:**
- Benutzernamen von Lesern (nur Autoren)
- Benutzer-IDs
- Cookies, Session-Daten
- IP-Adressen
- Private Arbeitsgruppen (wenn `index_private=false`)

**Speicherung auf Server:**
- Embeddings lokal in `wp_afspaces_search_index.embedding` (LONGBLOB)
- Kein Back-Channel zu OpenRouter
- Einmal eingebettet = kein neuer API-Call für gleichen Inhalt

### Ähnlichkeits-Filter

**VectorSearch::search()** nutzt Cosine-Ähnlichkeit:

```php
score = dot_product(query_vec, stored_vec) / (norm(query_vec) * norm(stored_vec))
```

- **score > 0.48–0.54**: Starke Treffer (relevanter Inhalt)
- **0.36–0.42**: Verwandte Inhalte
- **0.20–0.28**: Rauschen (wird gefiltert bei `semantic_min_score >= 0.30`)

Beispiel:
```
Query: "Workflow-Automation"
  → Vektor A: "CI/CD-Pipeline einrichten" (score: 0.52) ✓
  → Vektor B: "Team-Prozesse" (score: 0.38) — unter 0.40
  → Vektor C: "Unverwandter Text" (score: 0.15) ✗ gefiltert
```

---

## Hybrid-Fusion (RRF)

**ResultFusion** kombiniert Keyword- und Semantic-Ergebnisse mit **Reciprocal Rank Fusion**:

$$
\text{RRF}(d) = \sum_r \frac{1}{K + \text{rank}_r(d)}
$$

Wobei:
- $K = 60$ (Standardparamater, bei `=1` kommt der erste Hit doppelt)
- $\text{rank}_r$ = Position in Rangliste $r$ (1-basiert)

### Beispiel

| Keyword-Ranking | Semantic-Ranking | RRF-Score | Final-Rank |
|---|---|---|---|
| 1: Post A | 3: Post C | 1/61 + 1/63 = 0.0328 | 1 |
| 2: Post B | — | 1/62 = 0.0161 | 2 |
| 3: Post C | 1: Post A | 1/63 + 1/61 = 0.0328 | 1 |

**Gewichtung**: `semantic_weight` und `keyword_weight` multiplizieren RRF-Scores vor Reranking.

---

## REST-API

### Endpoint: GET /afspaces/v1/search

**Response-Format**:
```json
{
  "results": [
    {
      "source": "forum|wp",
      "title": "Beitrag-Titel",
      "url": "https://example.com/forum/...#post123",
      "author": "Autorenname",
      "date": "2024-07-28",
      "context": "Forum",
      "snippet": "Text-Ausschnitt mit Keyword-Highlights...",
      "score": 0.95
    }
  ],
  "total": 42,
  "page": 1,
  "per_page": 10,
  "total_pages": 5,
  "semantic_used": true
}
```

**Fehlerbehandlung**:
- `400 Bad Request`: Ungültige Parameter
- `401 Unauthorized`: Nur für semantische Suche + Guest
- `500 Internal Server Error`: Adapter/API-Fehler

**Autorisierung**:
- Keyword-Suche: Public (jeder darf suchen)
- Semantische Suche: nur eingeloggte Nutzer (um API-Kosten zu sparen)

---

## Frontend: Shortcodes & JavaScript

### Shortcodes

#### 1. `[afspaces_search_link]`
Zeigt einen Such-Link (nutzt Modal).

```
[afspaces_search_link label="Nach Forum durchsuchen"]
```

**Attribute:**
- `label`: Anzeigetext (default: "Suche")

**HTML-Output**:
```html
<a href="#afspaces-search" class="afspaces-search-link">Nach Forum durchsuchen</a>
```

**JS-Trigger**: Click öffnet Modal mit `data-afspaces-search-scope` (aus Button-Attribut).

#### 2. `[afspaces_search_button]`
Button im Dashboard-Header (öffnet Modal mit Forum-Scope).

```html
<button data-afspaces-search-open data-afspaces-search-scope="forum" ...>
  <span class="search-icon fas fa-search"></span>
</button>
```

**Button-Scope**: `data-afspaces-search-scope="forum"` (voreingestellt).

#### 3. `[afspaces]`
Hub-Seite mit allen Funktionen. Die Suche wird über Menü-Link/Button geöffnet, nicht als eigener Tab.

### JavaScript: afspaces-search.js

**Initialisierung**:
```javascript
// Global konfiguriert via wp_localize_script('afspaces-search', 'afspacesSearch', {...})
window.afspacesSearch = {
  restUrl: 'https://example.com/wp-json/afspaces/v1/search',
  nonce: 'abc123...',
  forums: [{id: 1, name: 'Allgemein'}, ...],
  semanticAvailable: true,
  replaceWpSearch: true,
  i18n: {
    placeholder: 'Suche...',
    searching: 'Suche läuft …',
    wordMode: 'Wortmodus',
    wordAny: 'Eines der Wörter',
    wordAll: 'Alle Wörter',
    ...
  }
};
```

**Trigger-Mechan**:
1. Click auf `[data-afspaces-search-open]` oder `a[href$="#afspaces-search"]`
2. JS liest `data-afspaces-search-scope` vom Element
3. `open(prefill, scope)` setzt Scope-Feld und zeigt Modal

**Funktionen**:
- **Live-Suche**: Debounced fetch nach 350ms Inaktivität
- **Fokus-Falle**: Fokus bleibt im Modal (ARIA-modal)
- **Escape-Taste**: Schließt Modal
- **Paginierung**: Bewahrt alle Filter in Seiten-Links
- **Highlights**: Suchbegriffe im Snippet markieren

**Progressive Enhancement**:
- Ohne JS: Form sendet zu SearchView-Seite (Fallback)
- Mit JS: Live-Ergebnisse im Modal (overlay)

### CSS: afspaces.css

**Wichtige Selektoren**:
```css
.afspaces-search-overlay          /* Modal-Container */
.afspaces-search-overlay__body    /* Formular + Ergebnisse */
.afspaces-search-row              /* Suchzeile (Flex) */
.afspaces-search-filter-row       /* Filter-Selects (Grid) */
.afspaces-spinner                 /* Loading-Spinner */
.afspaces-pagination-list         /* Pager (Pills-Stil) */
.afspaces-search-result           /* Einzelner Treffer */
.afspaces-search-result-snippet   /* Text-Ausschnitt */
```

**Farben** (konfigurierbar via Einstellungen → Look & Feel):
- Primary: `#2d5d7f` (Forum-Blau)
- Hover: Leicht dunkler
- Focus: Outline 2px solid

---

## Einstellungen (Backend)

**Pfad**: Einstellungen → AFSpaces Suche

### Einstellungsseite: SearchSettingsPage

```php
// Option-Schlüssel
afspaces_search_options = [
  'embedding_enabled'   => false,
  'embedding_api_url'   => 'https://openrouter.ai/api/v1/embeddings',
  'embedding_api_key'   => '', // NICHT Frontend-zugänglich!
  'embedding_model'     => 'perplexity/pplx-embed-v1-0.6b',
  'index_private'       => false,
  'index_wp'            => true,
  'wp_post_types'       => ['post', 'page'],
  'wp_all_public_types' => false,
  'semantic_weight'     => 1.0,
  'keyword_weight'      => 1.0,
  'semantic_min_score'  => 0.30,
  'replace_wp_search'   => false,
]
```

### Admin-Formular (Tabs)

1. **Keyword-Suche** (immer aktiv)
   - Keine Einstellungen nötig (verwendet Asgaros + WP native)

2. **WordPress-Integration**
   - ☐ WordPress-Beiträge durchsuchen
   - ☐ Alle öffentlichen Beitragstypen verwenden
   - ☐ Einzelne Beitragstypen: `[post]` `[page]` `[news]` ...

3. **Semantische Suche**
   - ☐ Aktivieren
   - API-URL: `___________________`
   - **API-Schlüssel**: `___________________` (Passwort-Feld)
   - Modell: `perplexity/pplx-embed-v1-0.6b` (Dropdown)
   - ☐ Private Arbeitsgruppen indizieren
   - Mindest-Ähnlichkeit: `0.30` (Slider 0.0–1.0)

4. **Ranking & Gewichte**
   - Semantik-Gewicht: `1.0` (Slider)
   - Keyword-Gewicht: `1.0` (Slider)
   - Kosten-Info: ~$0.02 pro 1M Tokens (Info-Text)

5. **WordPress-Suche ersetzen**
   - ☐ AFSpaces-Modal auch auf WP-Suchformulare anwenden
     (ersetzt default WP-Suche durch overlay)

6. **Wartung**
   - [Jetzt neu indexieren] Button (POST admin_post)
   - "Letzte Indizierung: 2024-07-28 14:30 UTC"
   - "Indizierte Einträge: 234"

---

## Datenfluss & Datenschutz

### Persönliche Daten

#### Eingabe (Query)
```
Nutzer gibt ein: "workflow automation"
→ Query wird an REST-Endpoint gesendet
→ Nur Query-String weitergeleitet, KEINE Nutzer-ID/IP
```

#### Keyword-Suche
```
Query → FULLTEXT/LIKE auf Asgaros + WP
→ Nur Treffer-Metadaten zurück: title, snippet, url, author
→ Keine Speicherung der Query
```

#### Semantische Suche
```
Query-String → OpenRouter API
→ Vektor-Embedding erzeugt
→ Lokal in wp_afspaces_search_index gespeichert
→ Query selbst NICHT gespeichert (nur Embedding)
```

#### Indexierung
```
Beitrag veröffentlicht/bearbeitet
→ Save-Hook: EmbeddingClient ::embed_batch([...]  content, author])
→ OpenRouter :: embedding zurück
→ SearchIndexRepository :: upsert(...)
→ is_private=1 wenn in privater AG
```

### Was OpenRouter sieht

**Beim API-Call**:
- Beitrag-Inhalt (Text, Titel)
- Autorenname (kein User-ID)
- Keine User-Cookies, Sessions, IPs

**Nicht gesendet**:
- Benutzer, die den Beitrag lesen
- Suchquery des Lesers (nur Beitrags-Embedding, nicht Query-Embedding)
- Zeitstempel von Zugriffen

### Datenschutz-Konformität

**DSGVO-Anforderungen**:

1. **Transparenz**
   - Datenschutzerklärung erwähnt: "Asgaros-Forum und WordPress-Beiträge werden durchsucht"
   - Semantische Suche: "Inhalte werden an OpenRouter gesendet für Embeddings"

2. **Anonymisierung**
   - Query-Strings werden nicht geloggt (nur bei Fehler in WP_DEBUG log)
   - Embeddings sind nicht auf User verknüpfbar

3. **Auskunftsplicht**
   - Nutzer können via "Persönliche Daten exportieren" eigene Beiträge abrufen
   - SearchIndexRepository löscht automatisch Inhalte, wenn Beiträge gelöscht

4. **Löschung**
   - Beitrag gelöscht → Auto-Hook: `delete_post` → SearchIndexRepository::delete()
   - Asgaros-Post gelöscht → Adapter-Hook → SearchIndexRepository::delete()

5. **Drittland-Transfer**
   - OpenRouter kann Server außerhalb EU haben
   - Admin-Einstellung sollte Warnung enthalten

---

## Troubleshooting

### Semantic-Suche liefert keine Ergebnisse

**Ursachen**:
1. API-Key ungültig oder Quota erschöpft
   - **Fix**: API-Key auf [OpenRouter](https://openrouter.ai/account/keys) prüfen
   
2. `semantic_min_score` zu hoch (z.B. 0.95)
   - **Fix**: In Einstellungen auf 0.30–0.40 setzen
   
3. Keine Beiträge indexiert
   - **Fix**: Admin → AFSpaces Suche → "Jetzt neu indexieren" klicken
   - **Oder Terminal**: `php -c tests/php-cli.ini wordpress-cron-simulator search`

### LIKE-Fallback sehr langsam

**Symptom**: Query mit `"AB"` oder `"IT"` dauert Sekunden

**Grund**: Alle 3-Zeichen-Token triggern LIKE-Fallback

**Lösungen**:
1. `innodb_ft_min_token_size` auf 2 setzen (MySQL restart nötig)
2. Benutzer zu längeren Queries anleiten ("AB Support" statt "AB")

### OpenRouter-Fehler: "API Key invalid" 

**Debug-Tipps**:
1. Key in Einstellungen speichern (nicht im Quellcode!)
2. WP-Debuglog: `define('WP_DEBUG_LOG', true);`
3. EmbeddingClient::embed() loggt Response bei Fehler

### Modal wird nicht angezeigt

**Checklist**:
1. SearchModal::enqueue wurde aufgerufen (in FrontendController)
2. afspaces.css/afspaces-search.js laden (Developer Tools → Network)
3. `#afspaces-search-overlay` Element in DOM vorhanden (DevTools)
4. Browser-Konsole auf JS-Fehler prüfen

---

## Development & Erweiterung

### Unit-Tests

```bash
php -c tests/php-cli.ini vendor/phpunit/phpunit/phpunit -c phpunit.xml.dist
```

**Relevante Tests**:
- `tests/FulltextQueryTest.php` — Query-Builder
- `tests/VectorMathTest.php` — Cosine-Ähnlichkeit
- `tests/ResultFusionTest.php` — RRF-Fusion
- `tests/ForumSearchServiceTest.php` — Adapter-Integration

### E2E-Tests (Playwright)

```bash
cd e2e && npm test -- tests/invitations.spec.ts
```

**Such-Spezifikationen**:
- `e2e/tests/accessibility.spec.ts` — ARIA-Modal, Keyboard-Nav
- Such-Selektoren im Modal validieren

### Custom Hooks & Filter

```php
// Suchergebnisse vor Rückgabe filtern
apply_filters('afspaces_search_results', $results, $query);

// Suchindexierung kontrollieren
apply_filters('afspaces_should_index_post', true, $post_id);

// Benchmark für langsamme Queries
do_action('afspaces_search_slow_query', $query_time, $query_string);
```

### Integration mit eigenem Plugin

```php
// Eigene POST-Typen durchsuchbar machen
add_filter('afspaces_search_wp_post_types', function( $types ) {
  $types[] = 'custom_book';
  return $types;
});

// Semantik deaktivieren für Hosts
add_filter('afspaces_search_semantic_enabled', '__return_false');
```

---

## Performance-Tipps

### Große Foren optimieren

**Problem**: FULLTEXT-Suche auf 100k+ Posts ist langsam

**Lösungen**:
1. Scope=forum aktivieren (nur Asgaros durchsuchen, nicht WP)
2. Per-Page auf 5–10 reduzieren (default 10)
3. MySQL-Index überprüfen:
   ```sql
   SHOW INDEX FROM wp_asgaros_posts WHERE Key_name = 'fulltext';
   REPAIR TABLE wp_asgaros_posts;
   ```

### Embedding-Kosten senken

**Problem**: Semantische Suche kostet ~$0.02 pro 1M Tokens

**Lösungen**:
1. `semantic_min_score` erhöhen → weniger False Positives
2. Nur wichtige Post-Typen indizieren (`wp_post_types` kürzen)
3. `index_private=false` → Private AGs nicht indizieren
4. Batch-Size in SearchIndexer::reindex_all() begrenzen

### Modal-Ladezeiten

**Problem**: First Paint verzögert sich

**Lösungen**:
1. CSS/JS defer/async laden (SearchModal::enqueue)
2. REST-Abfrage erst beim Focus auf Input starten
3. Query <3 Chars ablehnen ("zu kurz")

---

## Zusammenfassung

| Aspekt | Details |
|---|---|
| **Suchtyp** | Hybrid (Keyword + Semantic) |
| **Keyword-Index** | Asgaros FULLTEXT, WP native |
| **Semantic-Index** | `wp_afspaces_search_index` (Embeddings) |
| **API-Provider** | OpenRouter (perplexity/pplx-embed-v1-0.6b) |
| **Datenschutz** | API-Key serverseitig nur, Queries nicht geloggt |
| **Private Inhalte** | Optional indexierbar, `is_private` Flag |
| **Shortcodes** | `[afspaces_search_link]`, `[afspaces_search_button]` |
| **REST-API** | `GET /afspaces/v1/search` (public keyword, authenticated semantic) |
| **Frontend** | Modal-Overlay site-weit, Progressive Enhancement |
| **Tests** | 78 Unit-Tests, E2E via Playwright |
| **Einstellungen** | Admin → Einstellungen → AFSpaces Suche |

---

## Links & Referenzen

- **OpenRouter Docs**: https://openrouter.ai/docs
- **Asgaros Forum**: https://de.asgarosforum.com
- **WordPress REST API**: https://developer.wordpress.org/rest-api
- **PHP VectorMath**: `src/Search/VectorMath.php`
- **SearchIndexer Cron**: `wp-cron afspaces_reindex_search` (täglich)
