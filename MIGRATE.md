# Installation *mit Migration* von ownCloud-Ordnern aus ILIAS 7

## Inhaltsverzeichnis

[TOC]

## Hinweise für ILIAS 7

- !WICHTIG: Deinstallieren Sie vor der Aktualisierung auf ILIAS 8 das OwnCloud-Plugin *NICHT*, da dies zu einem Datenverlust führen könnte.

- Löschen Sie vor der ILIAS 8 Aktualisierung zunächst *NUR* den Ordner des OwnCloud-Plugins aus dem Dateisystem: `<ILIAS_directory>/Customizing/global/plugins/Modules/Cloud/CloudHook/OwnCloud`

  - Wechseln Sie auf dem Dateisystem Ihres Webservers ins ILIAS-Verzeichnis, dann:

    ```
    rm -r ./Customizing/global/plugins/Modules/Cloud/CloudHook/OwnCloud
    ```


## Aktualisierung auf ILIAS 8

1. Führen Sie die notwendigen Schritte für das Upgrade auf ILIAS 8 aus.

2. Kopieren Sie den Inhalt des CloudStorage Plugins oder Klonen Sie das Git Repository in folgendes Verzeichnis auf Ihrem Webserver: `<ILIAS_directory>/Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage`

    ```
    mkdir -p Customizing/global/plugins/Services/Repository/RepositoryObject
    cd Customizing/global/plugins/Services/Repository/RepositoryObject
    git clone https://github.com/internetlehrer/CloudStorage
    ```

3. Wechseln Sie ins Plugin-Unterverzeichnis `classes/OwnCloud` und installieren Sie die notwendigen Bibliotheken:
    ```
    cd Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/OwnCloud
    composer install
    ```

4. Führen Sie im ILIAS Webroot-Verzeichnis composer dump-autoload aus:
    ```
    composer du
    ```

5. Da sich die Authentifizierungsadresse zur `redirect.php` geändert hat, ist es notwendig, dass diese bei Ihrem Cloud-Service Anbieter angepasst wird. Für OwnCloud kann dies leider nur direkt in der Datenbank-Tabelle `oc_oauth2_clients` im Feld `redirect_uri` vorgenommen werden.

  - alt: `<SERVER_URL>/Customizing/global/plugins/Modules/Cloud/CloudHook/OwnCloud/redirect.php`
  - neu: `<SERVER_URL>/Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/OwnCloud/redirect.php`

Beim Einsatz von Sciebo wenden Sie sich bitte an folgende Adresse unter Angabe der notwendigen Informationen: 2ndlevel@sciebo.de
- URL Ihrer Sciebo-Instanz
- ID Ihrer alten OAuth2-Credentials
- Neue Redirect-URL

## Schritte in ILIAS 8

1. Melden Sie sich auf Ihrer ILIAS-Installation als Administrator an und wählen Sie im Menü `Administration / Plugins`. In der Plugin-Übersicht finden Sie den Eintrag CloudStorage. Führen Sie über dessen Dropdown-Menü folgende Aktionen aus:
  - Installieren
  - Aktivieren
  - Konfigurieren

2. Nach Aufrufen der Konfiguration werden Sie aufgefordert die bestehenden OwnCloud-Ordner zu migrieren. 

3. Nach dem Start der Migration werden Ihnen die Anzahl der zu migierenden Objekte angezeigt und Sie müssen die Migration noch einmal bestätigen. Sie haben auch die Möglichkeit die Migration abzubrechen und zu einem späteren Zeitpunkt durchzuführen.

4. Anschließend muss noch einmal das ILIAS-Setup ausgeführt werden, um die migrierten Cloud-Ordner in ILIAS sichtbar zu machen. Beispiel:

5. Wechseln Sie auf dem Dateisystem Ihres Webservers ins ILIAS-Verzeichnis, dann:

    ```
    php setup/setup.php update
    ```

Bitte beachten Sie, dass sich erst neue Cloud-Anbindungen anlegen lassen, wenn die Migration der bestehenden Anbindung und Ordner aus ILIAS 7 erfolgreich durchgeführt wurde.

## Permanentlinks

Da sich der Typ der Cloud-Objekte nach der Migration geändert hat, funktionieren die Permanentlinks (Link zu dieser Seite) für die ILIAS 7-Objekte, die vor der Migration kopiert wurden, nicht mehr und müssen entweder manuell geändert oder über eine Rewrite-Regel im Webserver umgeleitet werden:

  - alt: `<ILIAS_URL>/goto.php?target=cld_*`
  - neu: ``<ILIAS_URL>/goto.php?target=xcls_*``

Alternativ dazu lassen sich auch wie gewohnt die neuen Permanentlinks im Footer der Objekt-Seiten kopieren.