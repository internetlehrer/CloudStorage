# ILIAS CloudStorage-Plugin

## Über

Dieses [ILIAS](https://www.ilias.de) Plugin ermöglicht die Einbindung von Cloud-Ordnern verschiedener Cloud-Storage Services. 

In der aktuellen Version wird der Cloud-Storage Service [ownCloud] (https://owncloud.com/de/) unterstützt.

Weitere Services wie bspw. NextCloud sind in Planung.

Das Plugin übernimmt mit einer umfassenden Überarbeitung die Features des für ILIAS 8 abgekündigten [OwnCloud Plugins] (https://github.com/fluxapps/OwnCloud) und ermöglicht die nahtlose Migration der Cloud-Ordner aus ILIAS 7.

Darüberhinaus lassen sich nun auch verschiedene Cloud-Anbindungen des gleichen Typs konfigurieren wie bspw [sciebo] (https://hochschulcloud.nrw/) und parallel Services, die ebenfalls auf ownCloud basieren.

Für die ILIAS-Version 8 nutzen Sie bitte den branch 'release_8' (https://github.com/internetlehrer/CloudStorage/tree/release_8).

## Features

- migrieren Sie nahtlos ownCloud-Ordner und die Verbindungseinstellungen aus ILIAS 7
- erzeugen Sie Cloud-Ordner in ILIAS, verknüpfen diese mit Basisordnern Ihres Cloud-Service Accounts.
- bestimmen Sie, welche Nutzer in Kursen und Gruppen wie auf Inhalte des Cloud-Ordners zugreifen können.

## Hinweise

- Wenn Sie das Plugin *ohne Migration* in ILIAS 8 installieren möchten, befolgen Sie die Schritte der INSTALL.md
- Wenn Sie das Plugin *mit Migration* der Cloud-Objekte aus ILIAS 7 installieren möchten, befolgen Sie die Schritte der MIGRATE.md
  - Die Redirect-URL ändert sich mit diesem Plugin.
  - Die Permanentlinks zu den migrierten-Objekten, die in ILIAS 7 kopiert wurden, funktionieren nicht mehr. Es muss eine Weiterleitung eingerichtet werden, wenn benötigt (siehe MIGRATE.md)

## Inhaltsverzeichnis

[TOC]

## Voraussetzungen

Die Mindestvoraussetzungen, mit denen das Plugin getestet wurde, finden Sie hier im Überblick:

- ILIAS 8.x
- PHP 7.4, 8.0.x
- MySQL 5.7 oder MariaDB 10.2
- OAuth2 Credentials des Cloud-Anbieters

Des Weiteren benötigen Sie eine funktionsfähige Installation des gewünschten ownCloud-Services bzw. ein Kundenkonto des ownCloud-Service Anbieters.

Die Redirect-URL zur Erstellung der benötigten OAuth2 Credentials lautet: `<SERVER_URL>/Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/OwnCloud/redirect.php`

## Anpassungen der Icons für das Cloud-Ordner Objekt sowie Ordner- und Datei-Symbole

Die Icons befinden sich in : `templates/images/*` und können durch eigene Icons ersetzt werden:

- Cloud-Ordner Objekt: `icon_xcls.svg`
- Ordner-Symbol: `icon_dcl_folder.svg`
- Datei-Symbol: `icon_dcl_file.svg`

Wenn Sie einen eigenen Skin verwenden, können die Icons in den Image-Ordner des Skins gelegt werden, statt im Plugin-Ordner angepasst zu werden.