<?php

chdir('../../../../../../../../../');

require_once('./Services/Init/classes/class.ilInitialisation.php');
ilInitialisation::initILIAS();
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/OwnCloud/classes/class.ilCloudStorageOwnCloud.php');
ilCloudStorageOwnCloud::redirectToObject();

?>