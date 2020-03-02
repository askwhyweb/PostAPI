<?php

namespace OrviSoft\Cloudburst\Plugin\Logger;

class ArrayLogger implements LoggerInterface
{
    private $logs = [];

    public function log($text)
    {
        $this->logs[] = $text;
    }

    public function getLogs()
    {
        return $this->logs;
    }

    public function getLast()
    {
        $count = \count($this->logs);
        return $count > 0 ? $this->logs[$count - 1] : null;
    }
}
