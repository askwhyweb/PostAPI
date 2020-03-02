<?php

namespace OrviSoft\Cloudburst\Plugin;

class Registry
{
    private $data = [];
    private static $instance = null;

    private function __construct()
    {
    }

    private static function getInstance()
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        self::$instance = new Registry();
        return self::$instance;
    }

    private function _set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public static function set($key, $value)
    {
        self::getInstance()
            ->_set($key, $value);
    }

    private function _get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    public static function get($key)
    {
        return self::getInstance()
                   ->_get($key);
    }

    private function clearAll()
    {
        $this->data = [];
    }

    public static function reset()
    {
        self::getInstance()
            ->clearAll();
    }
}
