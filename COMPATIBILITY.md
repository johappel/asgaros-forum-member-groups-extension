# COMPATIBILITY.md

## Zweck

Dieses Dokument wird während der Entwicklung mit konkret geprüften Versionen und internen Asgaros-Schnittstellen aktualisiert.

## Ausgangslage

Asgaros dokumentiert zahlreiche Actions und Filters für Frontend-Erweiterungen sowie Ereignisse beim Hinzufügen oder Entfernen von Benutzern aus Benutzergruppen. Eine vollständige öffentliche CRUD-API für Foren und Benutzergruppen ist daraus nicht ersichtlich.

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
