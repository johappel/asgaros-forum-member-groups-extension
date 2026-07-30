# Bereich: Suche

Eigene Suchplattform über Asgaros- und WordPress-Inhalte. Fachlicher Überblick in [../SUCHE.md](../SUCHE.md), REST-Parameter in [REST-API.md](REST-API.md).

## Komponentenkarte

| Aufgabe | Klasse | Datei |
| --- | --- | --- |
| Query-Bau (Fulltext/Boolean) | `FulltextQuery` | `src/Search/FulltextQuery.php` |
| Snippet + Highlight | `SnippetBuilder` | `src/Search/SnippetBuilder.php` |
| Treffer-DTO | `SearchHit` | `src/Search/SearchHit.php` |
| Forensuche (Adapter-Mapping) | `ForumSearchService` | `src/Application/ForumSearchService.php` |
| WordPress-Suche | `WpPostSearch` | `src/Search/WpPostSearch.php` |
| Rangfusion (RRF) | `ResultFusion` | `src/Search/ResultFusion.php` |
| Fusion/Orchestrierung | `HybridSearchService` | `src/Application/HybridSearchService.php` |
| Embeddings-Client | `EmbeddingClient` | `src/Search/EmbeddingClient.php` |
| Vektor-Mathematik | `VectorMath` | `src/Search/VectorMath.php` |
| Vektorsuche | `VectorSearch` | `src/Search/VectorSearch.php` |
| Index-Repository | `SearchIndexRepository` | `src/Adapters/Database/SearchIndexRepository.php` |
| Indexer + Cron | `SearchIndexer` | `src/Application/SearchIndexer.php` |
| Einstellungen (VO) | `SearchSettings` | `src/Search/SearchSettings.php` |
| Hub-Suchseite | `SearchView` | `src/Interface/SearchView.php` |
| Site-weites Overlay | `SearchModal` | `src/Interface/SearchModal.php` |
| Admin-Seite | `SearchSettingsPage` | `src/Interface/SearchSettingsPage.php` |

## Ein-/Ausstiegspunkte

- REST: `GET /wp-json/afspaces/v1/search` → `RestController::search_forum` → `HybridSearchService`.
- Hub-View: `afspaces_view=search` (`SpacesUrls::VIEW_SEARCH`).
- Shortcodes: `[afspaces_search]`, `[afspaces_search_button]`, `[afspaces_search_link]`.
- Overlay-Assets: `assets/afspaces-search.js`, Styles in `assets/afspaces.css`.
- Asgaros-Suche wird via `SpacesHubController::redirect_forum_search` (Hook `template_redirect`) auf die AFSpaces-Suche umgeleitet.

## Scope & Modi

- `scope`: `all` (Fusion), `forum` (nur Asgaros), `wp` (nur WordPress).
- `mode`: `any` (OR) / `all` (AND); `in`: `all` / `title`.
- Kurze Tokens (< FULLTEXT-Mindestlänge): LIKE-Fallback (`FulltextQuery::needs_like_fallback`).
- Aktive Filter (Autor/Zeitraum/Forum) erzwingen Keyword-Modus und deaktivieren Semantik.

## Semantische Suche

- Standardmäßig aus; ohne API-Key sauberer No-op.
- Index-Tabelle `wp_afspaces_search_index` (Embedding als LONGBLOB via `VectorMath::pack/unpack`, `content_hash`-Skip).
- Live-Zugriffsfilter bei jeder Abfrage über `list_accessible_category_ids`.
- Cron-Hook `afspaces_reindex_search` (`SearchIndexer::CRON_HOOK`), täglich; plus `save_post`/`trashed_post`/`deleted_post`.
- Mindest-Score `semantic_min_score` (Default 0.30) filtert schwache Treffer.

## Einstellungen

Siehe [SETTINGS-PAGES.md](SETTINGS-PAGES.md) (Option `afspaces_search_options`). Reindex-Button: `admin_post_afspaces_search_reindex` → `SearchSettingsPage::handle_reindex`.

## Erweiterungspunkte

- WP-Query-Argumente: Filter `afspaces_wp_search_args`.
- Neue Suchparameter: `RestController` (`/search` args) + `HybridSearchService` + `SearchView`/Overlay gemeinsam anpassen.

## Tests

- `tests/FulltextQueryTest.php`, `tests/SnippetBuilderTest.php`, `tests/ForumSearchServiceTest.php`
- `tests/ResultFusionTest.php`, `tests/VectorMathTest.php`
