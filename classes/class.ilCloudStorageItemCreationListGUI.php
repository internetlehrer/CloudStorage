<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilCloudPluginItemCreationListGUI
 *
 * Class for the drawing of the list 'add new item'. Can be extended by the plugin if needed.
 *
 * @author  Timon Amstutz timon.amstutz@ilub.unibe.ch
 * @version $Id$
 */
class ilCloudStorageItemCreationListGUI
{

    public ?ilCloudStorageGroupedListGUI $gl = null;

    public $node = null;

    public function getGroupedListItemsHTML(bool $showUpload = false, bool $showCreateFolders = false): string
    {
        $gl = $this->getGroupedListItems($showUpload, $showCreateFolders);

        return $gl->getHTML();
    }

    public function getGroupedListItems($show_upload = false, $show_create_folders = false): ilCloudStorageGroupedListGUI
    {
        global $DIC;
        $lng = $DIC['lng'];

        $this->gl = new ilCloudStorageGroupedListGUI();

        $this->gl->setAsDropDown(true);

        if ($show_upload) {
            // Sn: ToDo
            //ilFileUploadGUI::initFileUpload();
            $icon_path = ilObjCloudStorageGUI::getImagePath('icon_dcl_file.svg');
            $img = ilUtil::img($icon_path);
            $a_ttip = $lng->txt('rep_robj_xcls_cld_info_add_file_to_current_directory');
            $this->gl->addEntry($img . ' '
                . $lng->txt('rep_robj_xcls_cld_add_file'), '#', '_top', 'javascript:il.CloudFileList.uploadFile();', '', 'il_cld_add_file', $a_ttip, 'bottom center', 'top center', false);
        }

        if ($show_create_folders) {
            $icon_path = ilObjCloudStorageGUI::getImagePath('icon_dcl_fold.svg');
            $img1 = ilUtil::img($icon_path);
            $a_ttip1 = $lng->txt('rep_robj_xcls_cld_info_add_folder_to_current_directory');
            $this->gl->addEntry($img1 . ' '
                . $lng->txt('rep_robj_xcls_cld_add_folder'), '#', '_top', 'javascript:il.CloudFileList.createFolder();', '', 'il_cld_add_file', $a_ttip1, 'bottom center', 'top center', false);
        }

        return $this->gl;
    }
}
