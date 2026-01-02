<?php
/**
 * Basic PSR-4 like autoloader for KH Folder plugin.
 */

namespace KHFolders\Core;

class Autoloader
{
    private $prefix;
    private $baseDir;

    public function __construct($prefix, $baseDir)
    {
        $this->prefix = trim($prefix, '\\') . '\\';
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function register()
    {
        spl_autoload_register([$this, 'load']);
    }

    private function load($class)
    {
        if (strpos($class, $this->prefix) !== 0) {
            return;
        }

        $relative = substr($class, strlen($this->prefix));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        $file = $this->baseDir . $relativePath;

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
