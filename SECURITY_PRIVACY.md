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

## Semantische Suche (Embeddings)

Die optionale semantische Suche überträgt Inhaltstexte an eine externe,
OpenRouter-kompatible Embedding-API. Dafür gelten folgende Schutzregeln:

- **Standardmäßig deaktiviert.** Ohne konfigurierten API-Schlüssel findet keine
  Übertragung statt; Foren- und WP-Suche laufen rein lokal (Keyword/Hybrid).
- **Private Inhalte nur mit Opt-in.** Inhalte privater Arbeitsgruppen
  (`forum_status = 'private'`) werden nur eingebettet, wenn in
  `Einstellungen → AFSpaces Suche` die Option „Private Inhalte einbetten“ aktiv ist.
  Ohne Opt-in verlassen private Texte den Server nicht.
- **Zugriffsschutz bei der Abfrage.** Semantische Treffer werden bei jeder Suche
  live gegen die für den Benutzer zugänglichen Kategorien gefiltert; ein Index-Eintrag
  allein legt keinen Inhalt offen.
- **API-Schlüssel.** Wird über die WordPress-Options-API gespeichert, im Formular
  maskiert, nie im Frontend ausgegeben und ausschließlich serverseitig im
  `Authorization`-Header gesendet. Er erscheint in keinem Log.
- **Nur öffentliche WP-Inhalte** (`publish`, ohne Passwortschutz, öffentliche
  Beitragstypen) werden indexiert.
- **Datensparsamkeit.** Es werden nur Titel und ein gekürzter Textauszug übertragen;
  der Index speichert Embedding, Anzeige-Snapshot und Inhalts-Hash. Bei Deinstallation
  wird die Tabelle `wp_afspaces_search_index` entfernt.
- **Kontrolle.** Betreiber sollten die Nutzung des Drittanbieters in ihrer
  Datenschutzerklärung dokumentieren; die Funktion ist jederzeit deaktivierbar.

