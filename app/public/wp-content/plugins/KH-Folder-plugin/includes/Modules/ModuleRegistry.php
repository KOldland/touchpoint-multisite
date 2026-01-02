<?php
namespace KHFolders\Modules;

class ModuleRegistry
{
    private $modules = [];

    public function add(ModuleInterface $module)
    {
        $this->modules[] = $module;
    }

    public function boot()
    {
        foreach ($this->modules as $module) {
            $module->register();
        }
    }
}
