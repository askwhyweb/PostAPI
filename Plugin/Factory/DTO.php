<?php

namespace OrviSoft\Cloudburst\Plugin\Factory;

use OrviSoft\Cloudburst\Plugin\Functions;

class DTO
{
    public function create($key)
    {
        $key        = Functions::camelcase($key);
        $class_name = '\\Mothercloud\\Bridge\\Plugin\\DTO\\' . $key;
        return new $class_name();
    }
}
