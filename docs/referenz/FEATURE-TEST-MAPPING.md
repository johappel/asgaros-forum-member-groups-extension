# Feature → Klassen → Tests

Schnellübersicht: Welche  Testdateien gehören zu welchem Feature?

## 1. Einladungen (Invitations)

| Feature | Quellklasse | Test |
| --- | --- | --- |
| Zustandsübergänge (pending → accepted/declined/revoked/expired) | `Domain/Invitation.php` | `tests/InvitationDomainTest.php` |
| Annahme/Ablehnung über Token (Full-Flow) | `Application/InvitationService.php` | `tests/Integration/InvitationFlowTest.php` |

## 2. Einladungslinks (Invite Links)

| Feature | Quellklasse | Test |
| --- | --- | --- |
| Link-Zustandsmaschine (active, revoked, expired, exhausted) | `Domain/InviteLink.php` | `tests/InviteLinkDomainTest.php` |
| Token-Generierung, Hashing, Verifizierung | `Application/InviteLinkToken.php` | `tests/InviteLinkDomainTest.php` |
| Erstellung, Nutzung, Rate-Limiting (Full-Flow) | `Application/InviteLinkService.php` | `tests/Integration/InviteLinkFlowTest.php` |

## 3. Beitrittsanfragen (Join Requests)

| Feature | Quellklasse | Test |
| --- | --- | --- |
| Zustandsmaschine (pending, approved, rejected) | `Domain/JoinRequest.php` | `tests/JoinRequestDomainTest.php` |
| Erstellung, Genehmigung, Ablehnung (Full-Flow) | `Application/JoinRequestService.php` | `tests/Integration/JoinRequestFlowTest.php` |

## 4. Space-Lebenszyklus & Berechtigungen

| Feature | Quellklasse | Test |
| --- | --- | --- |
| Gültige Status und erlaubte Übergänge | `Domain/SpaceLifecycle.php` | `tests/SpaceLifecycleTest.php` |
| Zentrale Berechtigungslogik (manage, remove, invite, moderate) | `Domain/SpacePolicy.php` | `tests/SpacePolicyTest.php` |
| Erstellungsrichtlinien (Quotas, Rate-Limits, Validierung) | `Domain/SpaceCreationPolicy.php` | `tests/SpaceCreationPolicyTest.php` |
| Erstellungs-Einstellungen und globale Aktivierung (Value Object/Policy) | `Core/SpaceCreationSettings.php`, `Domain/SpaceCreationPolicy.php` | `tests/SpaceCreationPolicyTest.php`, `tests/SpaceCreationServiceTest.php` |
| Sichtbarkeit und Zähler offener Freigaben | `Application/SpaceLifecycleService.php`, `Adapters/Database/SpaceRepository.php` | `tests/SpaceApprovalNavigationTest.php`, `tests/Integration/SpaceApprovalNavigationTest.php` |

## 5. Space-Dienste (Application)

Issue #15 (Topic pinnen/lösen) ist durch `SpaceModerationServiceTest` abgedeckt.

| Feature | Quellklasse | Test |
| --- | --- | --- |
| Space-Erstellung mit Transaktions-Rollback | `Application/SpaceCreationService.php` | `tests/SpaceCreationServiceTest.php` |
| Moderation (Schließen, Löschen, Verschieben) | `Application/SpaceModerationService.php` | `tests/SpaceModerationServiceTest.php` |
| Bestehende Asgaros-Foren als Spaces registrieren | `Application/SpaceRegistrationService.php` | `tests/SpaceRegistrationServiceTest.php` |
| Mitglieder hinzufügen/entfernen, Manager zuweisen | `Application/MemberService.php` | `tests/Integration/MemberManagementTest.php` |
| Arbeitsgruppen-Metadaten speichern/validieren | `Application/WorkingGroupService.php` | `tests/Integration/WorkingGroupMetaFlowTest.php` |

## 6. Suchschicht (Search)

| Feature | Quellklasse | Test |
| --- | --- | --- |
| MySQL FULLTEXT Boolean-Query-Builder | `Search/FulltextQuery.php` | `tests/FulltextQueryTest.php` |
| Reciprocal Rank Fusion (RRF) | `Search/ResultFusion.php` | `tests/ResultFusionTest.php` |
| Text-Snippet-Extraktion mit Hervorhebung | `Search/SnippetBuilder.php` | `tests/SnippetBuilderTest.php` |
| Vektor-Mathematik (Cosine, Normalize) | `Search/VectorMath.php` | `tests/VectorMathTest.php` |
| Forum-Suche (Adapter-Mapping, Pagination) | `Application/ForumSearchService.php` | `tests/ForumSearchServiceTest.php` |

## 7. Adapter-Schicht (Adapter)

| Feature | Quellklasse | Test |
| --- | --- | --- |
| Verfügbarkeitsprüfung, Exception-Normalisierung | `Adapters/Asgaros/AsgarosAdapter.php` | `tests/AdapterExceptionTest.php` |
| Native Moderationsprüfung und aktionsbezogene UI-Deduplizierung | `Adapters/Asgaros/AsgarosAdapter.php`, `Application/ModerationActionVisibility.php` | `tests/ModerationActionVisibilityTest.php` |

## 8. Schnittstellen & Bezeichnungen (Interface)

| Feature | Quellklasse | Test |
| --- | --- | --- |
| Hub-URL-Verwaltung, View-Konstanten, Legacy-Slug-Mapping | `Interface/SpacesUrls.php` | `tests/SpacesUrlsTest.php` |
| Arbeitsgruppen-Bezeichnungen (Labels, Zählungen) | `Interface/WorkingGroupTerminology.php` | `tests/WorkingGroupTerminologyTest.php` |

## 9. Production Readiness

| Feature | Quellklassen | Test |
| --- | --- | --- |
| Hub-Seite wiederverwenden und nach Löschung herstellen | `Core/Activator.php`, `Interface/SpacesUrls.php` | `tests/Integration/HubLifecycleTest.php` |
| Sichere Deinstallations-Einstellung | `Interface/InstallationSettingsPage.php`, `Core/Uninstaller.php` | `tests/InstallationSettingsTest.php`, `tests/Integration/UninstallPolicyTest.php` |
| Join-Request-Privacy | `Plugin.php`, `Adapters/Database/JoinRequestRepository.php` | `tests/Integration/JoinRequestPrivacyTest.php` |

## 10. Domänen-Modelle (Domain)

| Feature | Quellklasse | Test |
| --- | --- | --- |
| Standardwerte, Topic-ID-Normalisierung | `Domain/WorkingGroupMeta.php` | `tests/WorkingGroupMetaTest.php` |

## 11. Integration: REST-Sicherheit & End-to-End-Flows

| Feature | Quellklassen | Test |
| --- | --- | --- |
| REST-Authentifizierung, Autorisierung, Datenschutz | `Application/*Service.php` (alle) | `tests/Integration/RestSecurityTest.php` |
| Metadaten-Save → Beitrittsanfrage-Benachrichtigung | `Application/WorkingGroupService.php`, `Application/JoinRequestService.php`, `Adapters/Database/SpaceMetaRepository.php` | `tests/Integration/WorkingGroupMetaFlowTest.php` |
| Hub-Seite, verwaltetes Seiten-Meta und Wiederherstellung | `Core/Activator.php`, `Interface/SpacesUrls.php` | `tests/Integration/HubLifecycleTest.php` |
| Deinstallationsschutz und Opt-in-Cleanup | `Core/Uninstaller.php`, `Interface/InstallationSettingsPage.php` | `tests/Integration/UninstallPolicyTest.php`, `tests/InstallationSettingsTest.php` |
| Privacy-Export und Eraser für Beitrittsanfragen | `Plugin.php`, `Adapters/Database/JoinRequestRepository.php` | `tests/Integration/JoinRequestPrivacyTest.php` |

## Testarten im Überblick

| Testtyp | Verzeichnis | Zweck |
| --- | --- | --- |
| Unit-Tests (Domain) | `tests/*DomainTest.php`, `tests/Space*Test.php` | Reine Logik ohne externe Abhängigkeiten |
| Unit-Tests (Search) | `tests/*QueryTest.php`, `tests/ResultFusionTest.php`, `tests/SnippetBuilderTest.php`, `tests/VectorMathTest.php` | Algorithmen und Helfer ohne DB |
| Unit-Tests (Interface/Core) | `tests/SpacesUrlsTest.php`, `tests/WorkingGroupTerminologyTest.php`, `tests/AdapterExceptionTest.php` | URLs, Bezeichnungen, Adapter-Fehler |
| Service-Tests | `tests/SpaceCreationServiceTest.php`, `tests/SpaceModerationServiceTest.php`, `tests/SpaceRegistrationServiceTest.php`, `tests/ForumSearchServiceTest.php` | Service-Logik mit gemockten Abhängigkeiten |
| Integrations-Tests | `tests/Integration/*FlowTest.php`, `tests/Integration/RestSecurityTest.php` | Full-Flows mit Test-DB und echten Repositories |

## Bestands-Hinweis zur Abdeckung

Die Projektstruktur und Testdateien können sich weiterentwickeln; deshalb enthält diese Referenz bewusst keine statischen Gesamtzahlen.

### Nicht direkt unit-getestet

Diese Klassen haben kein eigenes Unit-Test-File, werden aber über Integrations-Tests oder andere Tests indirekt abgedeckt:

- `Domain/Space.php` — Daten-Träger, keine Logik
- `Domain/SpaceManager.php` — Daten-Träger
- `Search/SearchHit.php` — Daten-Träger
- `Core/Requirements.php` — Abhängigkeitsprüfung, in Tests gemockt
- `Core/DomainException.php` — Basisklasse
- `Adapters/Database/*Repository.php` — über Integrations-Tests abgedeckt
- `Application/HybridSearchService.php` — Infrastruktur
- `Application/SearchIndexer.php` — Infrastruktur
- `Search/EmbeddingClient.php` — Infrastruktur
- `Search/VectorSearch.php` — Infrastruktur
- `Search/WpPostSearch.php` — Infrastruktur
- `Search/SearchSettings.php` — Konfiguration
