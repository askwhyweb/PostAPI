<?php

namespace OrviSoft\Cloudburst\Plugin\Logger;

class Screen implements LoggerInterface
{
    public function log($text)
    {
        print_r($text . PHP_EOL);
    }
}
