<?php

namespace OrviSoft\Cloudburst\Plugin\Connection;

use OrviSoft\Cloudburst\Plugin\Functions;

class Curl implements ConnectionInterface
{
    private $url;
    private $handler;
    private $headers = [];
    private $options = [];

    function __construct($url)
    {
        $this->url = $url;
    }

    function init()
    {
        $this->close();
        $this->handler = \curl_init($this->url);
        $this->options = [];
        $this->headers = [];
        $this->setOption(CURLOPT_RETURNTRANSFER, 1);
        $this->setOption(CURLOPT_CONNECTTIMEOUT, 3);
        $this->setOption(CURLOPT_HEADER, 1);
        $this->setOption(CURLOPT_TIMEOUT, 30);
        $this->setOption(CURLOPT_SSL_VERIFYHOST, 2);
        $this->setOption(CURLOPT_SSL_VERIFYPEER, 0);
    }

    function close()
    {
        if ($this->handler !== null) {
            \curl_close($this->handler);
            $this->handler = null;
        }
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function addHeader($header)
    {
        if (!\in_array($header, $this->headers)) {
            $this->headers[] = $header;
        }
        return $this;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setTimeout($timeout)
    {
        $this->setOption(CURLOPT_TIMEOUT, $timeout);
    }

    public function setSSLCert($ssl_cert)
    {
        $this->setOption(CURLOPT_SSLCERT, $ssl_cert);
        return $this;
    }

    public function getSSLCert()
    {
        return $this->getOption(CURLOPT_SSLCERT);
    }

    public function setSSLVerifyPeer($ssl_verify_peer)
    {
        $this->setOption(CURLOPT_SSL_VERIFYPEER, $ssl_verify_peer);
        return $this;
    }

    public function getSSLVerifyPeer()
    {
        return $this->getOption(CURLOPT_SSL_VERIFYPEER);
    }

    public function setOption($key, $value)
    {
        $this->options[$key] = $value;
        return $this;
    }

    public function getOption($key)
    {
        return isset($this->options[$key]) ? $this->options[$key] : null;
    }

    public function getOptions()
    {
        return $this->options;
    }

    function curlExec()
    {
        return \curl_exec($this->handler);
    }

    function curlGetInfo($opt)
    {
        return \curl_getinfo($this->handler, $opt);
    }

    function execute($query_string = null, $post_string = null)
    {
        if ($this->handler === null) {
            $this->init();
        }
        if ($query_string !== null) {
            $this->setOption(CURLOPT_URL, $this->url . $query_string);
        }
        if ($post_string !== null) {
            $this->setOption(CURLOPT_POST, 1);
            $this->setOption(CURLOPT_POSTFIELDS, $post_string);
        }
        if (!empty($this->headers)) {
            $this->setOption(CURLOPT_HTTPHEADER, $this->headers);
        }
        if (!empty($this->options)) {
            \curl_setopt_array($this->handler, $this->options);
        }
        $response = $this->curlExec();
        if ($response === false) {
            return (object) [
                'code'    => 0,
                'headers' => [],
                'body'    => 'Error: ' .
                             \curl_error($this->handler) .
                             '. No: ' .
                             \curl_errno($this->handler),
            ];
        }
        $code        = $this->curlGetInfo(CURLINFO_HTTP_CODE);
        $header_size = $this->curlGetInfo(CURLINFO_HEADER_SIZE);
        $header      = \substr($response, 0, $header_size);
        $headers     = Functions::parse_http_headers($header);
        $body        = \substr($response, $header_size);
        return (object) [
            'code'    => $code,
            'headers' => $headers,
            'body'    => $body,
        ];
    }

    function getHandler()
    {
        return $this->handler;
    }

    function __destruct()
    {
        $this->close();
    }
}
