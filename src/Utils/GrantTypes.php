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

namespace Joandysson\HyperfOidc\Utils;

/**
 * Class GrantTypes.
 */
class GrantTypes
{
    public const AUTHORIZATION_CODE = 'authorization_code';

    public const IMPLICIT = 'implicit';

    public const REFRESH_TOKEN = 'refresh_token';

    public const PASSWORD = 'password';

    public const CLIENT_CREDENTIALS = 'client_credentials';
}
