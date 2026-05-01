<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf OIDC.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use function Hyperf\Support\env;

return [
    'default' => 'default',
    'providers' => [
        'default' => [
            'issuer' => env('OIDC_ISSUER'),
            'client_id' => env('OIDC_CLIENT_ID'),
            'client_secret' => env('OIDC_CLIENT_SECRET'),
            'redirect_uri' => env('OIDC_REDIRECT_URI'),
            'timeout' => (float) env('OIDC_TIMEOUT', 0),
            'scope' => env('OIDC_SCOPE', 'openid'),
            'discovery' => [
                'enabled' => (bool) env('OIDC_DISCOVERY_ENABLED', true),
                'url' => env('OIDC_DISCOVERY_URL'),
            ],
            'endpoints' => [
                'authorization' => env('OIDC_AUTHORIZATION_ENDPOINT', '/authorize'),
                'token' => env('OIDC_TOKEN_ENDPOINT', '/token'),
                'introspection' => env('OIDC_INTROSPECTION_ENDPOINT', '/introspect'),
                'end_session' => env('OIDC_END_SESSION_ENDPOINT', '/logout'),
            ],
        ],
    ],
];
