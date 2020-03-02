<?php

namespace OrviSoft\Cloudburst\Plugin;

class Plugin
{
    private static $config;
    private static $bridge;

    public static function getInstance()
    {
        if (!defined('MOTHERCLOUD_BRIDGE_PLUGIN_ROOT')) {
            define('MOTHERCLOUD_BRIDGE_PLUGIN_ROOT', realpath(dirname(__FILE__)));

            if (is_readable(MOTHERCLOUD_BRIDGE_PLUGIN_ROOT . '/config.php')) {
                self::$config = include(MOTHERCLOUD_BRIDGE_PLUGIN_ROOT . '/config.php');
            }
            if (isset(self::$config) && is_array(self::$config)) {
                foreach (self::$config AS $key => $value) {
                    Registry::set('config::' . $key, $value);
                }
            }
            self::$bridge = Functions::bridge();
            define('MOTHERCLOUD_BRIDGE_VERSION', '2.0.1');
        }
        self::$bridge = Functions::bridge();
        return self::$bridge;
    }
}
