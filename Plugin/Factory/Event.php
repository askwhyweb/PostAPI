<?php

namespace OrviSoft\Cloudburst\Plugin\Factory;

use OrviSoft\Cloudburst\Plugin\Exception;
use OrviSoft\Cloudburst\Plugin\Functions;

class Event
{
    public function create($key)
    {
        $key        = Functions::camelcase($key);
        $class_name = '\\Mothercloud\\Bridge\\Plugin\\Event\\' . $key;
        return new $class_name();
    }

    public function createFromString($string)
    {
        $data = \json_decode($string, true);
        if ($data === null) {
            throw new Exception('Invalid json string given');
        }
        if (!isset($data['resourceType']{0})) {
            throw new Exception('ResourceType not present');
        }
        if (!isset($data['lifecycleEvent']{0})) {
            throw new Exception('LivecycleEvent not present');
        }
        $key   = \str_replace('.', '_', $data['resourceType']) . '_' . $data['lifecycleEvent'];
        $event = $this->create($key);
        if (isset($data['auth']{0})) {
            $event->setAuth($data['auth']);
        }
        if (isset($data['clientName']{0})) {
            $event->setClientName($data['clientName']);
        }
        if (isset($data['source']{0})) {
            $event->setSource($data['source']);
        }
        if (isset($data['idSet']{0})) {
            $event->setIdSet($data['idSet']);
        }
        if (isset($data['objects'])) {
            $event->parseObjects($data['objects']);
        }
        if (isset($data['meta'])) {
            $event->setMeta($data['meta']);
        }
        return $event;
    }
}
