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
| `text_color` | `#3a4f66` |
| `link_color` | `#2d5d7f` |
| `breadcrumb_text_color` | `#3a4f66` |
| `wrapper_background` | `#d9d9d9` |
| `wrapper_border_color` | `#d9d9d9` |
| `wrapper_border_radius` | `30` |
| `nav_background` | `#2d5d7f` |
| `nav_text_color` | `#ffffff` |
| `nav_active_background` | `#2d5d7f` |
| `nav_active_text_color` | `#ffffff` |
| `pager_background` | `#d9d9d9` |
| `pager_text_color` | `#3a4f66` |
| `button_primary_bg` | `#2d5d7f` |
| `button_secondary_bg` | `#364149` |
| `button_text_color` | `#ffffff` |
| `button_secondary_text_color` | `#ffffff` |
| `button_hover_bg` / `button_hover_text_color` | `#f5ae35` / `#3a4f66` |

Presets: `Asgaros-Nah`, `Neutral`, `Kontrastreich`, plus Reset. Kontrastreich existiert bewusst als Barrierefreiheits-Option.

## Zentrale EfabiNet-Farbvariablen

Die Frontend-Styles definieren die verbindlichen Rollen als CSS Custom
Properties auf `#af-wrapper`:

| Variable | Wert | Verwendung |
| --- | --- | --- |
| `--afspaces-color-blue` | `#2d5d7f` | Feste Primärfarbe für Navigation und Primäraktionen |
| `--afspaces-color-yellow` | `#f5ae35` | Hover, Primäraktionen und Akzent |
| `--afspaces-color-purple` | `#561188` | Lila Akzent |
| `--afspaces-color-text` | `#3a4f66` | Lauftext und Text auf gelben Primäraktionen |
| `--afspaces-color-secondary-background` | `#364149` | Sekundäre Hintergründe |
| `--afspaces-color-light-background` | `#d9d9d9` | Heller Oberflächenhintergrund |
| `--afspaces-heading-color` | Einstellung `heading_color` | Überschriften |
| `--afspaces-link-color` | Einstellung `link_color` | Textlinks |

`AppearanceSettingsPage::build_inline_css()` setzt die konfigurierbaren Rollen
`--afspaces-heading-color` und `--afspaces-link-color` aus den Einstellungen.
Die feste Variable `--afspaces-color-blue` bleibt davon getrennt und kann nicht
durch eine Überschriften- oder Linkfarbe umgebogen werden.
Die Farbfelder der Settingspage akzeptieren Copy-and-paste-Hexwerte; die
Darstellung wird client- und serverseitig auf sechsstellige `#RRGGBB`-Werte
normalisiert.

## Arbeitsgruppen-Subnavigation

Die raumbezogene Subnavigation wird in
`SpacesHubController::render_space_context_navigation()` als Tab-Leiste
gerendert. `assets/afspaces.css` überschreibt dafür die allgemeine
`afspaces-hub-nav`-Fläche: Die Tab-Leiste bleibt transparent und hat nur eine
dünne blaue Linie am unteren Rand. Inaktive Tabs bleiben transparent, während
nur der aktive Tab weiß hinterlegt wird und eine blaue Unterkante erhält. Beim
Hover wird der Tab-Text blau und unterstrichen; die Auswahl ist damit nicht nur
über Farbe allein erkennbar.

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
als zugängliche Radio-Auswahlkacheln. Jede Kachel zeigt ein Farbfeld, den
Farbnamen und den Hexwert; die aktuell gespeicherte Auswahl erhält eine
farbige Umrandung und bleibt damit sofort erkennbar. WorkingGroupMeta::
normalize_accent_color() normalisiert zusätzlich historische oder manipulierte
Fremdwerte auf den Standard #2d5d7f. `assets/afspaces.js` aktualisiert die
sichtbare Markierung beim Wechsel unmittelbar.

### Arbeitsgruppen-Einstellungen

`WorkingGroupSettingsView::render()` ordnet die Seite in allgemeine Angaben
ohne zusätzliche sichtbare Abschnittsüberschrift, Darstellung, Zugang und Mitgliedschaft,
Verantwortliche,
Verwaltung und Gefahrenbereich. Die normale Konfiguration wird in einem
Formular mit dem Button „Änderungen speichern“ gesendet. „Arbeitsgruppe
ansehen“ bleibt ein GET-Link im Seitenkopf; Owner-Übertragung, Archivierung,
Reaktivierung und Löschung sind davon getrennte Formulare. Die Join-Auswahl
zeigt nur die drei fachlichen Zustände Anfrage, Einladung und keine neuen
Mitglieder und verwendet weiterhin die bestehende `WorkingGroupMeta`-Struktur.
Die öffentliche Sichtbarkeit wird in dieser Nutzeransicht nicht angeboten;
die administrative Sichtbarkeits-Policy bleibt für eine spätere Freigabe
erhalten. Die Verantwortlichen-Sektion verlinkt für Rollenänderungen in die
Mitgliederverwaltung.

Der Abschnitt „Zugang und Mitgliedschaft“ enthält zwei semantisch getrennte
`fieldset`-Radio-Gruppen: „Wer darf die Beiträge dieser Arbeitsgruppe lesen?“
und „Wer kann Mitglied werden und Beiträge verfassen?“. Die Leseoptionen heißen
„Nur Mitglieder der Arbeitsgruppe“ und „Alle angemeldeten Personen“ und
erklären ausdrücklich, dass Lesen keine Mitgliedschaft oder Schreibberechtigung
erzeugt. Die drei Beitrittsoptionen heißen „Beitritt auf Anfrage oder mit einem
Einladungslink“, „Nur über Einladungslink“ und „Keine neuen Mitglieder“ und
tragen jeweils einen eigenen Hilfetext. Ein Hinweis stellt klar, dass
Moderationsrechte an anderer Stelle vergeben werden. Die beiden Gruppen
verwenden unterschiedliche `name`-Attribute; die Erläuterungen sind über
`aria-describedby` den jeweiligen Eingaben zugeordnet.

## Hub-Layout

- Eine Hub-Seite (`[afspaces]`) mit Router (`SpacesHubController`), Brotkrümel und zweistufiger Navigation.
- Top-Navigation: hubweite Ansichten. Space-Kontext-Navigation nur beim Verwalten einer konkreten Arbeitsgruppe (Details/Mitglieder/Einladungen/Beitrittsanfragen/Moderation).
- Wrapper-ID `#af-wrapper`; CSS in `assets/afspaces.css`. Einige Regeln sind bewusst unscoped, damit sie auch im site-weiten Such-Overlay greifen.

## Asgaros-Forum-Override-Layer

### Abonnement-Navigation (Issue #9)

`AsgarosAdapter::relocate_subscription_navigation()` ordnet die Aktion
„Forum abonnieren“ bzw. „Thema abonnieren“ als normales Nav-Item direkt vor
„Abonnements“ ein. Dadurch gelten dieselben lesbaren Farben, Fokuszustände und
das mobile Verhalten wie für die übrige Asgaros-Navigation. Ein zusätzlicher
Button oberhalb des Headers wird nicht gerendert. Bei globalem Abonnement
bleibt ausschließlich die zentrale Abonnementverwaltung sichtbar.

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

Die Inline-Moderationsaktionen werden von
`ForumModerationControls::render_controls()` zunächst in das vorhandene
`.forum-post-menu` eingehängt. `assets/afspaces.js` portaliert die zugehörigen
`.afspaces-mod-move-form`-Formulare anschließend aus dem Post-Wrapper in den
übergeordneten `#af-wrapper` (mit `document.body` als Fallback) und richtet sie
per `position: fixed` am jeweiligen `summary` aus. So bleiben die Auslöser im
Beitragsmenü, während Asgaros-`overflow`-Regeln im `.post-wrapper` die
Formulare nicht mehr abschneiden. Die Sichtbarkeit und
`aria-expanded` werden weiter durch das native `details`-Toggle gesteuert;
die serverseitigen Nonces und POST-Aktionen bleiben unverändert.

### Konsolidierte Moderationsaktionen

`ForumModerationControls::render_controls()` und `ModerationView` rendern
lokale Aktionen nur, wenn `ModerationActionVisibility::should_render_local_action()`
die lokale AFSpaces-Berechtigung bestätigt und
`AsgarosAdapter::can_perform_moderation_action()` keinen gleichwertigen
nativen Asgaros-Bedienweg meldet. Die Entscheidung erfolgt getrennt für
Thema löschen, Thema verschieben, An-/Abpinnen, Öffnen/Schließen und Beitrag
löschen. „Beitrag verschieben“ bleibt als lokale Ergänzung sichtbar, weil
Asgaros dafür keinen nativen Post-Menüpunkt besitzt.

Das ist ausschließlich eine serverseitige Rendering-Entscheidung und keine
Sicherheitsmaßnahme. Die Nonce-, Capability-, Raum- und Objektprüfungen der
AFSpaces-Handler bleiben unabhängig davon bestehen.

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
