<?php

namespace OrviSoft\Cloudburst\Plugin\Logger;

class NullLogger implements LoggerInterface
{
    public function log($text)
    {
    }
}
