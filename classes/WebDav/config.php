<?php

declare(strict_types=1);

$ti = new ilTextInputGUI($pl->txt("server_url"), "server_url");
$ti->setRequired(true);
$ti->setMaxLength(1024);
$ti->setSize(60);
$this->form->addItem($ti);

/*
$cb = new ilCheckboxInputGUI($pl->txt("account"), "account");
$cb->setRequired(false);
$cb->setInfo($pl->txt("account_info"));

$ti = new ilTextInputGUI($pl->txt("account_username"), "account_username");
$ti->setRequired(true);
$ti->setMaxLength(1024);
$ti->setSize(60);
$ti->setInfo($pl->txt("account_username_info"));
$cb->addSubItem($ti);

$pi = new ilPasswordInputGUI($pl->txt("account_password"), "account_password");
$pi->setRequired(true);
$pi->setMaxLength(1024);
$pi->setSize(60);
$pi->setInfo($pl->txt("account_password"));
$cb->addSubItem($pi);

$this->form->addItem($cb);
*/
$ti = new ilTextInputGUI($pl->txt("webdav_path"), "webdav_path");
$ti->setRequired(true);
$ti->setMaxLength(255);
$ti->setSize(60);
//$ti->setInfo(ilCloudStorageWebDav::getDefaultWebDavPath());
$this->form->addItem($ti);

$ti = new ilTextInputGUI($pl->txt("base_directory"), "base_directory");
$ti->setRequired(false);
$ti->setMaxLength(255);
$ti->setSize(60);
$ti->setInfo($pl->txt("base_directory_info"));
$this->form->addItem($ti);

/*
$cb = new ilCheckboxInputGUI($pl->txt("bd_allow_override"), "bd_allow_override");
$cb->setRequired(false);
$cb->setInfo($pl->txt("bd_allow_override_info"));
$this->form->addItem($cb);
*/

$cb = new ilCheckboxInputGUI($pl->txt("col_app"), "col_app_integration");
$cb->setRequired(false);
$cb->setInfo($pl->txt("col_app_info"));

$ti = new ilTextInputGUI($pl->txt("col_app_url"), "col_app_url");
$ti->setRequired(true);
$ti->setMaxLength(1024);
$ti->setSize(60);
$ti->setInfo($pl->txt("col_app_url_info"));
$cb->addSubItem($ti);

$ti = new ilTextInputGUI($pl->txt("col_app_formats"), "col_app_formats");
$ti->setRequired(false);
$ti->setMaxLength(1024);
$ti->setSize(60);
$ti->setInfo($pl->txt("col_app_formats_info"));
$cb->addSubItem($ti);

$si = new ilSelectInputGUI($this->plugin_object->txt('col_app_mapping_field'), 'col_app_mapping_field');
$si->setOptions(
    array(
        'login'         => $this->lng->txt('login'),
        'ext_account'   => $this->lng->txt('user_ext_account'),
        'email'         => $this->lng->txt('email'),
        'second_email'  => $this->lng->txt('second_email')
    )
);
$si->setInfo($this->plugin_object->txt('col_app_mapping_field_info'));
$si->setRequired(true);
$cb->addSubItem($si);

$this->form->addItem($cb);

// Authentication
$rg = new ilRadioGroupInputGUI($pl->txt("authentication"),'auth_method');
$rg->setRequired(true);

// OAuth Option
$ro = new ilRadioOption($pl->txt("oa2_active"),"oauth2");
$ti = new ilTextInputGUI($pl->txt("oa2_client_id"), "oa2_client_id");
$ti->setRequired(true);
$ti->setMaxLength(1024);
$ti->setSize(60);
$ro->addSubItem($ti);

$ti = new ilTextInputGUI($pl->txt("oa2_client_secret"), "oa2_client_secret");
$ti->setRequired(true);
$ti->setMaxLength(1024);
$ti->setSize(60);
$ro->addSubItem($ti);

$ti = new ilTextInputGUI($pl->txt("oa2_path"), "oa2_path");
$ti->setRequired(true);
$ti->setMaxLength(1024);
$ti->setSize(60);
$ti->setInfo(ilCloudStorageWebDav::getDefaultOAuth2Path());
$ro->addSubItem($ti);

$si = new ilSelectInputGUI($this->plugin_object->txt('oa2_token_request_auth'), 'oa2_token_request_auth');
$si->setOptions(
    array(
        ilCloudStorageConfig::HEADER => $this->plugin_object->txt(ilCloudStorageConfig::HEADER),
        ilCloudStorageConfig::POST_BODY => $this->plugin_object->txt(ilCloudStorageConfig::POST_BODY),
    )
);
$si->setInfo($this->plugin_object->txt('oa2_token_request_auth_info'));
$si->setRequired(false);
$ro->addSubItem($si);
$rg->addOption($ro);

// BasicAuth Option
$ro = new ilRadioOption($pl->txt("bauth_active"),"basic");
$ro->setInfo($pl->txt("bauth_active_info"));

/*
$ti = new ilTextInputGUI($pl->txt("account_username"), "account_username");
$ti->setRequired(true);
$ti->setMaxLength(1024);
$ti->setSize(60);
//$ti->setInfo($pl->txt("account_username_info"));
$ro->addSubItem($ti);

$pi = new ilPasswordInputGUI($pl->txt("account_password"), "account_password");
$pi->setRequired(true);
$pi->setMaxLength(1024);
$pi->setSize(60);
//$pi->setInfo($pl->txt("account_password"));
$ro->addSubItem($pi);
*/
$rg->addOption($ro);
$this->form->addItem($rg);


$sh = new ilFormSectionHeaderGUI();
$sh->setTitle($pl->txt("extended_networking"));
$sh->setInfo($pl->txt("extended_networking_info"));
$this->form->addItem($sh);

$ti = new ilTextInputGUI($pl->txt("proxy_url"), "proxy_url");
$ti->setMaxLength(1024);
$ti->setSize(60);
$ti->setInfo($pl->txt("proxy_url_info"));
$this->form->addItem($ti);

$ti = new ilTextInputGUI($pl->txt("webdav_url"), "webdav_url");
$ti->setMaxLength(1024);
$ti->setSize(60);
$ti->setInfo($pl->txt("webdav_url_info"));
$this->form->addItem($ti);
