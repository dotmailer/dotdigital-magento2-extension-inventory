<?php

namespace Dotdigitalgroup\Inventory\Plugin;

use Dotdigitalgroup\Email\Model\Connector\Module;

class ModulePlugin
{
    private const MODULE_NAME = 'Dotdigitalgroup_Inventory';
    private const MODULE_DESCRIPTION = 'Dotdigital for Magento Inventory Management';

    /**
     * @var Module
     */
    private $module;

    /**
     * @param Module $module
     */
    public function __construct(Module $module)
    {
        $this->module = $module;
    }

    /**
     * Add this module to the list of active DD modules.
     *
     * @param Module $module
     * @param array $modules
     * @return array
     */
    public function beforeFetchActiveModules(Module $module, array $modules = [])
    {
        $modules[] = [
            'name' => self::MODULE_DESCRIPTION,
            'version' => $this->module->getModuleVersion(self::MODULE_NAME)
        ];
        return [
            $modules
        ];
    }
}
