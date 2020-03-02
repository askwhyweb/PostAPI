<?php

namespace OrviSoft\Cloudburst\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class Exception extends \Exception
{
    public function __construct($message, \Exception $previous = null)
    {
        return new LocalizedException(new Phrase($message), $previous);
    }
}
