# COMPATIBILITY.md

## Zweck

Dieses Dokument wird während der Entwicklung mit konkret geprüften Versionen und internen Asgaros-Schnittstellen aktualisiert.

## Ausgangslage

Asgaros dokumentiert zahlreiche Actions und Filters für Frontend-Erweiterungen sowie Ereignisse beim Hinzufügen oder Entfernen von Benutzern aus Benutzergruppen. Eine vollständige öffentliche CRUD-API für Foren und Benutzergruppen ist daraus nicht ersichtlich.

## Geprüfte Versionen (Stand MVP 1)

- **Getestete WP Local-Instanz:** Asgaros Forum `3.4.0`.
- **Mindestversion (`AFSPACES_MIN_ASGAROS_VERSION`):** `3.0.0` (vorläufig; wird mit Adapter-Recherche in M1.2 präzisiert).
- **Hauptklasse:** `AsgarosForum` (definiert in `asgaros-forum/includes/forum.php`, instanziiert in `asgaros-forum.php`).
- **Versionserkennung:** Konstante `ASGAROS_FORUM_VERSION` sofern definiert, sonst `get_plugin_data()` auf `asgaros-forum/asgaros-forum.php`.
- **Aktivitätsprüfung:** `class_exists('AsgarosForum')` bzw. `is_plugin_active('asgaros-forum/asgaros-forum.php')`.

> Hinweis: Die interne Gruppen- und Foren-API (für M1.2) ist noch nicht geprüft. Schreibende Adapter-Methoden bleiben bis dahin deaktiviert.

## Verpflichtende Prüfung

Vor MVP 1 sind zu dokumentieren:

- unterstützte Asgaros-Versionen,
- interne Klassen und Methoden für Benutzergruppen,
- interne Klassen und Methoden für Foren,
- Datenbanktabellen, die nur indirekt über Adapter angesprochen werden,
- Verhalten bei Plugin-Updates,
- Fallback bei unbekannter Version.

## Kompatibilitätsstrategie

- Versionsprüfung beim Start.
- Lesen darf bei sicherer Abwärtskompatibilität möglich bleiben.
- Schreiben wird bei unbekannter Version deaktiviert.
- Administrator erhält konkrete Diagnose.
- Adaptertests laufen gegen jede offiziell unterstützte Version.
- Änderungen an internen APIs führen zu einer neuen Adapterversion, nicht zu Änderungen in Domain oder UI.

## MVP 3 Hinweis

- MVP 3 nutzt keine zusätzlichen Asgaros-Interna. Invite-Links greifen weiter ausschließlich über den vorhandenen Adapter auf Foren-Metadaten, Gruppenmitgliedschaften und Gruppenzuordnungen zu.

## Forum-Navigation (UX-Integration)

Die Frontend-Verwaltung ist über die Asgaros-Forum-Navigation erreichbar. Dafür werden ausschließlich **dokumentierte, öffentliche Asgaros-Hooks** genutzt (keine internen Klassen):

- Filter `asgarosforum_filter_header_menu` — fügt den Menüpunkt „Räume" in die Forum-Navigation ein (nur für berechtigte, angemeldete Benutzer).
- Action `asgarosforum_overview_custom_content_top` — rendert ein kompaktes Einstiegs-Panel auf der Forum-Übersicht.

Die eigentliche Verwaltung läuft über eine einzelne WordPress-Hub-Seite (Slug `afspaces`) mit dem Shortcode `[afspaces]`. Die Unteransicht wird über den Query-Parameter `afspaces_view` gesteuert (`dashboard`, `members`, `invitations`, `my-invitations`, `create`). Es wird **kein** eigener Asgaros-`current_view` in das Forum-Routing eingehängt, da hierfür keine dokumentierte API existiert.

Alte Einzelseiten (`afspaces-dashboard`, `afspaces-members`, `afspaces-invitations`, `afspaces-my-invitations`) werden per 301 auf die entsprechende Hub-Unteransicht umgeleitet.

