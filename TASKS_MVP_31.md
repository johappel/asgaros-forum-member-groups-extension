# TASKS MVP 3.1 - Join Request Extension

## Ziel

Nicht eingeladene, angemeldete Benutzer koennen geschlossene Räume entdecken und einen Beitritt anfragen. Raumverantwortliche koennen Anfragen genehmigen oder ablehnen.

## Bereits abgeschlossen

### M3.1.1 Grundlagen und Architektur

- [x] Scope auf Discovery + Join-Request ohne Invite-Link-Overhead festgelegt.
- [x] Architektur entlang bestehender Layer (Domain, Application, Adapters, Interface) beibehalten.
- [x] Sicherheits- und Accessibility-Anforderungen aus AGENTS.md als Leitplanken uebernommen.

### M3.1.2 Persistenz und Domain

- [x] Domain-Modell JoinRequest erstellt.
- [x] Repository fuer Join-Requests mit eigener Tabelle erstellt.
- [x] Aktivierung/Deinstallation fuer Join-Request-Tabelle verdrahtet.

### M3.1.3 Use-Cases und Services

- [x] JoinRequestService mit create/list/approve/reject erstellt.
- [x] Idempotenz fuer offene Anfrage pro Nutzer/Raum umgesetzt.
- [x] Genehmigung fuehrt konsistent zur Gruppenaufnahme.
- [x] E-Mail-Benachrichtigung bei Genehmigung/Ablehnung umgesetzt.

### M3.1.4 Frontend und Navigation

- [x] Neue Hub-Ansicht discover hinzugefuegt.
- [x] DiscoverView zum Entdecken und Anfragen erstellt.
- [x] Frontend-Actions create_join_request, approve_join_request, reject_join_request umgesetzt.
- [x] Manager-Ansicht in InvitationsView um Beitrittsanfragen erweitert.
- [x] Nutzeransicht MyInvitationsView um eigene Beitrittsanfragen erweitert.
- [x] Dashboard zeigt fuer reine Mitglieder (ohne Managerrolle) ihre Mitgliedschaften inkl. Einstiegslink ins Forum.

### M3.1.5 Invite-Link-Integration

- [x] approval_required im Invite-Link-Flow erzeugt nun echte Join-Requests statt nur Audit-Ereignis.

### M3.1.6 REST

- [x] Discover-Endpunkt hinzugefuegt.
- [x] Join-Request-Endpunkte (create/list/approve/reject) hinzugefuegt.

### M3.1.7 Tests (teilweise)

- [x] Unit-Tests fuer JoinRequest-Domain erstellt.
- [x] Integrationstests fuer JoinRequest-Flow erstellt.
- [x] InviteLink-Integrationstest auf persistente Join-Request-Erzeugung erweitert.
- [x] Relevante Unit-Tests lokal erfolgreich ausgefuehrt.
- [x] Join-Request- und Invite-Link-Integrationstests in lokaler WP-Umgebung erfolgreich ausgefuehrt.
- [x] REST-Sicherheitstests fuer neue Join-Request-Endpunkte erstellt und erfolgreich ausgefuehrt.

## Offen

### M3.1.8 Tests und Verifikation

- [x] Integrationstests in lauffaehiger WP-Umgebung (inkl. mysqli) ausfuehren.
- [x] REST-Sicherheitstests fuer neue Endpunkte erweitern (Permission, IDOR, Fehlermeldungen).
- [x] E2E-Tests fuer Discover + Join-Request + Manager-Entscheidung erstellen.
- [ ] Axe/Keyboard-Checks fuer neue Discover-/Join-Request-Oberflaechen ergaenzen.

### M3.1.9 Privacy und Doku

- [ ] Privacy-Exporter/-Eraser fuer Join-Request-Daten erweitern.
- [ ] README/TESTING/COMPATIBILITY um Join-Request-Flow aktualisieren.
- [ ] Changelog-Eintrag fuer MVP 3.1 vorbereiten.

## Akzeptanzkriterien

MVP 3.1 ist abgeschlossen, wenn ein angemeldeter Nicht-Mitgliedsbenutzer geschlossene Räume sehen und idempotent einen Beitritt anfragen kann, Raumverantwortliche die Anfrage entscheiden koennen, Genehmigung zur Mitgliedschaft fuehrt, Ablehnung keine Mitgliedschaft erzeugt, und die neuen Flows durch Unit-, Integration-, REST-, E2E- sowie Accessibility-Tests abgedeckt sind.
