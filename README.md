# Asgaros Forum Spaces

AFSpaces ist ein eigenständiges WordPress-Plugin für eine verständliche, sichere und frontend-first Verwaltung von Arbeitsgruppen auf Basis von Asgaros Forum.

## Installation

### Voraussetzungen

- **WordPress:** > = 7.0 (getestet mit 7.0.2)
- **PHP:** >= 8.1
- **Asgaros Forum:** >= 3.0.0 (empfohlen und mit 3.4.0 getestet)
- **Datenbank:** MySQL/MariaDB mit Standard-WordPress-Tabellen

### Einrichtung

1. Plugin-Dateien in `wp-content/plugins/asgaros-forum-member-groups-extension/` ablegen oder ZIP über „Plugins → Installieren“ hochladen.
2. **Asgaros Forum vorher installieren und aktivieren**, sonst blockt die Anforderungsprüfung die Aktivierung.
3. Plugin aktivieren. Falls eine ältere Asgaros-Version läuft, zeigt das Dashboard eine konkrete Fehlermeldung.
4. WordPress-Seite anlegen (z. B. „Räume“) und Shortcode `[afspaces]` einfügen — das ist die zentrale Hub-Seite mit allen Unteransichten.
5. Rechte prüfen: Administratoren haben volle Berechtigung; Manager/Owner einzelner Räume erhalten automatisch die passenden Capabilities.

### Einstellungen

Unter **Einstellungen → AFSpaces Look & Feel** werden Farben, Schriften und Abstände des Frontends angepasst. Voreingestellte Presets (Asgaros, Neutral, Kontrast) wechseln das gesamte Farbschema mit einem Klick.

Unter **Einstellungen → AFSpaces Raumgründung** legt der Administrator fest, ob Mitglieder selbst Räume gründen dürfen, welche Sichtbarkeiten erlaubt sind und ob neu gegründete Räume freigegeben werden müssen.

### Deaktivierung und Deinstallation

**Deaktivierung** blockiert weder WordPress noch Asgaros Forum. Es werden nur der geplante Reindex-Cron-Job und die Rewrite-Rules entfernt. Die Hub-Seite und alle AFSpaces-Daten bleiben erhalten; das Forum funktioniert ganz normal weiter.

**Deinstallation** löscht alle AFSpaces-Daten unwiderruflich:
- alle eigenen Tabellen (`wp_afspaces_*`),
- die Hub-Seite,
- die Plugin-Optionen sowie
- die angelegten Capabilities.

**Asgaros-Daten** (Foren, Gruppen, Kategorien, Beiträge, Benutzergruppen) bleiben unangetastet.

> **Empfehlung:** Sichere vor der Deinstallation die eigenen AFSpaces-Tabellen oder exportiere relevante Daten (z. B. Räume, Einladungen, Audit-Log), falls sie später benötigt werden. Nach der Deinstallation sind sie nicht mehr wiederherstellbar.

## Hauptfunktionen

- **Arbeitsgruppen (Spaces):** Jeder Space verknüpft ein Asgaros-Forum mit einer zugriffssteuernden Benutzergruppe. Manager und Owner verwalten Mitglieder direkt im Frontend.
- **Mitgliederverwaltung:** Hinzufügen, Entfernen und Suche nach WordPress-Benutzern mit serverseitiger Prüfung, Nonces und Audit-Log.
- **Einladungen:** Persönliche Einladungen mit Annahme/Ablehnung, Widerruf und Ablaufdatum. Token werden gehasht gespeichert.
- **Einladungslinks:** Kryptografische Links mit Nutzungslimit, Ablaufdatum und optionalem Beitrittsanfrage-Modus.
- **Beitrittsanfragen:** Mitglieder können die Aufnahme in geschlossene Räume anfragen; Manager genehmigen oder lehnen ab.
- **Raumgründung:** Mitglieder beantragen oder gründen (je nach Policy) private Arbeitsgruppen; der Administrator kann Räume freigeben, archivieren oder löschen.
- **Suche:** Beitrag-genaue Forensuche mit Deep-Links, Hybridsuche über WordPress-Inhalte und optionale semantische Suche.
- **REST-API:** Versionierte Endpunkte unter `/wp-json/afspaces/v1` für alle Kernaktionen mit Paginierung, Schema-Validierung und Rate-Limits.
- **Barrierefreiheit:** No-JS-Fallback, Tastaturbedienung und semantische Auszeichnung für alle Kernpfade.

## Entwickler-Einstieg

Die kanonische Entwicklerdokumentation liegt in docs/:

- [docs/INDEX.md](docs/INDEX.md) — Einstieg und Dokumentenstruktur
- [docs/UEBERBLICK.md](docs/UEBERBLICK.md) — Produktbild und aktueller Funktionsstand
- [docs/ARCHITEKTUR.md](docs/ARCHITEKTUR.md) — Schichten, Persistenz und Request-Flows
- [docs/FEATURE-STATUS.md](docs/FEATURE-STATUS.md) — konsolidierter Umsetzungsstand aller MVPs
- [docs/SUCHE.md](docs/SUCHE.md) — Sucharchitektur und Suchbetrieb
- [docs/ERWEITERN-UND-QUALITAET.md](docs/ERWEITERN-UND-QUALITAET.md) — Änderungswege, Tests und Leitplanken

## Verbindliche Spezifikationen

Die Root-Dokumente bleiben die normativen Leitplanken für Produkt, Sicherheit und Kompatibilität:

- GOAL.md
- AGENTS.md
- ARCHITECTURE.md
- SECURITY_PRIVACY.md
- ACCESSIBILITY.md
- TESTING.md
- COMPATIBILITY.md

## Historie

Frühere Task-Tracker und Spezial-READMEs wurden in archive/ zusammengeführt, damit der Root nicht mehr zugleich Planungs- und Zieldokumentation mischt. Die Archivübersicht liegt in archive/INDEX.md.
