# FileUploadSettings

Verwaltet die erlaubten Dateiendungen beim Datei-Upload in WordPress und Asgaros Forum.

## Komponenten

| Zweck | Datei |
| --- | --- |
| Kern-Implementierung | `src/Core/FileUploadSettings.php` |
| Verdrahtung | `src/Plugin.php`, Methode `init()` |

## Verhalten

Die Klasse erweitert zwei Upload-Systeme:

1. **WordPress Media Library**: Registriert einen Filter auf `upload_mimes`
2. **Asgaros Forum Anhänge**: Modifiziert die globale Option `$asgarosforum->options['allowed_filetypes']` direkt

Da AFSpaces Dokumente als **Asgaros-Uploads** speichert (nicht als separate WordPress-Dateien), müssen beide Systeme erweitert werden.

### Timing (wichtig)

Asgaros lädt seine Optionen im Konstruktor (beim Plugin-Laden). `AsgarosForumUploads::initialize()` liest `allowed_filetypes` auf dem `init`-Hook bei **Priorität 10** in eine eigene, gecachte Property `$upload_allowed_filetypes` — genau diese prüft `check_uploads_extension()` beim Upload. Deshalb registriert `extend_asgaros_filetypes()` bei **`init`-Priorität 5**, um die Option zu erweitern, *bevor* Asgaros sie cached.

**Standardmäßig erlaubte Dateien:**

- `.md` → `text/markdown` (Markdown-Dokumente)
- `.doc` → `application/msword` (MS Word Legacy)
- `.docx` → `application/vnd.openxmlformats-officedocument.wordprocessingml.document` (MS Word)
- `.xls` → `application/vnd.ms-excel` (MS Excel Legacy)
- `.xlsx` → `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` (MS Excel)
- `.ppt` → `application/vnd.ms-powerpoint` (MS PowerPoint Legacy)
- `.pptx` → `application/vnd.openxmlformats-officedocument.presentationml.presentation` (MS PowerPoint)
- `.odt` → `application/vnd.oasis.opendocument.text` (LibreOffice Writer)
- `.ods` → `application/vnd.oasis.opendocument.spreadsheet` (LibreOffice Calc)
- `.odp` → `application/vnd.oasis.opendocument.presentation` (LibreOffice Impress)

## Erweiterung

Weitere Dateitypen können über den Filter `afspaces_additional_upload_mimes` hinzugefügt werden:

```php
add_filter( 'afspaces_additional_upload_mimes', function ( $mimes ) {
    $mimes['csv'] = 'text/csv';
    $mimes['json'] = 'application/json';
    $mimes['rtf'] = 'application/rtf';
    return $mimes;
} );
```

## Signaturen

| Methode | Parameter | Rückgabe | Zweck |
| --- | --- | --- | --- |
| `init()` | keine | `void` | registriert beide Filter/Actions |
| `allow_additional_mimes(array $mimes)` | `array<string, string>` WordPress-MIME-Typen | `array<string, string>` | kombiniert WordPress-Standard mit AFSpaces-Erweiterungen |
| `extend_asgaros_filetypes()` | keine | `void` | erweitert Asgaros `allowed_filetypes` auf `init` (Priorität 5, vor Asgaros' init/10-Cache) |
| `get_allowed_mimes()` | keine | `array<string, string>` | liefert AFSpaces-Typen + gefilterte Erweiterungen |
| `get_allowed_extensions()` | keine (private) | `array<int, string>` | gibt nur die Endungen (Schlüssel) zurück |

## Filter-Hook

| Filter | Parameter | Rückgabewert | Quelle |
| --- | --- | --- | --- |
| `afspaces_additional_upload_mimes` | `array<string, string> $mimes` | `array<string, string>`; Default: 10 MS Office + LibreOffice Dateitypen | `FileUploadSettings::get_allowed_mimes()` |
