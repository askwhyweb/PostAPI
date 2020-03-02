<?php

namespace OrviSoft\Cloudburst\Plugin\Event;

abstract class AbstractEvent
{
    protected $auth;
    protected $idSet;
    protected $clientName;
    protected $source;
    protected $meta = [];

    abstract public function parseObjects(array $objects);

    public function setAuth($auth)
    {
        $this->auth = $auth;
    }

    public function getAuth()
    {
        return $this->auth;
    }

    abstract public function getResourceType();

    public function setIdSet($idSet)
    {
        $this->idSet = $idSet;
    }

    public function getIdSet()
    {
        return $this->idSet;
    }

    public function setMeta(array $meta, $append = false)
    {
        if ($append !== false) {
            $this->meta = \array_merge($meta, $this->meta);
        } else {
            $this->meta = $meta;
        }
    }

    public function getMeta()
    {
        return $this->meta;
    }

    abstract public function getLifecycleEvent();

    abstract public function getObjects();

    public function setClientName($clientName)
    {
        $this->clientName = $clientName;
    }

    public function getClientName()
    {
        return $this->clientName;
    }

    public function setSource($source)
    {
        $this->source = $source;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function toArray()
    {
        return [
            'auth'           => $this->auth,
            'clientName'     => $this->clientName,
            'source'         => $this->source,
            'resourceType'   => $this->getResourceType(),
            'idSet'          => $this->idSet,
            'lifecycleEvent' => $this->getLifecycleEvent(),
            'objects'        => $this->getObjects(),
            'meta'           => $this->getMeta(),
        ];
    }

    public function toJson()
    {
        return \json_encode($this->toArray());
    }
}
