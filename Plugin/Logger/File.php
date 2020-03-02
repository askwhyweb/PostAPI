<?php

namespace OrviSoft\Cloudburst\Plugin\Logger;

class File implements LoggerInterface
{
    private $file;

    public function __construct($file)
    {
        $this->file = $file;
    }

    public function log($text)
    {
        $handler = \fopen($this->file, 'a+');
        \fwrite($handler, \gmdate('d.m.Y H:i:s T', \time()) . ' | ' . $text . PHP_EOL . PHP_EOL);
        \fclose($handler);
    }
}
