<?php

namespace OrviSoft\Cloudburst\Plugin;

use OrviSoft\Cloudburst\Plugin\Event\AbstractEvent;

class Queue
{
    private $queueDir;
    private $lockHandle;

    public function __construct($queue_directory)
    {
        $this->queueDir = \realpath($queue_directory);
    }

    public function send(AbstractEvent $event, Connector $connector)
    {
        $queued = (bool) $this->enqueue($event);
        $this->processQueue($connector);
        return $queued;
    }

    public function enqueue(AbstractEvent $event)
    {
        $json = \json_encode($event->toArray());
        list($usec, $sec) = \explode(' ', \microtime());
        $filename = \sprintf('%s.%s.json', $sec, \substr($usec, 2));
        $bytes    = \file_put_contents($this->queueDir . DIRECTORY_SEPARATOR . $filename, $json);
        return $bytes === \strlen($json);
    }

    public function processQueue(Connector $connector)
    {
        if (!$this->getLock()) {
            return false;
        }
        $sent = 0;
        while ($filename = $this->getNextFilename()) {
            $contents = \file_get_contents($filename);
            if ($this->itemInvalid($contents)) {
                \unlink($filename);
                continue;
            }
            $event = Functions::factory_event()
                              ->createFromString($contents);
            $ret   = $connector->sendEvent($event);
            if (!$ret || !$this->isHttpStatusSuccessful($ret->code)) {
                break;
            }
            $sent++;
            if (\file_exists($filename)) {
                \unlink( $filename );
            }
        }
        $this->releaseLock();
        return $sent;
    }

    private function itemInvalid($contents)
    {
        return $contents === '';
    }

    private function isHttpStatusSuccessful($status_code)
    {
        return $status_code >= 200 && $status_code < 300;
    }

    private function getLock()
    {
        $this->lockHandle = \fopen($this->queueDir . '/transmit.lock', 'w');
        return \flock($this->lockHandle, LOCK_EX | LOCK_NB);
    }

    private function getNextFilename()
    {
        $queue = \glob($this->queueDir . '/*.json');
        return isset($queue[0]) ? $queue[0] : false;
    }

    private function releaseLock()
    {
        if ($this->lockHandle === null) {
            return;
        }
        \flock($this->lockHandle, LOCK_UN);
    }

    public function getQueueDir()
    {
        return $this->queueDir;
    }

    public function __destruct()
    {
        $this->releaseLock();
    }
}
