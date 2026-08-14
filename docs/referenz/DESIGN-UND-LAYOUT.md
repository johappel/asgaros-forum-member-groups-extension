# Design- & Layout-Entscheidungen

Bewusste Gestaltungsentscheidungen und wo sie im Code liegen. Ziel: Asgaros-nahe Optik, vollständige Konfigurierbarkeit, Barrierefreiheit vor Effekt.

## Leitprinzipien

- Asgaros-nahe Standardoptik, damit sich AFSpaces wie Teil des Forums anfühlt.
- Serverseitiges Rendering als Basis; JavaScript nur als Verbesserung (Progressive Enhancement).
- Kein JavaScript-Framework; Vanilla-JS in `assets/afspaces.js` und `assets/afspaces-search.js`.
- Bedeutung nie allein über Farbe; immer zusätzlich Text, Icon-plus-Label oder Status.
- Touch-Ziele und Fokus sichtbar; Zoom bis 200 Prozent ohne Funktionsverlust.

## Konfigurierbares Erscheinungsbild

Zentraler Ort: `src/Interface/AppearanceSettingsPage.php`, Option `afspaces_appearance_options`. Styles werden via `wp_add_inline_style` an das Handle `afspaces-frontend` gehängt und site-weit über `SearchModal` geladen.

Standardwerte (Preset „Asgaros-Nah"):

| Schlüssel | Default |
| --- | --- |
| `base_font_family` / `heading_font_family` | `Quicksand, sans-serif` |
| `base_font_size` | `20` |
| `heading_color` | `#2d5d7f` |
| `text_color` | `#444444` |
| `link_color` | `#2d5d7f` |
| `breadcrumb_text_color` | `#888888` |
| `wrapper_background` | `#fafbfc` |
| `wrapper_border_color` | `#e1e8ed` |
| `wrapper_border_radius` | `30` |
| `nav_background` | `#2d5d7f` |
| `nav_text_color` | `#ffffff` |
| `nav_active_background` | `#ffffff` |
| `nav_active_text_color` | `#1d2f43` |
| `pager_background` | `#f2f2f2` |
| `pager_text_color` | `#888888` |
| `button_primary_bg` | `#2d5d7f` |
| `button_secondary_bg` | `#7f98ac` |
| `button_text_color` | `#ffffff` |

Presets: `Asgaros-Nah`, `Neutral`, `Kontrastreich`, plus Reset. Kontrastreich existiert bewusst als Barrierefreiheits-Option.

## Arbeitsgruppen-Subnavigation

Die raumbezogene Subnavigation wird in
`SpacesHubController::render_space_context_navigation()` als Tab-Leiste
gerendert. `assets/afspaces.css` überschreibt dafür die allgemeine
`afspaces-hub-nav`-Fläche: Der Hintergrund bleibt transparent, die Tabs sind
kleiner und dunkelgrau (`#50575e`). Beim Hover wird der Tab-Text unterstrichen.
Der aktive Tab wird zusätzlich durch dunkelblaue Schrift und eine untere
Linie (`#224c75`) eindeutig markiert; die Auswahl ist damit nicht nur über
Farbe allein erkennbar.

Subcontent-Karten verwenden `border-radius: 16px`, passend zur abgerundeten
AFSpaces-Oberfläche. Das Einladungslink-Formular wird als einspaltiges Grid
gerendert; der abschließende Erstellungsbutton steht dadurch immer unter den
Eingabefeldern und erhält zusätzlich einen vertikalen Abstand.

## Arbeitsgruppen-Akzentfarbe und Icon

Pro Arbeitsgruppe in `afspaces_space_meta`:

- `accent_color` (Default `#2d5d7f`) — via `sanitize_hex_color` gefiltert. Wird u. a. für Forenkategorie-Farben in `ForumNavigation::render_category_colors` genutzt.
- `icon` (Default `users`) — auf FontAwesome gemappt in `WorkingGroupService::icon_class`:

| Schlüssel | Klasse | Label |
| --- | --- | --- |
| `users` | `fas fa-users` | Menschen |
| `comments` | `fas fa-comments` | — |
| `book` | `fas fa-book` | — |
| `briefcase` | `fas fa-briefcase` | — |
| `lightbulb` | `fas fa-lightbulb` | — |

Farbe und Icon sind immer nur Ergänzung; Name und Status stehen zusätzlich als Text (keine reine Farb-/Symbolbedeutung).

### Corporate-Design-Palette für Arbeitsgruppen

Arbeitsgruppen dürfen keine freien Hex-Farben verwenden. Die serverseitig
erlaubte Palette liegt in WorkingGroupMeta::accent_colors():

- #77429e — EfabiNet-Lila
- #2d5d7f — EfabiNet-Blau
- #f5ae35 — EfabiNet-Orange
- #5563a5 — EfabiNet-Indigo

Die Bearbeitungsansicht verwendet WorkingGroupService::accent_color_options()
als Select. WorkingGroupMeta::normalize_accent_color() normalisiert zusätzlich
historische oder manipulierte Fremdwerte auf den Standard #2d5d7f.
Die Optionen tragen zusätzlich ihre Palettefarbe als Hintergrund und eine
kontrastierende Vordergrundfarbe.

## Hub-Layout

- Eine Hub-Seite (`[afspaces]`) mit Router (`SpacesHubController`), Brotkrümel und zweistufiger Navigation.
- Top-Navigation: hubweite Ansichten. Space-Kontext-Navigation nur beim Verwalten einer konkreten Arbeitsgruppe (Details/Mitglieder/Einladungen/Beitrittsanfragen/Moderation).
- Wrapper-ID `#af-wrapper`; CSS in `assets/afspaces.css`. Einige Regeln sind bewusst unscoped, damit sie auch im site-weiten Such-Overlay greifen.

## Asgaros-Forum-Override-Layer

Die dynamischen Arbeitsgruppenfarben werden in
ForumNavigation::render_category_colors() mit #af-wrapper und der
Kategorie-ID ausgegeben. Diese höhere Spezifität ist erforderlich, weil
Asgaros #af-wrapper .title-element ebenfalls mit !important stylt.

Forumbezogene Anpassungen an Asgaros gehören in
assets/afspaces-forum-overrides.css und werden über
AFSpaces\Interface\ForumStyleLayer::enqueue() geladen. Der Layer ist auf
#af-wrapper und forumbezogene Klassen begrenzt und wird nur auf Seiten mit
[forum] eingebunden. Er wird nach den registrierten Asgaros-Styles in die
WordPress-Styles-Warteschlange eingereiht.

asgaros-forum/skin/custom.css darf niemals direkt geändert werden: Asgaros
erzeugt diese Datei aus den Appearance-Einstellungen neu. Neue AFSpaces-Regeln
werden stattdessen in diesem Layer ergänzt. Die CSS Custom Properties am
#af-wrapper bilden die spätere Anbindung an AFSpaces-Darstellungsoptionen.

## Such-Overlay

## Arbeitsgruppen-KontextÃ¼berschrift

Bei jeder verwalteten Arbeitsgruppe steht zwischen Breadcrumbs und
Subnavigation die dynamische Kontextzeile
`#afspaces-space-context-heading.afspaces-space-context-title` mit dem Muster
`Arbeitsgruppe: <Forumsname>`. Der Forumsname stammt aus dem Asgaros-Adapter und
wird escaped ausgegeben. Die H2 der nachfolgenden Verwaltungsansichten
wiederholen den Forumsnamen nicht; sie lauten beispielsweise `Mitglieder
verwalten`, `Einladungen zur Arbeitsgruppe`, `Beitrittsanfragen`, `Moderation`
und `Beiträge moderieren`.

- `src/Interface/SearchModal.php` + `assets/afspaces-search.js`.
- Barrierearmer Dialog: `aria-modal`, Fokusfalle, Escape schließt, Rückgabe des Fokus.
- Spinner respektiert `[hidden]` (CSS-Regel `.afspaces-spinner[hidden]{display:none!important}`), weil eigene `display`-Regeln sonst `[hidden]` überschreiben.
- Trigger: Shortcodes, Asgaros-Suchformular (per JS statt Redirect), optional WP-Suchformulare.
- Ohne JavaScript bleibt die serverseitige Suchseite plus 302-Weiterleitung erhalten.

## Barrierefreiheit als Akzeptanzkriterium

Verbindliche Regeln in [ACCESSIBILITY.md](../../ACCESSIBILITY.md). Für Layoutarbeit besonders relevant:

- Drag-and-drop nur zusätzlich, nie als einzige Bedienung.
- Listen als semantische Liste oder Tabelle.
- Statusmeldungen über Live-Regionen (`aria-live`), z. B. Trefferzahl der Suche.
- Bestätigungen nicht nur als Toast; destruktive Aktionen mit expliziter Bestätigung (`data-afspaces-confirm`).
- Keine unbeschrifteten Icon-Buttons.

## Registrierung bestehender Foren

Die Ansicht `FrontendController::render_dashboard()` führt Administrator:innen bei der
Übernahme vorhandener Asgaros-Foren. Sie verwendet die Spalten `Forum`, `Zugriff`,
`AFSpaces-Status` und `Aktion`. Die Statuswerte sind `Registriert`, `Kann registriert
werden` und `Einrichtung erforderlich`; die Zugriffsspalte zeigt Gruppennamen, niemals
Asgaros-Term-IDs. Die Reihenfolge ist nach Handlungspriorität und anschließend nach
Forumname sortiert (`SpaceRegistrationService::list_registrable_forums()`).

Auf schmalen Viewports werden die Tabellenzeilen über `data-label` als lesbare Blöcke
dargestellt. Status-Badges verwenden zusätzlich immer ihren sichtbaren Text; Farbe ist
nicht der alleinige Informationsträger. Die serverseitigen Formulare, Nonces und
Berechtigungsprüfungen bleiben unverändert.

## Layout ändern

1. Globale Optik über `AppearanceSettingsPage`/Option erweitern, nicht über verstreute Inline-Styles.
2. Neue AFSpaces-Hub-Regeln in `assets/afspaces.css`; forumbezogene Asgaros-Overrides in assets/afspaces-forum-overrides.css.
3. Farbe/Icon nie als alleinigen Bedeutungsträger einsetzen.
4. Fokus, Tastaturpfad und `aria`-Attribute für jede neue interaktive Komponente prüfen.
