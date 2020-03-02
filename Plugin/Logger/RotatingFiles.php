<?php

namespace OrviSoft\Cloudburst\Plugin\Logger;

class RotatingFiles implements LoggerInterface
{
    private $dir;
    private $file;
    private $chanceOfCleanup = 1000;
    private $numberOfOldLogs = 6;
    private $uniqueId;

    public function __construct($dir)
    {
        $this->dir      = $dir;
        $this->file     = $this->createLogName();
        $this->uniqueId = \uniqid(\getmypid());
    }

    private function createLogName($time = null)
    {
        if (!$time) {
            $time = \time();
        }
        return \date('Y-m-d', $time) . '.log';
    }

    public function log($text)
    {
        $handler = \fopen($this->dir . '/' . $this->file, 'a+');
        \fwrite($handler, \gmdate('Y-m-d\TH:i:s\Z') . ' @' . $this->uniqueId . ' ' . $text . PHP_EOL);
        \fclose($handler);
    }

    public function clearOldLogs()
    {
        $first_log = $this->createLogName(\strtotime($this->numberOfOldLogs . ' days ago'));
        foreach (\glob($this->dir . '/????-??-??.log') as $filename) {
            if (\basename($filename) < $first_log) {
                \unlink($filename);
            }
        }
    }

    public function __destruct()
    {
        if (\mt_rand(0, $this->chanceOfCleanup) === 0) {
            $this->clearOldLogs();
        }
    }
}
