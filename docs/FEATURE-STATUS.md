# Feature-Status

Dieses Dokument konsolidiert die Arbeit aus den bisherigen TASKS-Dateien und beschreibt den tatsächlichen Umsetzungsstand als Entwicklerreferenz.

## Production Readiness (Issue #4)

Status: umgesetzt.

- Hub-Seite wird bei Aktivierung automatisch angelegt und über `afspaces_hub_page_id` sowie `_afspaces_managed_page` sicher wiederverwendet.
- Fremde Seiten mit dem Standard-Slug werden nicht übernommen oder beim Cleanup gelöscht; Titel und Slug der gespeicherten Hub-Seite bleiben erhalten.
- Deinstallation bewahrt Daten standardmäßig. Vollständiges Cleanup ist über `afspaces_cleanup_on_uninstall` ausdrücklich aktivierbar und lässt Asgaros-Daten unberührt.
- Produktionsvoraussetzungen sind PHP 8.1+, WordPress 7.0+ und Asgaros Forum 3.4.0+.
- Aktivierungsstatus wird einmalig im Backend mit Links zur Hub-Seite und Installationseinstellung angezeigt.
- Join-Request-Privacy exportiert persönliche Nachrichten und leert sie beim Eraser, während Status-, Zeit- und Nachweisdaten erhalten bleiben.

## MVP 1: Frontend-Mitgliederverwaltung

Status: im Kern umgesetzt und laut Task-Dokument abgeschlossen.

Abdeckung nach Task-Blöcken:

- M1.1 Plugin-Grundgerüst: umgesetzt.
- M1.2 Asgaros-Adapter: umgesetzt.
- M1.3 Space-Zuordnung: umgesetzt.
- M1.4 Capabilities und Policies: umgesetzt.
- M1.5 Frontend-Dashboard: umgesetzt.
- M1.6 Mitgliederansicht: umgesetzt.
- M1.7 Optionale Drag-and-drop-Ansicht: umgesetzt als Progressive Enhancement, Kernfunktion bleibt ohne JavaScript nutzbar.
- M1.8 Audit-Log: umgesetzt.
- M1.9 Fehler und Rückmeldungen: umgesetzt.

Enthalten:

- Plugin-Grundgerüst mit Aktivierung, Deaktivierung, Deinstallation, Requirements und Versionskonstanten.
- Asgaros-Adapter für Foren, Gruppen, Gruppenmitglieder und Gruppenzuordnungen.
- Space-Registrierung für bestehende Foren inklusive Owner- und Managerzuordnung.
- Zentrale Capabilities und SpacePolicy.
- Dashboard und Mitgliederansicht auf der Hub-Seite.
- Serverseitige WordPress-Benutzersuche.
- Direktes Hinzufügen und Entfernen von Mitgliedern.
- Audit-Log für relevante Änderungen.
- Verständliche Erfolgsmeldungen, idempotente Behandlung von Duplikaten und Race-Condition-Schutz.
- Progressive Enhancement inklusive optionaler Drag-and-drop-Unterstützung.

Wichtige Nachbesserungen, die bereits im Produktstand enthalten sind:

- Dashboard-Links zeigen auf die korrekten Hub-Ansichten.
- Orphaned Spaces werden im Dashboard gefiltert.
- Mitgliederzählungen und Gruppenauflösung bevorzugen die primäre Gruppe des Space statt unsicherer Kategoriezuordnungen.
- Kernpfad wurde für No-JS, Tastaturbedienung und E2E nachgezogen.

## MVP 2: Persönliche Einladungen

Status: funktionsfähig umgesetzt, mit wenigen offenen Abschlussarbeiten.

Abdeckung nach Task-Blöcken:

- M2.1 Einladungsmodell: umgesetzt.
- M2.2 Einladung erstellen: umgesetzt.
- M2.3 Benachrichtigungen: umgesetzt.
- M2.4 Frontend für eingeladene Personen: umgesetzt.
- M2.5 Einladungsverwaltung: umgesetzt.
- M2.6 Mitgliedschaft bei Annahme: umgesetzt.
- M2.7 Datenschutz: umgesetzt.

Enthalten:

- Invitation-Modell, Tabelle und Zustandsmaschine mit pending, accepted, declined, revoked, expired.
- Einladungserstellung für bestehende WordPress-Benutzer.
- Versand über wp_mail mit übersetzbaren und filterbaren Inhalten.
- Ansicht Meine Einladungen für eingeladene Personen.
- Annahme und Ablehnung im Frontend.
- Widerruf und erneuter Versand offener Einladungen.
- Mitgliedschaft erst nach Annahme.
- Audit-Ereignisse für Einladung, Annahme, Ablehnung und Widerruf.
- Privacy-Integration für Einladungsdaten.

Noch offen laut Task-Stand:

- E2E-Fall für abgelaufene Einladung als nicht mehr annehmbar.
- Fokusmanagement von Annahme- und Ablehnungsdialogen ausdrücklich absichern.

## MVP 3: Sichere Einladungslinks

Status: Kernfunktion umgesetzt, mit wenigen bewusst offenen Optionalpunkten.

Abdeckung nach Task-Blöcken:

- M3.1 Linkmodell: umgesetzt.
- M3.2 Link erstellen: umgesetzt.
- M3.3 Link verwenden: umgesetzt.
- M3.4 Freigabemodi: Kernmodi umgesetzt, optionale Zusätze nur teilweise.
- M3.5 Linkverwaltung: umgesetzt.
- M3.6 Missbrauchsschutz: umgesetzt.
- M3.7 Registrierung: umgesetzt innerhalb der dokumentierten Grenzen.

Enthalten:

- Invite-Link-Modell mit kryptografischem Zufallstoken und Hash-Speicherung.
- Ablaufdatum, Nutzungslimit, Statusableitung und Widerruf.
- Frontend-Erstellung von Links mit Bedingungen.
- Direkte Nutzung durch angemeldete Benutzer mit serverseitiger Prüfung.
- Login-Rücksprung und optionaler Registrierungsfluss.
- Freigabemodi für direkte Aufnahme oder Beitrittsanfrage.
- Missbrauchsschutz mit Rate-Limits, Logik gegen Information Leakage und sichere Redirects.
- Tests für Token-Vergleich, Race Conditions und Limit-Ausnutzung.

Noch offen laut Task-Stand:

- E-Mail-Domain-Einschränkung.
- Einladungscode zusätzlich zum Link.

## MVP 3.1: Beitrittsanfragen

Status: funktional weitgehend umgesetzt, aber noch nicht vollständig abgeschlossen.

Abdeckung nach Task-Blöcken:

- M3.1.1 Grundlagen und Architektur: umgesetzt.
- M3.1.2 Persistenz und Domain: umgesetzt.
- M3.1.3 Use Cases und Services: umgesetzt.
- M3.1.4 Frontend und Navigation: umgesetzt.
- M3.1.5 Invite-Link-Integration: umgesetzt.
- M3.1.6 REST: umgesetzt.
- M3.1.7 Tests: weitgehend umgesetzt.
- M3.1.8 Abschlussverifikation: teilweise offen.
- M3.1.9 Privacy und Doku: umgesetzt.

Enthalten:

- JoinRequest-Domainmodell und Persistenz.
- JoinRequestService für create, list, approve, reject.
- Idempotenz für offene Anfragen pro Benutzer und Arbeitsgruppe.
- Genehmigung erzeugt echte Asgaros-Mitgliedschaft.
- E-Mail-Benachrichtigungen bei Entscheidung.
- Discover-Ansicht und Manager-Tab für Beitrittsanfragen.
- Anzeige eigener Anfragen in der Nutzeransicht.
- REST-Endpunkte für Discovery und Join-Request-Aktionen.
- E2E-Tests für Discover- und Entscheidungsflow.

Noch offen laut Task-Stand:

- Zusätzliche Axe- und Keyboard-Prüfungen für die neuen Oberflächen.
- Join-Request-Privacy ist über `Plugin::init` und `JoinRequestRepository` integriert.
- README-, TESTING- und COMPATIBILITY-Abgleich sowie Changelog-Eintrag: umgesetzt.

## MVP 3.2: Arbeitsgruppenmodell für efabiNet

Status: teilweise umgesetzt, aber als Gesamtpaket noch offen.

Abdeckung nach Task-Blöcken:

- M3.2.1 Begriffe und Informationsarchitektur: teilweise umgesetzt.
- M3.2.2 Arbeitsgruppen-Metadaten: teilweise vorbereitet, aber nicht vollständig abgeschlossen.
- M3.2.3 Arbeitsgruppen entdecken und Übersicht: teilweise umgesetzt.
- M3.2.4 Verantwortlichkeit und Moderation trennen: in der Architektur bewusst umgesetzt.
- M3.2.5 Benachrichtigungen und Eskalation: teilweise offen.
- M3.2.6 Profilintegration: teilweise umgesetzt.
- M3.2.7 ACF- und Themenintegration: offen.
- M3.2.8 Migration und Kompatibilität: teilweise umgesetzt.
- M3.2.9 Arbeitsgruppenverwaltung im Frontend: teilweise umgesetzt.

Bereits sichtbar oder vorbereitet:

- Arbeitsgruppen-Terminologie ist in großen Teilen der Hub- und Forum-Navigation eingeführt.
- Discover-, Profil-, Mitglieder-, Einladungs- und Beitrittsansichten existieren bereits im Arbeitsgruppenkontext.
- WorkingGroupMeta als Domänenobjekt und SpaceMetaRepository als Persistenzbaustein sind vorhanden.
- ForumNavigation rendert gruppenbezogene Einstiege und Farbmarkierungen im Forum.
- Arbeitsgruppenbezogene Moderation ist bewusst von globaler Asgaros-Moderation getrennt.
- Tests für Terminologie und Metadaten existieren bereits im Repository.

Als noch nicht abgeschlossen zu betrachten:

- Vollständige Umstellung aller sichtbaren Begriffe ohne Restvokabular aus der Space-Terminologie.
- Durchgängige Metadatenverwaltung im Frontend, inklusive Beschreibung, Kontakt, Farbe, Symbol und Beitrittslogik.
- ACF-Themenintegration.
- Vollständige Profilintegration mit Sichtbarkeitsregeln.
- Benachrichtigungs- und Privacy-Vervollständigung.
- Vollständige Unit-, Integration-, REST-, E2E- und Accessibility-Abdeckung für das Arbeitsgruppenmodell.

## MVP 4: Private Arbeitsgruppen selbst gründen

Status: Kernfunktion umgesetzt, einzelne Folgearbeiten offen.

Abdeckung nach Task-Blöcken:

- M4.1 Globale Richtlinien: umgesetzt, mit dokumentierter Isolationsentscheidung statt zentraler Zielkategorie.
- M4.2 Raumassistent: weitgehend umgesetzt, Startmitglieder oder Start-Einladungen noch offen.
- M4.3 Erstellung: umgesetzt.
- M4.4 Freigabeprozess: weitgehend umgesetzt, Admin-Benachrichtigung noch offen.
- M4.5 Raumverwaltung: umgesetzt.
- M4.6 Lebenszyklus: weitgehend umgesetzt, Inaktiv-Markierung noch offen.
- M4.7 Quoten und Missbrauchsschutz: umgesetzt.
- M4.8 Vorlagen: offen.

Enthalten:

- Global aktivierbare Raumgründung mit SpaceCreationSettings.
- Policy für Berechtigung, Limits, Rate-Limits, reservierte Namen und erlaubte Sichtbarkeiten.
- Frontend-Assistent als zugängliche Ein-Seiten-Basis mit optionalem JS-Wizard.
- Erstellung dedizierter Asgaros-Kategorie, Gruppe und Forum pro neuer Arbeitsgruppe.
- Space-Datensatz, Owner-Zuordnung und Mitgliedschaft des Erstellers.
- Freigabeprozess mit pending, approve und reject.
- SpaceLifecycle mit pending, active, archived, rejected und deleted.
- Bearbeiten von Name, Beschreibung, Sichtbarkeit und Owner-Übertragung im Frontend.
- Archivierung, Reaktivierung und Löschung mit definierter Cleanup-Strategie.
- Arbeitsgruppenbezogene Moderation für Themen und Beiträge, einschließlich Verschieben.

Wichtige Architekturentscheidungen:

- Isolation privater Räume erfolgt über dedizierte Asgaros-Kategorien, weil Asgaros Zugriffe auf Kategorieebene steuert.
- Pending-Räume bleiben bis zur Freigabe restriktiv und erlauben weder Einladungen noch normale Mitgliederverwaltung.
- Moderation wird nicht über globale Asgaros-Rollen delegiert, sondern über eine eigene, Space-begrenzte Service-Schicht.

Noch offen laut Task-Stand oder Dokumentationsstand:

- Optionale Startmitglieder oder Einladungen direkt im Gründungsassistenten.
- Administrator-Benachrichtigungen bei pending-Räumen.
- Optionale Kennzeichnung inaktiver Räume.
- Raumvorlagen.
- Zusätzliche Owner-Transfer-Absicherung auf dedizierter Unit-Test-Ebene.
- Vollständige E2E-Abdeckung der Gründungs- und Lifecycle-Flows.

## Suche als eigener Arbeitsstrang

Status: weitgehend umgesetzt und produktiv relevant.

Abdeckung nach Suchphasen:

- Phase 0 bis 1: post-genaue Keyword-Suche mit Deep-Links umgesetzt.
- Phase 2: WordPress-Beiträge und Hybrid-Merge umgesetzt.
- Phase 3 bis 4: semantische Suche, Indexierung und Fusion umgesetzt.
- Phase 5: site-weites Overlay, Filter und UX-Ausbau umgesetzt.
- Phase 6: Suchqualitätsausbau mit Wortmodus, Titelsuche und LIKE-Fallback umgesetzt.

Enthalten:

- Beitrag-genaue Asgaros-Suche mit Deep-Link auf den konkreten Beitrag statt bloß auf das Thema.
- Eigene SearchView im Hub sowie site-weites Such-Overlay.
- Filter nach Scope, Arbeitsgruppe, Autor und Zeitraum.
- Boolean-Fulltext-Builder mit Phrase-Support, Titelmodus und LIKE-Fallback für kurze Suchwörter.
- Hybride Suche über Forum und WordPress-Inhalte mit Reciprocal Rank Fusion.
- Optionale semantische Suche mit externem Embedding-Anbieter und lokalem Vektorindex.
- Search-Adminseite für Konfiguration und Reindex.

Noch offen:

- Besseres Rate-Limiting des öffentlichen Suchendpunkts.
- Echte Stemming- oder Wortformbehandlung für Deutsch.
- Weitere Qualitätsverbesserungen bei sehr kurzen Suchbegriffen und Phrasen jenseits des bestehenden Fulltext-Builders.

## Querschnittsthemen

Bereits stark berücksichtigt:

- Audit-Logging über mehrere Use Cases.
- Nonces, Policies, Objektberechtigung und deny-by-default.
- No-JS-Fallbacks für Kernpfade.
- Asgaros-nahe Hub-Integration statt separater Frontend-Inseln.

Weiter aktiv zu pflegen:

- Dokumentationsabgleich bei neuen Asgaros-Integrationen.
- Accessibility-Prüfung bei jeder neuen View oder jedem Dialog.
- Vollständige Privacy-Abdeckung für neue personenbezogene Daten.
