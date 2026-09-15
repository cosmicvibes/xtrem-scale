<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scale IP Address
    |--------------------------------------------------------------------------
    |
    | The IP address of your Gram Xtreme F-06 scale on the network
    |
    */
    'ip_address' => env('XTREM_SCALE_IP', '192.168.1.100'),

    /*
    |--------------------------------------------------------------------------
    | Send Port
    |--------------------------------------------------------------------------
    |
    | The UDP port to send commands to the scale (default: 4445)
    |
    */
    'send_port' => env('XTREM_SCALE_SEND_PORT', 4445),

    /*
    |--------------------------------------------------------------------------
    | Receive Port
    |--------------------------------------------------------------------------
    |
    | The UDP port to receive data from the scale (default: 5556)
    |
    */
    'receive_port' => env('XTREM_SCALE_RECEIVE_PORT', 5556),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Connection timeout in seconds (default: 5)
    |
    */
    'timeout' => env('XTREM_SCALE_TIMEOUT', 5),

];
