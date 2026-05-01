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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Contract\ConfigInterface;
use Joandysson\HyperfOidc\Exceptions\InvalidConfigException;

/**
 * Class AdapterConfig.
 */
class AdapterConfig
{
    public const CONFIG_FILE = 'oidc';

    private string $providerConfig;

    private ?array $discovery = null;

    /**
     * @throws InvalidConfigException
     */
    public function __construct(
        private ConfigInterface $config,
        ?string $provider = null,
        private ?Client $discoveryClient = null
    ) {
        $provider ??= $this->defaultProvider();
        $this->providerConfig = sprintf('%s.providers.%s', self::CONFIG_FILE, $provider);

        if (! is_array($this->config->get($this->providerConfig))) {
            throw new InvalidConfigException(sprintf('OIDC provider "%s" is not configured.', $provider));
        }

        if (! filter_var($this->redirectUri(), FILTER_VALIDATE_URL)) {
            throw new InvalidConfigException('Invalid redirect URI.');
        }

        if (! filter_var($this->issuer(), FILTER_VALIDATE_URL)) {
            throw new InvalidConfigException('Invalid OIDC issuer.');
        }

        if ($this->clientId() === '') {
            throw new InvalidConfigException('Invalid OIDC client id.');
        }

        if ($this->scope() === '') {
            throw new InvalidConfigException('Invalid OIDC scope.');
        }

        if ($this->timeout() < 0) {
            throw new InvalidConfigException('Invalid OIDC timeout.');
        }
    }

    public function issuer(): string
    {
        return rtrim((string) $this->config->get($this->key('issuer')), '/');
    }

    public function clientId(): string
    {
        return (string) $this->config->get($this->key('client_id'));
    }

    public function secret(): ?string
    {
        $secret = $this->config->get($this->key('client_secret'));

        return $secret === null ? null : (string) $secret;
    }

    public function redirectUri(): string
    {
        return (string) $this->config->get($this->key('redirect_uri'));
    }

    public function scope(): string
    {
        return (string) $this->config->get($this->key('scope'), 'openid');
    }

    public function timeout(): float
    {
        return (float) $this->config->get($this->key('timeout'), 0);
    }

    public function authorizationEndpoint(): string
    {
        return $this->endpoint('authorization', 'authorization_endpoint', '/authorize');
    }

    public function tokenEndpoint(): string
    {
        return $this->endpoint('token', 'token_endpoint', '/token');
    }

    public function introspectionEndpoint(): string
    {
        return $this->endpoint('introspection', 'introspection_endpoint', '/introspect');
    }

    public function endSessionEndpoint(): string
    {
        return $this->endpoint('end_session', 'end_session_endpoint', '/logout');
    }

    public function discoveryEnabled(): bool
    {
        return (bool) $this->config->get($this->key('discovery.enabled'), true);
    }

    public function discoveryUrl(): string
    {
        $url = (string) $this->config->get($this->key('discovery.url'), '');

        if ($url !== '') {
            return $url;
        }

        return sprintf('%s/.well-known/openid-configuration', $this->issuer());
    }

    private function defaultProvider(): string
    {
        return (string) $this->config->get(sprintf('%s.default', self::CONFIG_FILE), 'default');
    }

    private function key(string $key): string
    {
        return sprintf('%s.%s', $this->providerConfig, $key);
    }

    private function endpoint(string $name, string $metadataKey, string $default): string
    {
        $endpoint = null;

        if ($this->discoveryEnabled()) {
            $endpoint = $this->discovery()[$metadataKey] ?? null;
        }

        if (! is_string($endpoint) || $endpoint === '') {
            $endpoint = (string) $this->config->get($this->key(sprintf('endpoints.%s', $name)), $default);
        }

        return $this->normalizeEndpoint($endpoint);
    }

    private function normalizeEndpoint(string $endpoint): string
    {
        if ($endpoint === '') {
            throw new InvalidConfigException('Invalid OIDC endpoint.');
        }

        if (filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return $endpoint;
        }

        return sprintf('%s/%s', $this->issuer(), ltrim($endpoint, '/'));
    }

    private function discovery(): array
    {
        if ($this->discovery !== null) {
            return $this->discovery;
        }

        try {
            $client = $this->discoveryClient ?? new Client([
                'timeout' => $this->timeout() > 0 ? $this->timeout() : 2.0,
                'http_errors' => false,
            ]);
            $response = $client->get($this->discoveryUrl());

            if ($response->getStatusCode() !== 200) {
                return $this->discovery = [];
            }

            $metadata = json_decode((string) $response->getBody(), true);

            return $this->discovery = is_array($metadata) ? $metadata : [];
        } catch (GuzzleException) {
            return $this->discovery = [];
        }
    }
}
