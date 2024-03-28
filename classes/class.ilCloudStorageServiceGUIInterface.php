<?php

interface ilCloudStorageServiceGUIInterface
{
    public function initPropertiesForm(): void;

    public function getPropertiesValues(array &$values): void;

    public function editProperties(): void;

    public function updateProperties(): void;

    public function addItemsBefore(ilCloudStorageFileNode $node, ilAdvancedSelectionListGUI &$selection_list): void;

    public function addItemsAfter(ilCloudStorageFileNode $node, ilAdvancedSelectionListGUI &$selection_list): void;

    public function checkHasAction(ilCloudStorageFileNode $node): bool;

    public function openInPlatform(): void;

}
