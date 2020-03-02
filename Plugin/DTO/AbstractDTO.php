<?php

namespace OrviSoft\Cloudburst\Plugin\DTO;

abstract class AbstractDTO
{
    public function enforceUnicode($string)
    {
        if (\function_exists('mb_detect_encoding') &&
            \mb_detect_encoding($string,
                                [
                                    'UTF-8',
                                    'UTF-7',
                                    'ASCII',
                                ],
                                true
            ) === false
        ) {
            $string = \utf8_encode($string);
        }
        return $string;
    }

    abstract public function toArray();
}
