<?php

declare(strict_types=1);

chdir('../../../../../../../');

require_once('./Services/Init/classes/class.ilInitialisation.php');
ilInitialisation::initILIAS();
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/class.ilCloudStorageOAuth2.php');
ilCloudStorageOAuth2::redirect();

?>