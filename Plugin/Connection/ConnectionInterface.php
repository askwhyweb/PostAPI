<?php

namespace OrviSoft\Cloudburst\Plugin\Connection;

interface ConnectionInterface
{
    public function init();

    public function close();

    public function addHeader($header);

    public function getHeaders();

    public function setTimeout($timeout);

    public function setSSLCert($ssl_cert);

    public function getSSLCert();

    public function setSSLVerifyPeer($ssl_verify_peer);

    public function getSSLVerifyPeer();

    public function execute($query_string = null, $post_string = null);
}
