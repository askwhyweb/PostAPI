<?php

namespace OrviSoft\Cloudburst\Plugin;

use OrviSoft\Cloudburst\Plugin\Connection\Curl;
use OrviSoft\Cloudburst\Plugin\Logger\ArrayLogger;
use OrviSoft\Cloudburst\Plugin\Logger\File;
use OrviSoft\Cloudburst\Plugin\Logger\NullLogger;
use OrviSoft\Cloudburst\Plugin\Logger\RotatingFiles;
use OrviSoft\Cloudburst\Plugin\Logger\Screen;
use OrviSoft\Cloudburst\Plugin\Factory\Event;
use OrviSoft\Cloudburst\Plugin\Factory\DTO;

class Functions
{
    private function __construct()
    {
    }

    static function camelcase($string, $separator = null)
    {
        if ($separator === null) {
            $separator = '_';
        }
        return \str_replace(' ', '', \ucwords(\str_replace($separator, ' ', $string)));
    }

    static function parse_http_headers($header)
    {
        $headers = [];
        $key     = '';
        foreach (\explode("\n", $header) as $i => $h) {
            $h = \explode(':', $h, 2);
            if (isset($h[1])) {
                if (!isset($headers[$h[0]])) {
                    $headers[$h[0]] = \trim($h[1]);
                } elseif (\is_array($headers[$h[0]])) {
                    $headers[$h[0]] = \array_merge($headers[$h[0]], [\trim($h[1])]);
                } else {
                    $headers[$h[0]] = \array_merge([$headers[$h[0]]], [\trim($h[1])]);
                }
                $key = $h[0];
            } else {
                if (\substr($h[0], 0, 1) == "\t") {
                    $headers[$key] .= "\r\n\t" . \trim($h[0]);
                } elseif (!$key) {
                    $headers[0] = \trim($h[0]);
                }
            }
        }
        return $headers;
    }

    static function guidv4()
    {
        if (\function_exists('openssl_random_pseudo_bytes')) {
            $data    = \openssl_random_pseudo_bytes(16);
            $data[6] = \chr(\ord($data[6]) & 0x0f | 0x40);
            $data[8] = \chr(\ord($data[8]) & 0x3f | 0x80);
            return \vsprintf('%s%s-%s-%s-%s-%s%s%s', \str_split(\bin2hex($data), 4));
        } else {
            return \sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                            \mt_rand(0, 0xffff),
                            \mt_rand(0, 0xffff),
                            \mt_rand(0, 0xffff),
                            \mt_rand(0, 0x0fff) | 0x4000,
                            \mt_rand(0, 0x3fff) | 0x8000,
                            \mt_rand(0, 0xffff),
                            \mt_rand(0, 0xffff),
                            \mt_rand(0, 0xffff)
            );
        }
    }

    static function float_equals($float1, $float2)
    {
        if (\function_exists('bccomp')) {
            return \bccomp($float1, $float2, 10) === 0;
        }
        $epsilon = 0.0000000001;
        return \abs($float1 - $float2) < $epsilon;
    }
}
