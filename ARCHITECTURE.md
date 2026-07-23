# ARCHITECTURE.md

## Schichten

1. **Domain:** Space, Manager, Invitation, Invite Link, Policies und Statusübergänge.
2. **Application:** Use Cases wie Mitglied hinzufügen, Einladung annehmen oder Raum erstellen.
3. **Adapters:** Asgaros, WordPress-Benutzer, E-Mail, Datenbank und Audit.
4. **Interface:** REST, serverseitige Formulare, Shortcodes, Templates und optionale JavaScript-Komponenten.

## Abhängigkeitsregel

Domain und Application dürfen nicht direkt von Asgaros-internen Klassen abhängen. Nur der Asgaros-Adapter kennt diese Implementierungsdetails.

## Datenhaltung

Asgaros bleibt maßgeblich für Forum, Benutzergruppe und Gruppenzuordnung. Eigene Tabellen speichern nur Space-Metadaten, Manager, Einladungen, Invite Links und Audit-Ereignisse.

## Transaktionsähnliche Operationen

WordPress und Asgaros bieten möglicherweise keine durchgehende Transaktion über alle Operationen. Mehrschrittige Use Cases benötigen daher:

- definierte Reihenfolge,
- idempotente Schritte,
- Kompensationsaktionen,
- Erkennung unvollständiger Zustände,
- Reparaturwerkzeug für Administratoren.

## Frontend

Serverseitig gerenderte Formulare sind die Basis. REST und JavaScript verbessern die Interaktion. Kein Kernprozess darf ausschließlich über JavaScript erreichbar sein.

## Forum-Integration und Hub-Seite

Die gesamte Frontend-Verwaltung ist unter einer einzigen WordPress-Hub-Seite (Slug `afspaces`, Shortcode `[afspaces]`) gebündelt. Ein Router (`SpacesHubController`) wählt anhand des Query-Parameters `afspaces_view` die passende Unteransicht (Dashboard, Mitglieder, Einladungen, Beitrittsanfragen, meine Einladungen, später Raumgründung) und rendert Brotkrümel plus zweistufige Navigation.

- `SpacesUrls` ist der zentrale URL- und View-Namensraum; alle internen Links und Redirects laufen darüber.
- Die globale Hub-Navigation enthält links eine dauerhafte Schaltfläche `Forum` (Link zur Forum-Startseite) sowie hubweite Ansichten (`Meine Räume`, `Meine Einladungen`, `Räume entdecken`, optional `Raum gründen`).
- Raumbezogene Verwaltungstabs (`Mitglieder`, `Einladungen`, `Beitrittsanfragen`) werden nur im Verwaltungskontext und direkt unter einem Raumtitel angezeigt.
- `ForumNavigation` hängt über dokumentierte Asgaros-Hooks (`asgarosforum_filter_header_menu`, `asgarosforum_overview_custom_content_top`) einen Menüpunkt „Räume" und ein Einstiegs-Panel in das Forum ein.
- Die bestehenden Views (`MembersView`, `InvitationsView`, `MyInvitationsView`) und `FrontendController::render_dashboard` bleiben unverändert in ihrer Fachlogik und werden vom Router eingebettet.
- Das Erscheinungsbild wird standardmäßig Asgaros-nah ausgeliefert und kann über die Admin-Seite `Einstellungen -> AFSpaces Look & Feel` konfiguriert werden. Die Werte werden als Option gespeichert und per Inline-CSS auf den Frontend-Style angewendet.
- Erweiterungspunkte (`afspaces_hub_navigation_tabs`, `afspaces_render_space_creation`, Option/Filter `afspaces_enable_space_creation`) bereiten die MVP-4-Raumgründung vor, ohne sie sichtbar zu aktivieren.

