# Changelog

## Unreleased

- Issue #9: Das bestehende Asgaros-Control für „Forum abonnieren“ bzw.
  „Thema abonnieren“ wird einmalig oben rechts in der jeweiligen Ansicht als
  sichtbare Schaltfläche ausgegeben; die zentrale Abonnementverwaltung bleibt
  unverändert.
- Plugin-Version auf 0.4.15 angehoben.

- Arbeitsgruppen-Farbwahl in `working-group-settings` als sichtbare,
  zugängliche Auswahlkacheln mit Farbfeld, Bezeichnung, Hexwert und klarer
  Markierung der aktuell gewählten Farbe gestaltet.
- Plugin-Version auf 0.4.14 angehoben.

- Appearance-Settings um eine zentrale Hex-Farbbedienung mit Farbwähler und
  Copy-and-paste-Feld für alle Farbrollen einschließlich Lila-Akzent erweitert.
- Hex-Kurzformen und Werte ohne führendes `#` werden normalisiert; ungültige
  Eingaben fallen serverseitig sicher auf den jeweiligen Standardwert zurück.
- Plugin-Version auf 0.4.13 angehoben.

- Arbeitsgruppen-Subnavigation an die zurückhaltende Tab-Optik angepasst:
  transparenter Hintergrund, nur eine blaue Linie am unteren Rand und weißer
  Hintergrund ausschließlich für den aktiven Tab.
- Plugin-Version auf 0.4.12 angehoben.

- Button-Textfarben, Hoverfarben und die aktive Arbeitsgruppen-Subnavigation
  in der Darstellungsseite ergänzt; bestehende alte Buttonwerte werden beim
  Einlesen auf die neue Palette korrigiert.
- Plugin-Version auf 0.4.10 angehoben.

- Hover und Fokus von Primär- und Sekundärbuttons verwenden verbindlich
  EfabiNet-Gelb (`#f5ae35`); Sekundärbuttons verwenden `#364149`.
- Plugin-Version auf 0.4.9 angehoben.

- Zentrale EfabiNet-Farbvariablen für Blau, Gelb, Lila, Text, sekundäre und
  helle Hintergründe ergänzt. Das Preset `Asgaros-Nah` verwendet diese Palette.
- Plugin-Version auf 0.4.8 angehoben.

- Issue #21: Der Name der verwalteten Arbeitsgruppe steht jetzt als dynamische
  Kontextüberschrift zwischen Breadcrumbs und Subnavigation. Die H2 der
  Verwaltungsansichten wiederholen den Gruppennamen nicht mehr.
- Plugin-Version auf 0.4.7 angehoben.

- Issue #21: Die Subnavigation ist jetzt eine transparente Tab-Leiste mit
  klar markiertem aktivem Tab.
- Subcontent-Karten und Einladungslink-Formular an das äußere Rundungs- und
  Abstandsdesign angepasst.

- Issue #21: Untermenü und Detailbearbeitung übersichtlicher gestaltet,
  Verzeichnis-Sichtbarkeit entfernt, Arbeitsgruppen für angemeldete Personen
  auffindbar gemacht und Rollenbegriff „Besitzer:in“ eingeführt.
- Plugin-Version auf 0.4.6 angehoben.

- Die Standardfarbe der AFSpaces-Arbeitsgruppenüberschriften, einschließlich
  .afspaces-dashboard h2, ist jetzt EfabiNet-Blau (#2d5d7f).
- Plugin-Version auf 0.4.5 angehoben.

- Die Corporate-Design-Farben werden im Arbeitsgruppen-Select zusätzlich als
  farbige Option-Hintergründe mit kontrastierender Schrift dargestellt.
- Plugin-Version auf 0.4.4 angehoben.

- Freie Arbeitsgruppenfarben entfernt. Die Bearbeitungsansicht bietet nur
  noch die Corporate-Design-Palette; Server und Domain normalisieren fremde
  Farbwerte auf #2d5d7f.
- Plugin-Version auf 0.4.3 angehoben.

- Kategorie-Farbregeln der Arbeitsgruppen mit einer höheren, auf
  #af-wrapper und die Kategorie-ID begrenzten Spezifität ausgegeben, damit
  individuelle Farben nicht von Asgaros .title-element-Regeln überschrieben
  werden.
- Plugin-Version auf `0.4.2` angehoben.

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
