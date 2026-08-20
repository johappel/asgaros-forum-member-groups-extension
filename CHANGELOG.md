# Changelog

## Unreleased

- Beitrittsanfragen-Tabelle verbessert: Nachrichten und Aktionen bleiben ohne
  Umbruch lesbar und die Aktionsformulare werden kompakt untereinander angeordnet.
  Technische Statuswerte werden in allen betroffenen Frontend-Ansichten zentral
  auf Deutsch angezeigt; technische Werte der REST-API bleiben unverändert.
- Plugin-Version auf 0.4.33 angehoben.

- Einladungslink-Tabelle im Frontend verbessert: Nutzungs-Spalte entfernt,
  Umbrüche in den verbleibenden Inhalten verhindert und die Aktionen kompakt
  untereinander angeordnet.
- Plugin-Version auf 0.4.32 angehoben.

- AFSpaces-Frontendtabellen auf die gemeinsame `.afspaces-table`-,
  `.afspaces-table__actions`- und `.afspaces-badge`-Konvention umgestellt.
  Die Moderationsansicht zeigt jetzt ausschließlich nutzbare Aktionen,
  beschriftet die Forennavigation als „Im Forum moderieren“ und blendet die
  Forenverwaltung ohne zusätzlichen Handlungsspielraum aus.
- Legacy-escaped Topic-Titel werden an der Asgaros-Adaptergrenze normalisiert;
  HTML-Escaping bleibt beim Frontend-Output erhalten.
- Plugin-Version auf 0.4.31 angehoben.

- Lokale Arbeitsgruppenmoderation erweitert: Die Moderationsansicht verlinkt
  Themen direkt ins Asgaros-Forum und bietet kontextabhängig Schließen,
  Wiederöffnen und Löschen; die Zwischenaktion „Beiträge“ entfällt.
- Zusätzliche Foren können über die globale Option
  `afspaces_group_managers_can_create_forums` ausschließlich im eigenen Space
  angelegt und über ein dauerhaftes Space-Forum-Mapping abgesichert werden.
  Die Moderationsansicht kann zusätzliche Foren nach Bestätigung samt allen
  Themen und Beiträgen löschen; das Primärforum bleibt geschützt.
- Der Settings-Tab „Installation“ heißt jetzt „Extras“; der alte Tab-Parameter
  und die Legacy-Seite bleiben rückwärtskompatibel.
- Plugin-Version auf 0.4.30 angehoben.

- Issue #9 nach dem lokalen visuellen Abgleich korrigiert: „Forum abonnieren“
  und „Thema abonnieren“ werden innerhalb des vorhandenen Asgaros-Containers
  `.forum-menu` bei den jeweiligen Forum-/Themenaktionen ausgegeben, nicht in
  der oberen Forum-Navigation.
- Plugin-Version auf 0.4.28 angehoben.

- Abo-Aktionen im Forum-Menü auch bei frühem Asgaros-Header-Kontext korrekt
  erzeugen; dafür werden die vom Rewrite ermittelte Forum-/Themen-ID und die
  nativen Asgaros-Notifications-Methoden verwendet.
- Plugin-Version auf 0.4.27 angehoben.

- Fehler bei der Übernahme von Asgaros-Abonnementlinks behoben: Das `href`-
  Attribut wird jetzt auch erkannt, wenn Asgaros vorher Klassen am `<a>`-
  Element ausgibt.
- Plugin-Version auf 0.4.26 angehoben.

- Arbeitsgruppenprofile verlinken über `UserIdentityService` und den neuen
  Filter `afspaces_user_profile_url` auf das kanonische Mitgliederprofil.
  Ohne externen Provider wird kein irreführender Ersatzlink ausgegeben.
- Plugin-Version auf 0.4.25 angehoben.

- „Arbeitsgruppe ansehen“ aus der hervorgehobenen Schaltfläche der
  Einstellungsansicht in einen rechtsbündigen Link der Tab-Leiste verschoben.
- Plugin-Version auf 0.4.24 angehoben.

- Beitrittsoptionen in den Arbeitsgruppen-Einstellungen klarer formuliert:
  Die Auswahl benennt jetzt ausdrücklich, wer Mitglied werden und Beiträge
  verfassen kann; Einladungslinks und die separate Vergabe von
  Moderationsrechten werden verständlich erklärt.
- Plugin-Version auf 0.4.23 angehoben.

- Zugang und Mitgliedschaft in den Arbeitsgruppen-Einstellungen fachlich
  getrennt: Das Leserecht wird als „Wer darf die Beiträge dieser Arbeitsgruppe
  lesen?“ mit verständlichen Radio-Optionen und Hilfetexten dargestellt; die
  Mitgliedschaft erhält eine eigene Radio-Gruppe. Geschützte Nichtmitglieder
  können lesen, erhalten aber weder Mitgliedschaft noch Schreibrecht.
- Plugin-Version auf 0.4.22 angehoben.

- Öffentliche Forumssichtbarkeit aus der Arbeitsgruppen-Nutzeransicht entfernt
  und die Join-Auswahl per CSS sauber ausgerichtet. Die Mitgliederverwaltung
  ist aus dem Verantwortlichen-Abschnitt direkt erreichbar.
- Arbeitsgruppen-Einstellungen in der Frontend-Ansicht als zusammenhängende
  Oberfläche mit einer gemeinsamen `save_working_group_settings`-Action
  umgesetzt. Name, Beschreibung, Themen, Darstellung, Zugang und Beitritt
  werden vor dem ersten Schreibzugriff vollständig validiert; Owner-Transfer,
  Lifecycle und Löschung bleiben separate, berechtigungsgeschützte Aktionen.
- Plugin-Version auf 0.4.21 angehoben.

- Farbrollen semantisch getrennt: `--afspaces-color-blue` bleibt die feste
  Primärfarbe; konfigurierbare Überschriften- und Linkfarben werden über
  `--afspaces-heading-color` bzw. `--afspaces-link-color` gesetzt.
- Plugin-Version auf 0.4.20 angehoben.

- Moderationsaktionen im Asgaros-Topic-/Post-Menü werden aktionsbezogen
  gegen die native Asgaros-Berechtigung dedupliziert. Globale Moderatoren
  und Administratoren sehen dadurch keine semantisch identischen AFSpaces-
  Links für Löschen, Verschieben, Pinning oder Öffnen/Schließen; lokale
  Arbeitsgruppenmoderation und die serverseitigen Prüfungen bleiben erhalten.
  Das Verschieben einzelner Beiträge bleibt sichtbar, da Asgaros dafür keinen
  entsprechenden nativen Bedienweg anbietet.
- Plugin-Version auf 0.4.19 angehoben.

- Issue #15: Raumverantwortliche können Themen in ihren privaten
  Arbeitsgruppenforen sicher oben halten und wieder lösen. Die Funktion nutzt
  die bestehende Space-Policy, Topic-/Forum-Prüfung und Audit-Ereignisse,
  ohne globale Asgaros-Moderatorrechte zu vergeben.
- Plugin-Version auf 0.4.18 angehoben.

- Moderationsformulare zum Verschieben und Löschen aus dem begrenzenden
  Asgaros-Post-Wrapper in den `body` portaliert, damit sie nicht mehr durch
  `overflow` abgeschnitten werden; Ausrichtung und `details`-Bedienung bleiben
  erhalten.
- Plugin-Version auf 0.4.17 angehoben.

- Issue #9 nach visuellem Review: „Forum abonnieren“ bzw. „Thema abonnieren“
  als kontextabhängigen Nav-Eintrag direkt vor „Abonnements“ eingeordnet; die
  separate Schaltfläche oberhalb des Forum-Headers entfernt. Bei einem globalen
  Abonnement bleibt nur die vorhandene zentrale Abonnementverwaltung sichtbar.
- Plugin-Version auf 0.4.16 angehoben.

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
