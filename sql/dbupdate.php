<#1>
<?php
if (!$ilDB->tableExists('rep_robj_xcls_data')) {
    $fields_data = array(
        'id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ),
        'is_online' => array(
            'type' => 'integer',
            'length' => 1
        ),
        'conn_id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => false,
            'default' => '1'
        ),
        'root_folder' => array(
            'type' => 'text',
            'length' => 256
        ),
        'root_id' => array(
            'type' => 'text',
            'length' => 256
        ),
        'auth_complete' => array(
            'type' => 'integer',
            'length' => 1
        ),
        'base_uri' => array(
            'type' => 'text',
            'length' => 256
        ),
        'username' => array(
            'type' => 'text',
            'length' => 256
        ),
        'password' => array(
            'type' => 'text',
            'length' => 256
        ),
        'owner_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        )
    );
    $ilDB->createTable("rep_robj_xcls_data", $fields_data);
    $ilDB->addPrimaryKey("rep_robj_xcls_data", array("id"));
}
?>
<#2>
<?php
if (!$ilDB->tableExists('rep_robj_xcls_conn')) {
    $fields_conn = array(
        'id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true
        ),
        'title' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => false,
        ),
        'hint' => array(
            'type' => 'text',
            'length' => 1000,
            'notnull' => false,
        ),
        'availability' => array(
            'type' => 'integer',
            'length' => 1,
            'notnull' => true,
            'default' => 1
        ),
        'account' => array(
            'type' => 'integer',
            'length' => 1,
            'notnull' => true,
            'default' => 0
        ),
        'account_username' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'account_password' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'base_directory' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'bd_allow_override' => array(
            'type' => 'integer',
            'length' => 1,
            'notnull' => true,
            'default' => 0
        ),
        'col_app_integration' => array(
            'type' => 'integer',
            'length' => 1,
            'notnull' => true,
            'default' => 0
        ),
        'col_app_formats' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'col_app_mapping_field' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'col_app_url' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'oa2_active' => array(
            'type' => 'integer',
            'length' => 1,
            'notnull' => true,
            'default' => 0
        ),
        'oa2_client_id' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'oa2_client_secret' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'oa2_path' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'oa2_token_request_auth' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'server_url' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'webdav_url' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => false,
            'default' => ''
        ),
        'proxy_url' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => false,
            'default' => ''
        ),
        'obj_ids_special' => array(
            'type' => 'text',
            'length' => 1024,
            'notnull' => true,
            'default' => ''
        ),
        'service_id' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'webdav_path' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => true,
            'default' => ''
        ),
        'access_token' => array(
            'type' => 'text',
            'notnull' => false,
            'default' => null
        ),
        'refresh_token' => array(
            'type' => 'text',
            'notnull' => false,
            'default' => null
        ),
        'auth_method' => array(
            'type' => 'text',
            'length' => 64,
            'notnull' => true,
            'default' => ''
        )
    );

    $ilDB->createTable("rep_robj_xcls_conn", $fields_conn);
    $ilDB->addPrimaryKey("rep_robj_xcls_conn", array("id"));
}
?>
<#3>
<?php
if (!$ilDB->tableExists('rep_robj_xcls_ocld_tk')) {
    $fields_token = array(
        'conn_id' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => true,
        ),
        'user_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ),
        'access_token' => array(
            'type' => 'text',
            'length' => 256,
            'notnull' => true,
            'default' => ''
        ),
        'refresh_token' => array(
            'type' => 'text',
            'length' => 256,
            'notnull' => true,
            'default' => ''
        ),
        'valid_through' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        )
    );
    $ilDB->createTable("rep_robj_xcls_ocld_tk", $fields_token);
    $ilDB->addPrimaryKey("rep_robj_xcls_ocld_tk", array("conn_id","user_id"));
}
?>
<#4>
<?php
require_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/CloudStorage/classes/class.ilObjCloudStorage.php");
ilObjCloudStorage::migrationSetup();
?>

