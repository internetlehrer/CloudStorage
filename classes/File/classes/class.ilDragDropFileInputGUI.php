<?php

declare(strict_types=1);

/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* This class represents a file input property where multiple files can be dopped in a property form.
*
* @author Stefan Born <stefan.born@phzh.ch>
* @version $Id$
* @ingroup	ServicesForm
*/
class ilDragDropFileInputGUI extends ilFileInputGUI
{
    /**
     * @var ilLanguage
     */
    //protected ilLanguage $lng;

    private $uniqueId = 0;
    private $archive_suffixes = array();
    private $submit_button_name = null;
    private $cancel_button_name = null;

    //private ?ilTemplate $tpl = null;
    
    private static $uniqueInc = 1;
    
    private static function getNextUniqueId()
    {
        return self::$uniqueInc++;
    }
    
    /**
     * Constructor
     *
     * @param	string	$a_title	Title
     * @param	string	$a_postvar	Post Variable
     */
    public function __construct($a_title = "", $a_postvar = "")
    {
        global $DIC;

        //$this->lng = $DIC->language();
        parent::__construct($a_title, $a_postvar);
        $this->uniqueId = self::getNextUniqueId();
    }
    
    /**
    * Set accepted archive suffixes.
    *
    * @param	array	$a_suffixes	Accepted archive suffixes.
    */
    public function setArchiveSuffixes($a_suffixes)
    {
        $this->archive_suffixes = $a_suffixes;
    }

    /**
    * Get accepted archive suffixes.
    *
    * @return	array	Accepted archive suffixes.
    */
    public function getArchiveSuffixes()
    {
        return $this->archive_suffixes;
    }
    
    public function setCommandButtonNames($a_submit_name, $a_cancel_name)
    {
        $this->submit_button_name = $a_submit_name;
        $this->cancel_button_name = $a_cancel_name;
    }
    
    /**
     * Render html
     */
    public function render($a_mode = ""): string
    {
        global $DIC;
        $lng = $this->lng;

        $tpl = new ilTemplate(ilObjCloudStorage::PLUGIN_PATH . "/classes/File/templates/default/tpl.prop_dndfiles.html", false, false);
        // general variables
        $tpl->setVariable("UPLOAD_ID", $this->uniqueId);
        
        // input
        $tpl->setVariable("FILE_SELECT_ICON", ilObject::_getIcon(0, "", "fold"));
        $tpl->setVariable("TXT_SHOW_ALL_DETAILS", $lng->txt('show_all_details'));
        $tpl->setVariable("TXT_HIDE_ALL_DETAILS", $lng->txt('hide_all_details'));
        $tpl->setVariable("TXT_SELECTED_FILES", $lng->txt('selected_files'));
        $tpl->setVariable("TXT_DRAG_FILES_HERE", $lng->txt('drag_files_here'));
        $tpl->setVariable("TXT_NUM_OF_SELECTED_FILES", $lng->txt('num_of_selected_files'));
        $tpl->setVariable("TXT_SELECT_FILES_FROM_COMPUTER", $lng->txt('select_files_from_computer'));
        $tpl->setVariable("TXT_OR", $lng->txt('logic_or'));
        $tpl->setVariable("INPUT_ACCEPT_SUFFIXES", $this->getInputAcceptSuffixes($this->getSuffixes()));

        // info
        $tpl->setCurrentBlock("max_size");
        $tpl->setVariable("TXT_MAX_SIZE", $lng->txt("file_notice") . " " . $this->getMaxFileSizeString());
        $tpl->parseCurrentBlock();
        
        // Sn: ToDo

        // if ($quota_legend) {
        //     $tpl->setVariable("TXT_MAX_SIZE", $quota_legend);
        //     $tpl->parseCurrentBlock();
        // }
        
        $this->outputSuffixes($tpl);
        // create file upload object
        $upload = new ilFileUploadGUI("ilFileUploadDropZone_" . $this->uniqueId, $this->uniqueId, false);
        $upload->enableFormSubmit("ilFileUploadInput_" . $this->uniqueId, $this->submit_button_name, $this->cancel_button_name);
        $upload->setDropAreaId("ilFileUploadDropArea_" . $this->uniqueId);
        $upload->setFileListId("ilFileUploadList_" . $this->uniqueId);
        $upload->setFileSelectButtonId("ilFileUploadFileSelect_" . $this->uniqueId);
        $tpl->setVariable("FILE_UPLOAD", $upload->getHTML());
        return $tpl->get();
    }
    
    /**
     * Check input, strip slashes etc. set alert, if input is not ok.
     *
     * @return	boolean		Input ok, true/false
     */
    public function checkInput(): bool
    {
        $lng = $this->lng;
        
        // if no information is received, something went wrong
        // this is e.g. the case, if the post_max_size has been exceeded
        if (!is_array($_FILES[$this->getPostVar()])) {
            $this->setAlert($lng->txt("form_msg_file_size_exceeds"));
            return false;
        }
        
        // empty file, could be a folder
        if ($_FILES[$this->getPostVar()]["size"] < 1) {
            $this->setAlert($lng->txt("error_upload_was_zero_bytes"));
            return false;
        }

        // call base
        $inputValid = parent::checkInput();
        
        // set additionally sent input on post array
        if ($inputValid) {
            $_POST[$this->getPostVar()]["extract"] = isset($_POST["extract"]) ? (bool) $_POST["extract"] : false;
            $_POST[$this->getPostVar()]["title"] = isset($_POST["title"]) ? $_POST["title"] : "";
            $_POST[$this->getPostVar()]["description"] = isset($_POST["description"]) ? $_POST["description"] : "";
            $_POST[$this->getPostVar()]["keep_structure"] = isset($_POST["keep_structure"]) ? (bool) $_POST["keep_structure"] : true;

            $_POST[$this->getPostVar()]["name"] = ilStrLegacy::normalizeUtf8String($_POST[$this->getPostVar()]["name"]);
            $_POST[$this->getPostVar()]["title"] = ilStrLegacy::normalizeUtf8String($_POST[$this->getPostVar()]["title"]);
        }
        
        return $inputValid;
    }
    
    protected function getInputAcceptSuffixes($suffixes)
    {
        $list = $delim = "";
        
        if (is_array($suffixes) && count($suffixes) > 0) {
            foreach ($suffixes as $suffix) {
                $list .= $delim . "." . $suffix;
                $delim = ",";
            }
        }
        
        return $list;
    }
    
    protected function buildSuffixList($suffixes)
    {
        $list = $delim = "";
        
        if (is_array($suffixes) && count($suffixes) > 0) {
            foreach ($suffixes as $suffix) {
                $list .= $delim . "\"" . $suffix . "\"";
                $delim = ", ";
            }
        }
        
        return $list;
    }
    
    protected function getMaxFileSize()
    {
        // get the value for the maximal uploadable filesize from the php.ini (if available)
        $umf = ini_get("upload_max_filesize");
        // get the value for the maximal post data from the php.ini (if available)
        $pms = ini_get("post_max_size");
    
        //convert from short-string representation to "real" bytes
        $multiplier_a = array("K" => 1024, "M" => 1024 * 1024, "G" => 1024 * 1024 * 1024);
    
        $umf_parts = preg_split("/(\d+)([K|G|M])/", $umf, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $pms_parts = preg_split("/(\d+)([K|G|M])/", $pms, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    
        if (count($umf_parts) == 2) {
            $umf = $umf_parts[0] * $multiplier_a[$umf_parts[1]];
        }
        if (count($pms_parts) == 2) {
            $pms = $pms_parts[0] * $multiplier_a[$pms_parts[1]];
        }
    
        // use the smaller one as limit
        $max_filesize = min($umf, $pms);
    
        if (!$max_filesize) {
            $max_filesize = max($umf, $pms);
        }
        
        return $max_filesize;
    }
}
