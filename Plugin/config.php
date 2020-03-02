<?php

return [
    //These are to be used if the cart integration does not include a native Bridge configuration section
    'client_name'     => '',
    'source'          => '',
    'auth'            => '',

    //The url that the connector will try to communicate to
    'bridge_callback' => 'https://eu.mothercloud.com/callback.php',

    //The ssl certificate file
    'ssl_pem'         => '',

    //Queue settings
    'queue_dir'       => BP . '/var/bridge/queue',

    //Lock settings
    'lock_dir'       => BP . '/var/bridge/lock',

    //The log settings
    'log_type'        => 'files',
    'log_dir'         => BP . '/var/bridge/log',
    #'log_file' => '', //When logger is file, the filename to write logs to
];
