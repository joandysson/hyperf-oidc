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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Joandysson\HyperfOidc\AdapterConfig;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

/**
 * Class OidcAPI.
 */
class OidcAPI
{
    public function __construct(
        private AdapterConfig $config,
        private ?Client $client = null
    ) {
        $this->client ??= make(Client::class, [
            'config' => $this->clientConfig(),
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function authorization(array $grantValue): ResponseInterface
    {
        return $this->client->post($this->config->tokenEndpoint(), [
            'headers' => $this->getHeaders(),
            'form_params' => $this->formAuthorization($grantValue),
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function introspect(array $data): ResponseInterface
    {
        return $this->client->post($this->config->introspectionEndpoint(), [
            'headers' => $this->getHeaders(),
            'form_params' => $this->formIntrospect($data),
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function logout(string $refreshToken): ResponseInterface
    {
        return $this->client->post($this->config->endSessionEndpoint(), [
            'headers' => $this->getHeaders(),
            'form_params' => $this->formLogout($refreshToken),
        ]);
    }

    /**
     * @return string[]
     */
    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    private function formAuthorization(array $grantValue): array
    {
        $payload = array_merge($grantValue, $this->clientCredentials());

        if (($grantValue['grant_type'] ?? null) === GrantTypes::AUTHORIZATION_CODE) {
            $payload['redirect_uri'] = $this->config->redirectUri();
        }

        return $payload;
    }

    private function formLogout(string $refreshToken): array
    {
        return array_merge(
            [
                'refresh_token' => $refreshToken,
            ],
            $this->clientCredentials()
        );
    }

    private function formIntrospect(array $data): array
    {
        return array_merge($data, $this->clientCredentials());
    }

    private function clientCredentials(): array
    {
        $credentials = [
            'client_id' => $this->config->clientId(),
        ];

        $secret = $this->config->secret();

        if ($secret !== null && $secret !== '') {
            $credentials['client_secret'] = $secret;
        }

        return $credentials;
    }

    private function clientConfig(): array
    {
        return [
            'base_uri' => $this->config->issuer(),
            'timeout' => $this->config->timeout(),
            'http_errors' => false,
        ];
    }
}
