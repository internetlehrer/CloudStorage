# Informationen zur Installation

1. Führen Sie das Upgrade von ILIAS 8 auf ILIAS 9 aus oder Installieren Sie ILIAS 9.

2. Kopieren Sie den Inhalt dieses Ordners oder Klonen Sie das Git Repository in folgendes Verzeichnis auf Ihrem Webserver: `<ILIAS_directory>/Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage`
  - Wechseln Sie auf dem Filesystem Ihres Webservers ins ILIAS-Verzeichnis, dann:
    ```
    mkdir -p Customizing/global/plugins/Services/Repository/RepositoryObject
    cd Customizing/global/plugins/Services/Repository/RepositoryObject
    git clone -b release_9 https://github.com/internetlehrer/CloudStorage.git
    ```
  - Wechseln Sie ins Plugin-Verzeichnis und installieren Sie die composer Bibliotheken:
    ```
    cd CloudStorage
    composer install --no-dev
    ```
3. Führen Sie im ILIAS Webroot-Verzeichnis composer dump-autoload und das setup aus:
    ```
    composer du
    php setup/setup.php update
    ```

4. Melden Sie sich auf Ihrer ILIAS-Installation als Administrator an und wählen Sie im Menü `Administration / Plugins`. In der Plugin-Übersicht finden Sie den Eintrag CloudStorage. Führen Sie über dessen Dropdown-Menü folgende Aktionen aus:
  - Installieren
  - Aktivieren
  - Konfigurieren: Geben Sie in der Konfiguration die Daten Ihres Cloud-Service Anbieters ein.
