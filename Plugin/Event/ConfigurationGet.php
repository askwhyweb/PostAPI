<?php

namespace OrviSoft\Cloudburst\Plugin\Event;

class ConfigurationGet extends AbstractEvent
{
    public function getResourceType()
    {
        return 'configuration';
    }

    public function getLifecycleEvent()
    {
        return 'get';
    }

    public function parseObjects(array $objects)
    {
    }

    public function getObjects()
    {
        return [];
    }
}
