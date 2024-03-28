# Installation *ohne Migration* von ownCloud-Ordnern aus ILIAS 7

1. Führen Sie das Upgrade von ILIAS 7 auf ILIAS 8 aus oder Installieren Sie ILIAS 8.

2. Kopieren Sie den Inhalt dieses Ordners oder Klonen Sie das Git Repository in folgendes Verzeichnis auf Ihrem Webserver: `<ILIAS_directory>/Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage`
  - Wechseln Sie auf dem Filesystem Ihres Webservers ins ILIAS-Verzeichnis, dann:
    ```
    mkdir -p Customizing/global/plugins/Services/Repository/RepositoryObject
    cd Customizing/global/plugins/Services/Repository/RepositoryObject
    git clone https://github.com/internetlehrer/CloudStorage
    ```
  - Wechseln Sie ins Plugin-Unterverzeichnis `OwnCloud` und installieren Sie die composer Bibliotheken:
    ```
    cd Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/OwnCloud/
    composer install
    ```
3. Führen Sie im ILIAS Webroot-Verzeichnis composer dump-autoload aus:
    ```
    composer du
    ```

4. Melden Sie sich auf Ihrer ILIAS-Installation als Administrator an und wählen Sie im Menü `Administration / Plugins`. In der Plugin-Übersicht finden Sie den Eintrag CloudStorage. Führen Sie über dessen Dropdown-Menü folgende Aktionen aus:
  - Installieren
  - Aktivieren
  - Konfigurieren: Geben Sie in der Konfiguration die Daten Ihres Cloud-Service Anbieters ein.
