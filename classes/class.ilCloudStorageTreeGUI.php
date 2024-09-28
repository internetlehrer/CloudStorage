<?php

declare(strict_types=1);

/**
 * Class ilCloudStorageTreeGUI
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */


// use ILIAS\DI\Container;

class ilCloudStorageTreeGUI extends ilCloudStorageTreeExplorerLegacyGUI
{

    //private Container $dic;

    //protected $tree;
    
    // protected $log;

    // Sn: ToDo does this really work? 
    // parent constructor ilTreeExplorerGUI needs ilTree so i extended ilCloudStorageTree from ilTree (?)
    public function __construct(string $a_expl_id, ilObjCloudStorageGUI $a_parent_obj, string $a_parent_cmd, ilCloudStorageTree $tree)
    {
        global $tpl, $ilLog;
        //global $DIC;
        parent::__construct($a_expl_id, $a_parent_obj, $a_parent_cmd, $tree);
        $this->setSkipRootNode(false);
        $this->setPreloadChilds(false);
        $this->setAjax(true);

        // necessary from 5.4 to fix bug where only root node shows
        $this->setNodeOpen($this->getNodeId($this->getRootNode()));
        $this->log = $ilLog;
        $css = '.jstree a.clickable_node {
               color:black !important;
             }

             .jstree a:hover {
               color:#b2052e !important;
             }';
        $tpl->addInlineCss($css);
        //$DIC->ui()->mainTemplate()->addInlineCss($css);
        //$this->parent_obj->tpl->addInlineCss($css);
        // shows loading gif, which is hidden (hard-coded in tpl)
        $container_outer_id = "il_expl2_jstree_cont_out_" . $this->getId();
        //$DIC->ui()->mainTemplate()->addOnLoadCode('$("#' . $container_outer_id . '").removeClass("ilNoDisplay");');;
        $tpl->addOnLoadCode('$("#' . $container_outer_id . '").removeClass("ilNoDisplay");');;
        //$this->parent_obj->tpl->addOnLoadCode('$("#' . $container_outer_id . '").removeClass("ilNoDisplay");');
    }

    function getNodeIcon($a_node): string
    {
        if ($a_node->getType() == ilCloudStorageItem::TYPE_FILE) {
            $img = 'icon_dcl_file.svg';
        } else {
            $img = 'icon_dcl_fold.svg';
        }
        return ilObjCloudStorageGUI::getImagePath($img);
    }

    function getNodeIconAlt($a_node): string
    {
        return '';
    }

    function getNodeContent($node): string
    {   
        assert($this->parent_obj instanceof ilObjCloudStorageGUI);
        $node->getName() ? $name = $node->getName() : $name = $this->parent_obj->getRootName();
        return htmlspecialchars($name);
    }


    function getNodeHref($node): string
    {
        global $ilCtrl;
        $ilCtrl->setParameter($this->parent_obj, 'root_path', $this->urlencode($node->getFullPath()));

        return $ilCtrl->getLinkTarget($this->parent_obj, 'editProperties');
    }


    /**
     * urlencode without encoding slashes
     *
     * @param $str
     *
     * @return mixed
     */
    protected function urlencode($str)
    {
        return str_replace('%2F', '/', rawurlencode($str));
    }


    function isNodeClickable($node): bool
    {
        return ($node->getType() == ilCloudStorageItem::TYPE_FOLDER);
    }


    /**
     * Get root node.
     *
     * Please note that the class does not make any requirements how
     * nodes are represented (array or object)
     *
     * @return ownclFolder root node object/array
     */
    function getRootNode()
    {
        assert($this->tree instanceof ilCloudStorageTree);
        return $this->tree->getRootNode();
    }


    /**
     * Get id of a node
     *
     * @param mixed $a_node node array or object
     *
     * @return string id of node
     */
    function getNodeId($a_node)
    {
        return ilCloudStorageUtil::encodeBase64Path($a_node->getFullPath());
    }
}
