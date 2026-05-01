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

namespace Joandysson\HyperfOidc;

use Hyperf\Contract\ConfigInterface;
use Joandysson\HyperfOidc\Utils\OidcAPI;

class OidcFactory
{
    public function __construct(
        private ConfigInterface $config
    ) {}

    public function forProvider(string $provider): Oidc
    {
        $config = new AdapterConfig($this->config, $provider);

        return new Oidc($config, new OidcAPI($config));
    }
}
