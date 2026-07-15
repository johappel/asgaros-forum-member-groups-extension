# SECURITY_PRIVACY.md

## Sicherheitsprinzipien

- deny by default,
- serverseitige Objektberechtigung,
- Nonces gegen CSRF,
- kontextgerechtes Escaping,
- vorbereitete SQL-Abfragen,
- Rate-Limits für Suche, Einladungen und Tokens,
- sichere zufällige Tokens,
- Speicherung ausschließlich als Hash,
- keine sensitiven Daten in Logs.

## Bedrohungen

Zu testen sind mindestens:

- IDOR durch manipulierte Space-, Gruppen-, Einladungs- oder Benutzer-IDs,
- CSRF,
- gespeichertes und reflektiertes XSS,
- Benutzer-Aufzählung,
- E-Mail-Leakage,
- Token-Brute-Force,
- Race Conditions bei Nutzungslimits und Raumquoten,
- Privilege Escalation über Managerrollen,
- Open Redirect,
- Missbrauch des E-Mail-Versands.

## Datenschutz

- Datenminimierung,
- konfigurierbare Löschfristen,
- WordPress Privacy Exporter und Eraser,
- dokumentierte Rechtsgrundlage durch Websitebetreiber,
- keine verdeckte Profilbildung,
- transparente Audit-Protokollierung.
