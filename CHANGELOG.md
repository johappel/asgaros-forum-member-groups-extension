# Changelog

## Unreleased

- Eigenen, updatefesten Asgaros-Forum-Override-Layer ergänzt: AFSpaces lädt
  `assets/afspaces-forum-overrides.css` ausschließlich auf [forum]-Seiten
  nach den registrierten Asgaros-Styles. Die initialen Regeln verbessern die
  Hoverfarbe der Standardbuttons und die Lesbarkeit der Post-Metadaten.
- Plugin-Version auf `0.4.1` angehoben.

- Repository-Lizenz auf GNU Affero General Public License v3 oder später (AGPL-3.0-or-later) umgestellt.

- User-Identity-Abstraktion für Anzeigenamen, Avatare und Benutzersuche ergänzt; externe Profilintegrationen bleiben über Filter und WordPress-User-IDs entkoppelt.
- Lokale Efabi-Bridge um den Asgaros-Namensfilter, den AFSpaces-Avatarfilter und die paginierte Efabi-Profil-Suche erweitert.
- Plugin-Version auf `0.4.0` angehoben.
- Einladungslinks verwenden in `afspaces_view=invitations` für entsprechend berechtigte Ersteller standardmäßig unbegrenzte Nutzungen (`0`); für andere Verantwortliche bleibt der sichere Default `1`.
- Der Hinweis zur maximalen Nutzungszahl erklärt jetzt, dass das Limit alle Nutzungen eines Links umfasst und die Weitergabe begrenzen kann.

- Gründungsbutton und Gründungsberechtigung an die zentrale Option `afspaces_creation_options[enabled]` gebunden; die Deaktivierung blendet die Funktion auch für Administratoren aus und ignoriert den veralteten Legacy-Optionswert.
- Plugin-Version auf `0.3.2` angehoben.

- Asgaros-Parent-Slug auf `asgarosforum-structure` korrigiert, damit AFSpaces im Menü „Forum → Struktur“ erscheint.
- Zentrale Arbeitsgruppen-Settingsseite unter dem Asgaros-Forum-Menü mit nativen Tabs für Darstellung, Raumgründung, Suche und Installation ergänzt.
- Direkte frühere Settings-Slugs leiten rückwärtskompatibel auf den jeweils passenden Tab weiter.
- Plugin-Version auf `0.3.1` angehoben.

- Developer-Reference-Hardening für Issue #6: REST-Routen, Capabilities, Adapter-Shapes, Hooks, physisches DB-Schema, Test-Mapping und generischer Entwickler-Quickstart gegen den aktuellen Code synchronisiert.
- Production-Readiness-Pass für Issue #4: automatische und eigentumssichere Hub-Seite, Wiederverwendung nach redaktionellen Änderungen und sichere Wiederherstellung.
- Deinstallation bewahrt AFSpaces-Daten standardmäßig; vollständiges Cleanup ist ein explizites Admin-Opt-in und löscht keine Asgaros-Daten.
- Produktionsvoraussetzungen auf WordPress 7.0+, PHP 8.1+ und Asgaros Forum 3.4.0+ angehoben.
- Einmalige Aktivierungsrückmeldung mit Hub- und Installationseinstellungen ergänzt.
- Join-Request-Privacy-Exporter und -Eraser ergänzt; Sicherheits- und Nachweisdaten bleiben erhalten.
