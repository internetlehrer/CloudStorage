# ILIAS CloudStorage-Plugin

## Über

Dieses [ILIAS](https://www.ilias.de) Plugin ermöglicht die Einbindung von Cloud-Ordnern verschiedener Cloud-Storage Services. 

In der aktuellen Version werden die Cloud-Storage Services [ownCloud] (https://owncloud.com/de/) und [WebDAV] unterstützt.

Weitere Services wie bspw. NextCloud sind in Planung.

Es lassen sich nun auch verschiedene Cloud-Anbindungen des gleichen Typs konfigurieren wie bspw. [sciebo] (https://hochschulcloud.nrw/) und parallel Services, die ebenfalls auf ownCloud basieren oder einen Zugriff via WebDAV ermöglichen.

Für die ILIAS-Version 9 nutzen Sie bitte den branch 'release_9' (https://github.com/internetlehrer/CloudStorage/tree/release_9).

## Features

- Binden Sie Cloud-Services an ILIAS an, die auf ownCloud oder WebDAV basieren.
- Erzeugen Sie Cloud-Ordner in ILIAS, verknüpfen diese mit Basisordnern Ihres Cloud-Service Accounts.
- Bestimmen Sie, welche Nutzenden in Kursen und Gruppen wie auf Inhalte des Cloud-Ordners zugreifen können.

## Hinweise

- Cloud-Ordner aus ILIAS 7 wurden mit ILIAS 8 zum CloudStorage Plugin migriert. Für das Update von ILIAS 8 zu ILIAS 9 ist keine Migration vorgesehen. Wenn Sie von ILIAS 7 zu ILIAS 9 wechseln möchten, ist also die Migration in ILIAS 8 als Zwischenschritt notwendig.
- Informationen zur Installation des Plugins finden Sie in der INSTALL.md

## Voraussetzungen

Die Mindestvoraussetzungen, mit denen das Plugin getestet wurde, finden Sie hier im Überblick:

- ILIAS 9.x
- PHP 8.2.x
- MySQL 5.7 oder MariaDB 10.8
- OAuth2 Credentials des Cloud-Anbieters

Des Weiteren benötigen Sie eine funktionsfähige Installation des gewünschten ownCloud-Services bzw. ein Kundenkonto des ownCloud-Service Anbieters.

Die Redirect-URL zur Erstellung der benötigten OAuth2 Credentials lautet: `<SERVER_URL>/Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/OwnCloud/redirect.php`

## Anpassungen der Icons für das Cloud-Ordner Objekt sowie Ordner- und Datei-Symbole

Die Icons befinden sich in : `templates/images/*` und können durch eigene Icons ersetzt werden:

- Cloud-Ordner Objekt: `icon_xcls.svg`
- Ordner-Symbol: `icon_dcl_folder.svg`
- Datei-Symbol: `icon_dcl_file.svg`

Wenn Sie einen eigenen Skin verwenden, können die Icons in den Image-Ordner des Skins gelegt werden, statt im Plugin-Ordner angepasst zu werden.

## Dokumentation von Bugs und Feature-Vorschlägen

Wenn Sie **Probleme bei der Installation oder der Verwendung** dieses Plugins haben, melden Sie diese bitte im Mantis der ILIAS-Community unter https://mantis.ilias.de/. Wählen Sie oben rechts "ILIAS Plugins" aus und erstellen Sie einen Report in der Kategorie "CloudStorage".

Wenn Sie **Vorschläge für Verbesserungen oder für neue Funktionen** haben, dokumentieren Sie diese gerne hier im github-Repository als issue.