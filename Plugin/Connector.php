<?php

namespace OrviSoft\Cloudburst\Plugin;

use OrviSoft\Cloudburst\Plugin\Connection\ConnectionInterface;
use OrviSoft\Cloudburst\Plugin\Logger\LoggerInterface;
use OrviSoft\Cloudburst\Plugin\Event\AbstractEvent;
use StdClass;

class Connector
{
    private $clientName;
    private $source;
    private $auth;
    private $sslCert;
    private $retries;
    private $lastRetries;
    private $connection;
    private $logger;

    public function __construct(ConnectionInterface $connection, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger     = $logger;
        $this->retries    = 0;
    }

    public function addAuthDetails(AbstractEvent $event)
    {
        $data = $event->toArray();
        if (!isset($data['auth']) || $data['auth'] == '') {
            $event->setAuth($this->getAuth());
        }
        if (!isset($data['source']) || $data['source'] == '') {
            $event->setSource($this->getSource());
        }
        if (!isset($data['clientName']) || $data['clientName'] == '') {
            $event->setClientName($this->getClientName());
        }
        return $event;
    }

    public function sendEvent(AbstractEvent $event)
    {
        $event        = $this->addAuthDetails($event);
        $data         = $event->toArray();
        $data['guid'] = Functions::guidv4();
        $post         = \json_encode($data);
        $this->connection->init();
        $this->connection->addHeader('Content-Type: application/json');
        if ($this->sslCert !== null) {
            $this->connection->setSSLCert($this->sslCert)
                             ->setSSLVerifyPeer(1);
        }
        $this->logger->log('Sending data to bridge: ' . $post);
        $this->lastRetries = 0;
        $response          = null;
        while (true) {
            $response = $this->connection->execute('', $post);
            $this->logger->log('Receiving from bridge: ' . \json_encode($response));
            if ($response && $response->code === 202) {
                break;
            }
            if ($this->lastRetries >= $this->retries) {
                $this->logger->log('Failed: Maximum retries reached. ' . $this->lastRetries . '. Data: ' . $post);
                break;
            }
            $this->lastRetries++;
            $this->logger->log('Retrying #' . $this->lastRetries . ': ' . $post);
        }
        return $response;
    }

    function parseRequest($json, array $server)
    {
        $ret = new StdClass();
        if (!isset($server['SERVER_PROTOCOL'])) {
            $server['SERVER_PROTOCOL'] = 'HTTP/1.1';
        }
        if (!isset($server['REQUEST_METHOD']) || $server['REQUEST_METHOD'] !== 'POST') {
            $ret->status  = 0;
            $ret->headers = [
                $server['SERVER_PROTOCOL'] . ' 405 Method Not Allowed',
                'Allow: POST',
            ];
            $ret->body    = 'Only post requests are allowed';
            return $ret;
        }
        try {
            $event = Functions::factory_event()
                              ->createFromString($json);
        } catch (Exception $e) {
            $this->logger->log($e);
            $ret->status  = 0;
            $ret->headers = [$server['SERVER_PROTOCOL'] . ' 400 Bad Request'];
            $ret->body    = \json_encode([
                                             'response' => false,
                                             'errors'   => ['general' => 'Malformed data given'],
                                         ]
            );
            return $ret;
        }
        if ($this->checkAuth($event->getAuth()) !== true) {
            $ret->status  = 0;
            $ret->headers = [$server['SERVER_PROTOCOL'] . ' 403 Forbidden'];
            $ret->body    = \json_encode([
                                             'response' => false,
                                             'errors'   => ['general' => 'Authentication token not valid'],
                                         ]
            );
            return $ret;
        }
        $ret->status = 1;
        $ret->event  = $event;
        $ret->code   =
            Functions::camelcase($event->getResourceType(), '.') .
            Functions::camelcase($event->getLifecycleEvent());
        return $ret;
    }

    public function setCredentials($clientName, $source, $auth)
    {
        $this->clientName = $clientName;
        $this->source     = $source;
        $this->auth       = $auth;
        return $this;
    }

    function checkAuth($auth)
    {
        return $this->auth === $auth;
    }

    function getClientName()
    {
        return $this->clientName;
    }

    function setClientName($clientName)
    {
        $this->clientName = $clientName;
        return $this;
    }

    function getSource()
    {
        return $this->source;
    }

    function setSource($source)
    {
        $this->source = $source;
        return $this;
    }

    function getAuth()
    {
        return $this->auth;
    }

    function setAuth($auth)
    {
        $this->auth = $auth;
        return $this;
    }

    function getConnection()
    {
        return $this->connection;
    }

    function getLogger()
    {
        return $this->logger;
    }

    function setRetries($retries)
    {
        $this->retries = $retries;
        return $this;
    }

    function getRetries()
    {
        return $this->retries;
    }

    function setSSLCert($sslCert)
    {
        $this->sslCert = $sslCert;
        return $this;
    }

    function getSSLCert()
    {
        return $this->sslCert;
    }

    function getLastRetries()
    {
        return $this->lastRetries;
    }
}
