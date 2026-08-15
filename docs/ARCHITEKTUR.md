# Architektur

## Schichtenmodell im Code

Die Repository-Struktur folgt dem in den Spezifikationen definierten Schichtenmodell.

### Core

Verantwortlich für Plugin-Lebenszyklus, Grundkonfiguration und Aktivierung:

- Activator
- Deactivator
- Uninstaller
- Requirements
- Capabilities
- SpaceCreationSettings

### Domain

Enthält die fachlichen Modelle und Regeln:

- Space
- SpaceManager
- Invitation
- InviteLink
- JoinRequest
- WorkingGroupMeta
- SpacePolicy
- SpaceLifecycle
- SpaceCreationPolicy

Wichtige Regel: Domain und Application kennen keine Asgaros-Interna direkt.

### Application

Implementiert die Use Cases des Plugins:

- SpaceRegistrationService
- MemberService
- InvitationService
- InviteLinkService
- JoinRequestService
- WorkingGroupService
- SpaceCreationService
- SpaceLifecycleService
- SpaceModerationService
- ForumSearchService
- HybridSearchService
- SearchIndexer

`UserIdentityService` bildet die allgemeine Integrationsgrenze für sichtbare
Benutzernamen, Avatare und Benutzersuche. AFSpaces kennt keine externen
Profil-Plugins; diese liefern über dokumentierte Filter nur WordPress-User-IDs
und sichtbare Profilwerte.

Hier liegen die eigentlichen Geschäftsabläufe, die über Policies, Adapter und Repositories orchestriert werden.

### Adapters

Kapseln Fremdsysteme und Persistenz:

- AsgarosAdapter und weitere Asgaros-spezifische Integrationen unter src/Adapters/Asgaros/
- Repositories unter src/Adapters/Database/

Aktuelle Repositories:

- SpaceRepository
- SpaceMetaRepository
- InvitationRepository
- InviteLinkRepository
- JoinRequestRepository
- AuditRepository
- SearchIndexRepository

### Interface

Stellt Frontend, REST und Admin-Einstellungen bereit:

- SpacesHubController
- FrontendController
- RestController
- ForumNavigation
- ForumStyleLayer
- ForumModerationControls
- SearchModal
- SearchSettingsPage
- SpaceCreationSettingsPage
- AppearanceSettingsPage
- diverse Views für Dashboard, Mitglieder, Einladungen, Profil, Discover, Moderation und Suchoberflächen

ForumStyleLayer lädt assets/afspaces-forum-overrides.css nur auf Seiten mit
dem Asgaros-Shortcode [forum]. Der Hook läuft mit Priorität 999; zusätzlich
werden registrierte Asgaros-Style-Handles als Dependencies übernommen. So
bleibt der Override updatefest, ohne skin/style.css oder die von Asgaros
generierte skin/custom.css zu verändern.

## Datenhoheit

### Asgaros bleibt führend für

- Foren, Kategorien, Themen und Beiträge.
- Benutzergruppen und Gruppenzuordnungen.
- Den eigentlichen Lese- und Schreibzugriff auf Foren.

### AFSpaces ergänzt eigene Tabellen für

- Spaces: Zuordnung eines verwalteten Kontexts zu einem Asgaros-Forum.
- Space Manager: Owner- und Managerrollen.
- Einladungen.
- Invite Links.
- Join Requests.
- Audit-Ereignisse.
- Arbeitsgruppen-Metadaten.
- Suchindex für semantische Suche.

Bekannte Tabellennamen:

- wp_afspaces_spaces
- wp_afspaces_space_managers
- wp_afspaces_space_meta
- wp_afspaces_invitations
- wp_afspaces_invite_links
- wp_afspaces_join_requests
- wp_afspaces_audit
- wp_afspaces_search_index

## Zentrale Request-Flows

### Serverseitige Frontend-Aktion

1. Eine View rendert ein Formular mit Nonce und Space-Kontext.
2. FrontendController nimmt den POST-Request entgegen.
3. Der Controller validiert Aktion, Authentifizierung und Parameter.
4. Ein Application Service führt den Use Case aus.
5. Policy und Objektberechtigung werden im Service geprüft.
6. Adapter oder Repository schreiben nach Asgaros oder in AFSpaces-Tabellen.
7. Per PRG-Muster erfolgt Redirect zurück in die passende Hub-Ansicht.

### REST-Aktion

1. RestController registriert versionierte Endpunkte unter /wp-json/afspaces/v1.
2. permission_callback, Schema und Validierung greifen vor der Geschäftslogik.
3. Application Services erledigen die eigentliche Aktion.
4. Antworten werden normalisiert; sicherheitskritische Details bleiben verborgen.

### Forum-integrierte UI

Die Abonnement-Aktion von Asgaros wird über den Adapter einmalig als
kontextabhängiger Eintrag in die Forum-Navigation verlagert; URLs, Nonces und
Zustand bleiben vollständig bei Asgaros.

1. ForumNavigation hängt Navigation und Panel in Asgaros ein und verwendet für die Anzeige der Gründungsoption `SpaceCreationService::can_user_create()`.
2. ForumModerationControls hängt kontextabhängige Aktionen in die Forenansicht.
3. SearchModal öffnet die AFSpaces-Suche über Asgaros- und optional WordPress-Suchformulare.
4. Freigabe-Navigation verwendet `SpaceLifecycleService::count_pending_for_actor()`;
   die serverseitige Berechtigungsprüfung und eine statusbasierte `COUNT(*)`-
   Abfrage verhindern unzuständige oder leere Freigabe-Buttons und laden keine
   vollständigen Space-Listen für den Zähler.

## Hub-Architektur

Die Frontend-Verwaltung ist um eine einzige Hub-Seite zentriert. SpacesUrls ist der zentrale URL-Baustein; dort sollten neue Views, Redirects und kontextbezogene Links verankert werden.

Wichtige Regeln:

- Keine neuen Einzelseiten für Kernflows, solange der Hub-Ansatz ausreicht.
- Query-Parameter und Redirects zentral über SpacesUrls führen.
- Raumbezogene Tabs nur im Kontext einer konkreten Arbeitsgruppe anzeigen.

## Asgaros-Integration

Nur der Adapter kennt interne Asgaros-Methoden. Bereits dokumentierte und verwendete Integrationen sind unter anderem:

- Lesen zugänglicher Kategorien für Suche und Sichtbarkeit.
- Berechnung von Post-Deep-Links für Suchtreffer.
- Erstellen von Kategorien, Gruppen und Foren für selbst gegründete Arbeitsgruppen.
- Forum-Moderationsoperationen wie Schließen, Löschen, Verschieben.

Wenn neue Asgaros-Interna benötigt werden, gilt:

- erst Quellcode prüfen,
- in COMPATIBILITY.md dokumentieren,
- nur im Adapter kapseln,
- bei unbekannter Version defensiv degradieren.

## Persistenz und transaktionsähnliche Abläufe

WordPress und Asgaros bieten keine durchgehende Datenbanktransaktion über alle Artefakte. Deshalb arbeiten die Services mit geordneter Ausführung und Kompensationslogik.

Beispiel: SpaceCreationService

1. Kategorie anlegen.
2. Gruppe anlegen.
3. Forum anlegen.
4. Gruppe dem Forum zuweisen.
5. Space-Datensatz speichern.
6. Owner und Mitgliedschaft zuordnen.

Bei Teilfehlern werden bereits angelegte Artefakte rückwärts bereinigt.

## Sicherheitsrelevante Architekturentscheidungen

- Policies sind zentralisiert; Views zeigen keine eigenen Berechtigungsregeln an.
- Moderation ist space-scoped und ersetzt keine globalen Asgaros-Moderatorrechte.
- Pending-Arbeitsgruppen werden vor Freigabe restriktiv behandelt.
- Tokens für Invite Links werden niemals im Klartext gespeichert.
- Die Suche filtert Treffer anhand der tatsächlich zugänglichen Kategorien und Foren.

## Reale Einstiegspunkte für Änderungen

Wenn du neue Funktionen einbaust, beginne meistens hier:

- Neue Fachregel: Domain oder Application.
- Neue Schreibaktion: Application Service plus FrontendController oder RestController.
- Neue UI-Ansicht: Interface-View plus SpacesHubController und SpacesUrls.
- Neue Speicherung: neues Repository oder Erweiterung eines bestehenden Repositories.
- Neue Asgaros-Interaktion: Adapter-Interface, Adapter-Implementierung, Tests und COMPATIBILITY.md.
- Neue Sucheigenschaft: meist Search/, Application/HybridSearchService und Interface/SearchView oder SearchModal.

## Vollständige Nachschlage-Referenz

Konkrete Signaturen, Routen, Hooks und Optionsschlüssel stehen in der Referenz unter [referenz/INDEX.md](referenz/INDEX.md):

- [referenz/REST-API.md](referenz/REST-API.md)
- [referenz/FRONTEND-ACTIONS.md](referenz/FRONTEND-ACTIONS.md)
- [referenz/HOOKS.md](referenz/HOOKS.md)
- [referenz/ADAPTER.md](referenz/ADAPTER.md)
- [referenz/SETTINGS-PAGES.md](referenz/SETTINGS-PAGES.md)
- [referenz/DOMAINMODELLE.md](referenz/DOMAINMODELLE.md)
- [referenz/DATENBANK.md](referenz/DATENBANK.md)
- [referenz/DESIGN-UND-LAYOUT.md](referenz/DESIGN-UND-LAYOUT.md)
