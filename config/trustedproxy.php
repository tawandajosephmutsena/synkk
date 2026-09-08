<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Set the IP addresses or CIDR blocks of proxies to trust. Use '*' to
    | trust all proxies (e.g. behind Cloudflare, AWS ALB, or Docker).
    | Comma-separated strings or arrays are supported.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
