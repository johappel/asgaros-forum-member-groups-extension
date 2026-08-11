# Settings-Pages-Referenz

Die AFSpaces-Optionen liegen zentral als Untermenü von `Asgaros Forum` (`admin.php?page=afspaces-settings`). Die Capability ist durchgehend `manage_options`. Die Seite nutzt native WordPress-Tabs und die WordPress-Settings-API; sie beeinflusst nur AFSpaces (keine Asgaros-Interna).

## Zentrale Seite

- Klasse: `src/Interface/AFSpacesSettingsPage.php`
- Parent-Menü: `asgarosforum-structure` (Asgaros-Seite „Struktur")
- Seiten-Slug: `afspaces-settings`
- URL-Muster: `admin.php?page=afspaces-settings&tab={tab}`
- Tabs: `appearance`, `creation`, `search`, `installation`
- Standardtab: `appearance`
- Capability: `manage_options`

Die früheren direkten Seiten-Slugs (`afspaces-appearance`, `afspaces-look-and-feel`, `afspaces-creation`, `afspaces-search`, `afspaces-installation`) werden über `AFSpacesSettingsPage::redirect_legacy_pages()` dauerhaft auf den passenden Tab weitergeleitet. Optionsschlüssel und Settings-Gruppen bleiben dabei unverändert.

## Darstellung

- Klasse: `src/Interface/AppearanceSettingsPage.php`
- Tab: `appearance` (Titel „Arbeitsgruppen-Darstellung")
- Option: `afspaces_appearance_options`
- Anwendung: `enqueue_inline_style()` hängt Inline-CSS an `afspaces-frontend`; site-weit über `SearchModal`.

Feldschlüssel (Auszug): `base_font_family`, `heading_font_family`, `base_font_size`, `heading_color`, `text_color`, `link_color`, `breadcrumb_text_color`, `wrapper_background`, `wrapper_border_color`, `wrapper_border_radius`, `nav_background`, `nav_text_color`, `nav_active_background`, `nav_active_text_color`, `pager_background`, `pager_text_color`, `button_primary_bg`, `button_secondary_bg`, `button_text_color`.

Presets: `Asgaros-Nah`, `Neutral`, `Kontrastreich` plus Reset auf Standard.

## Raumgründung

- Klasse: `src/Interface/SpaceCreationSettingsPage.php`
- Tab: `creation` (Titel „AFSpaces Raumgründung")
- Option: `afspaces_creation_options` (`SpaceCreationSettings::OPTION`)
- Settings-Group: `SpaceCreationSettingsPage::GROUP`

| Feld | Default | Zweck |
| --- | --- | --- |
| `enabled` | `false` | Selbstgründung aktivieren |
| `allowed_roles` | `[]` | erlaubte Rollen (leer = alle angemeldeten) |
| `max_spaces_per_user` | `3` | Quote aktiver Räume pro Nutzer |
| `allowed_visibilities` | `[private]` | erlaubte Sichtbarkeiten gesamt |
| `regular_visibilities` | `[private]` | Sichtbarkeiten für nicht-privilegierte Nutzer |
| `require_approval` | `true` | Freigabepflicht |
| `name_min_length` / `name_max_length` | `3` / `60` | Namensgrenzen |
| `description_max_length` | `2000` | Beschreibungslänge |
| `reserved_names` | `admin, administrator, moderator, system, support, afspaces` | gesperrte Namen |
| `rate_limit_seconds` | `300` | Drossel zwischen Gründungen |
| `default_icon` | `users` | Standard-Icon |

Konsumiert von `SpaceCreationPolicy` und `SpaceCreationService`.

## Installation

- Klasse: `src/Interface/InstallationSettingsPage.php`
- Tab: `installation`
- Option: `afspaces_cleanup_on_uninstall`
- Settings-Gruppe: `afspaces_installation_group`
- Capability: `manage_options`
- Default: `false`

Das Kontrollkästchen aktiviert bewusst die vollständige Entfernung eigener AFSpaces-Tabellen, Optionen und der mit `_afspaces_managed_page=1` markierten Hub-Seite bei der Deinstallation. Asgaros-Daten werden nie entfernt. Ohne Opt-in bewahrt `Uninstaller::uninstall()` die AFSpaces-Daten.

## Suche

- Klasse: `src/Interface/SearchSettingsPage.php`
- Tab: `search` (Titel „Arbeitsgruppen-Suche")
- Option: `afspaces_search_options` (`SearchSettings::OPTION_KEY`)
- Settings-Group: `afspaces_search_group`
- Reindex: `admin_post_afspaces_search_reindex` → `handle_reindex` (Nonce via `check_admin_referer`)

| Feld | Default | Zweck |
| --- | --- | --- |
| `embedding_enabled` | `false` | semantische Suche aktivieren |
| `embedding_api_url` | `https://openrouter.ai/api/v1/embeddings` | Embedding-Endpunkt |
| `embedding_api_key` | `''` | API-Key (maskiert, nie im Frontend; leerer Submit überschreibt nicht) |
| `embedding_model` | `perplexity/pplx-embed-v1-0.6b` | Modell (1024 dims) |
| `index_private` | `false` | private Inhalte einbetten (Opt-in) |
| `index_wp` | `true` | WordPress-Inhalte indexieren |
| `wp_post_types` | `[post, page]` | zu indexierende Beitragstypen |
| `wp_all_public_types` | `false` | alle öffentlichen Typen einbeziehen |
| `semantic_weight` | `1.0` | Fusionsgewicht Semantik |
| `keyword_weight` | `1.0` | Fusionsgewicht Keyword |
| `semantic_min_score` | `0.30` | Cosine-Mindestähnlichkeit |
| `replace_wp_search` | `false` | normale WP-Suchformulare ersetzen |

Konsumiert von `EmbeddingClient`, `VectorSearch`, `SearchIndexer`, `WpPostSearch`, `HybridSearchService`, `SearchModal`.

## Neue Optionsseite hinzufügen

1. Tab in `AFSpacesSettingsPage::tabs()` ergänzen und die zugehörige Renderklasse injizieren.
2. Bestehende oder neue Settingsklasse mit `init()` und `register_setting` anbinden.
3. `register_setting` mit `sanitize_callback` und `default` verwenden.
4. Instanziierung und `init()` in `Plugin::init` verdrahten.
5. Werte über eine dedizierte Settings-/VO-Klasse kapseln, nicht direkt `get_option` in der Geschäftslogik streuen.
