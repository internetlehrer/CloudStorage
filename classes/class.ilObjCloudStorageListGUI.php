<?php

//include_once "./Services/Repository/classes/class.ilObjectPluginListGUI.php";

/**
 * ListGUI implementation for CloudStorage object plugin. This one
 * handles the presentation in container items (categories, courses, ...)
 * together with the corresponfing ...Access class.
 *
 * PLEASE do not create instances of larger classes here. Use the
 * ...Access class to get DB data and keep it small.
 *
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 * @ilCtrl_Calls ilObjCloudStorageListGUI: ilCommonActionDispatcherGUI
 */
class ilObjCloudStorageListGUI extends ilObjectPluginListGUI
{
    /**
     * Init type
     */
    public function initType()
    {
        $this->setType("xcls");
    }

    /**
     * Get name of gui class handling the commands
     */
    public function getGuiClass(): string
    {
        return "ilObjCloudStorageGUI";
    }

    /**
     * Get commands
     */
    public function initCommands(): array
    {
        return array(
            array(
                "permission" => "read",
                "cmd" => "showContent",
                "default" => true),
            array(
                "permission" => "write",
                "cmd" => "editProperties",
                "txt" => $this->txt("edit"),
                "default" => false),
        );
    }

    /**
     * Get item properties
     *
     * @return	array		array of property arrays:
     *						"alert" (boolean) => display as an alert property (usually in red)
     *						"property" (string) => property name
     *						"value" (string) => property value
     */
    public function getProperties(): array
    {
        $props = array();

        // offline modes are a bit confused, needs streamling with more explicit messages and actions

        $props[] = array(
                "alert" => false,
                "property" => $this->txt("conn_id"),
                "value" => ilObjCloudStorage::getConnTitleFromObjId($this->obj_id)
            );

        if (!ilObjCloudStorageAccess::checkConnAvailability($this->obj_id)) {
            $props[] = array(
                "alert" => true,
                "property" => $this->txt("status"),
                "value" => $this->txt("not_available"),
            );
            return $props;
        }

        if (!ilObjCloudStorageAccess::checkOnline($this->obj_id)) {
            $props[] = array("alert" => true, "property" => $this->txt("status"),
                "value" => $this->txt("offline"));
            return $props;
        }

        if (!ilObjCloudStorageAccess::checkAuthStatus($this->obj_id)) {
            $props[] = array(
                "alert" => true,
                "property" => $this->txt("status"),
                "value" => $this->txt("cld_not_authenticated_offline"),
            );
            return $props;
        }

        return $props;

    }
}
