# Entwicklerdokumentation

Diese Dokumentation ist der kanonische Einstieg für Entwickler und Agents, die AFSpaces anpassen, erweitern oder warten sollen. Sie fasst die verstreuten Umsetzungsstände aus den bisherigen Task-Dateien, Spezial-READMEs und Spezifikationen zusammen.

## 5-Minuten-Onboarding

1. Repository klonen und in den Projektordner wechseln.
2. Abhängigkeiten installieren: `composer install`.
3. Unit-Tests ausführen: `composer test` oder `vendor/bin/phpunit -c phpunit.xml.dist`.
4. Produktbild und Schichten in [ÜBERBLICK.md](ÜBERBLICK.md) und [ARCHITEKTUR.md](ARCHITEKTUR.md) lesen.
5. Vor einer Änderung die passende Seite unter [referenz/INDEX.md](referenz/INDEX.md) auswählen, zum Beispiel REST, Hooks, Adapter oder Datenbank.
6. Code, Test und betroffene Referenzdokumentation im selben Arbeitsschritt aktualisieren.

Integrationstests brauchen eine vorbereitete WordPress-/Asgaros-Testumgebung; lokale Pfade sind kein Bestandteil dieses kanonischen Einstiegs.

## Schnellstart

1. Lies zuerst diese Datei.
2. Lies danach [ÜBERBLICK.md](ÜBERBLICK.md) für Produktbild, Terminologie und den aktuellen Funktionsstand.
3. Nutze [ARCHITEKTUR.md](ARCHITEKTUR.md) für Einstiegspunkte im Code und für die Aufteilung nach Schichten.
4. Nutze [FEATURE-STATUS.md](FEATURE-STATUS.md), wenn du wissen musst, was pro MVP umgesetzt ist und welche Restlücken bewusst offen sind.
5. Nutze [SUCHE.md](SUCHE.md) für alle Suchfunktionen; die Suche ist ein eigener größerer Arbeitsstrang.
6. Nutze [ERWEITERN-UND-QUALITAET.md](ERWEITERN-UND-QUALITAET.md) für Entwicklungsregeln, Tests, Sicherheitsleitplanken und typische Änderungswege.
7. Nutze [referenz/INDEX.md](referenz/INDEX.md) als vollständiges Nachschlagewerk (REST, Hooks, Frontend-Actions, Adapter, Settings, Bereiche).

## Dokumentenstruktur

- [ÜBERBLICK.md](ÜBERBLICK.md)
  - Produktbild des Plugins, zentrale Begriffe, Nutzerpfade und reale Feature-Oberfläche.
- [ARCHITEKTUR.md](ARCHITEKTUR.md)
  - Schichten, zentrale Klassen, Persistenz, Request-Flows und Integrationspunkte.
- [FEATURE-STATUS.md](FEATURE-STATUS.md)
  - Konsolidierter Umsetzungsstand aus MVP 1 bis MVP 4 sowie Such-Meilensteinen, inklusive offener Punkte.
- [SUCHE.md](SUCHE.md)
  - Eigene Sucharchitektur mit Keyword-, Hybrid- und semantischer Suche, REST-API, UI und Datenschutzaspekten.
- [ERWEITERN-UND-QUALITAET.md](ERWEITERN-UND-QUALITAET.md)
  - Arbeitsregeln für Änderungen, Teststrategie, lokale Ausführung, Sicherheits- und Accessibility-Anforderungen.
- [referenz/INDEX.md](referenz/INDEX.md)
  - Vollständige Nachschlage-Referenz: REST-Endpunkte, Frontend-Actions, Hooks, Adapter-Vertrag, Settings-Pages, Domänenmodelle mit Zustandsdiagrammen, Datenbankfelder, Design-/Layout-Entscheidungen und Referenzen je Hauptteil (Mitglieder/Einladungen, Private Arbeitsgruppen, Suche).

## Weiterhin relevante Quellspezifikationen im Root

Die Dateien im Root bleiben als normative Spezifikations- und Leitplankendokumente bestehen und werden durch diese Entwicklerdokumentation nicht ersetzt:

- GOAL.md
- ARCHITECTURE.md
- SECURITY_PRIVACY.md
- ACCESSIBILITY.md
- TESTING.md
- COMPATIBILITY.md
- AGENTS.md

## Historische Dokumente

Historische Task-Tracker und ältere Spezial-READMEs liegen nach der Konsolidierung im Ordner archive/. Sie sind nützlich für Detailhistorie, aber nicht mehr der primäre Einstieg. Die konkrete Liste steht in archive/INDEX.md.

## Pflegeprinzip

- docs/ beschreibt den aktuellen Entwicklungsstand und die beabsichtigte Erweiterung.
- Root-Spezifikationen definieren Produkt-, Sicherheits- und Architekturleitplanken.
- archive/ bewahrt historische Planungs- und Fortschrittsdokumente auf.
- Bei neuen Features zuerst docs/ aktualisieren, wenn sich das Verständnis für Architektur, Status oder Änderungswege ändert.
