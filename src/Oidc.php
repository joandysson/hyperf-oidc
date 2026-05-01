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

use GuzzleHttp\Exception\GuzzleException;
use Joandysson\HyperfOidc\Exceptions\InvalidResponseException;
use Joandysson\HyperfOidc\Exceptions\MissingTokenException;
use Joandysson\HyperfOidc\Exceptions\OidcException;
use Joandysson\HyperfOidc\Utils\GrantTypes;
use Joandysson\HyperfOidc\Utils\OidcAPI;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Oidc
{
    private AdapterConfig $config;

    private OidcAPI $oidcAPI;

    public function __construct(
        ?AdapterConfig $config = null,
        ?OidcAPI $oidcAPI = null
    ) {
        $this->config = $config ?? make(AdapterConfig::class);
        $this->oidcAPI = $oidcAPI ?? make(OidcAPI::class);
    }

    public function getRedirectUri(): string
    {
        return $this->config->redirectUri();
    }

    public function getIssuer(): string
    {
        return $this->config->issuer();
    }

    public function generateCodeVerifier(?string $codeVerifier = null): string
    {
        return $codeVerifier ?? rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    public function getLoginUrl(?string $state = null, ?string $scope = null, ?string $codeVerifier = null): string
    {
        return sprintf(
            '%s?%s',
            $this->config->authorizationEndpoint(),
            $this->parameters($state, $scope, $codeVerifier)
        );
    }

    /**
     * @throws GuzzleException
     */
    public function logout(string $refreshToken): ResponseInterface
    {
        return $this->oidcAPI->logout($refreshToken);
    }

    public function getClientId(): string
    {
        return $this->config->clientId();
    }

    public function getClientSecret(): ?string
    {
        return $this->config->secret();
    }

    /**
     * @throws GuzzleException
     */
    public function authorizationCode(string $code, ?string $codeVerifier = null): ResponseInterface
    {
        $payload = [
            'code' => $code,
        ];

        if ($codeVerifier !== null && $codeVerifier !== '') {
            $payload['code_verifier'] = $codeVerifier;
        }

        return $this->oidcAPI->authorization($this->prepareGrantTypeValue(GrantTypes::AUTHORIZATION_CODE, $payload));
    }

    /**
     * @throws GuzzleException
     */
    public function authorizationToken(string $refreshToken): ResponseInterface
    {
        return $this->oidcAPI->authorization($this->prepareGrantTypeValue(GrantTypes::REFRESH_TOKEN, [
            'refresh_token' => $refreshToken,
        ]));
    }

    /**
     * @throws GuzzleException
     */
    public function authorizationLogin(string $username, string $password, ?string $scope = null): ResponseInterface
    {
        return $this->oidcAPI->authorization($this->prepareGrantTypeValue(GrantTypes::PASSWORD, [
            'username' => $username,
            'password' => $password,
            'scope' => $this->scope($scope),
        ]));
    }

    /**
     * @throws GuzzleException
     */
    public function authorizationClientCredentials(?string $scope = null): ResponseInterface
    {
        return $this->oidcAPI->authorization($this->prepareGrantTypeValue(GrantTypes::CLIENT_CREDENTIALS, [
            'scope' => $this->scope($scope),
        ]));
    }

    /**
     * @throws GuzzleException
     */
    public function introspect(string $token, ?string $tokenTypeHint = null): ResponseInterface
    {
        $payload = ['token' => $token];

        if ($tokenTypeHint !== null) {
            $payload['token_type_hint'] = $tokenTypeHint;
        }

        return $this->oidcAPI->introspect($payload);
    }

    /**
     * @throws OidcException
     */
    public function json(ResponseInterface $response): array
    {
        try {
            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidResponseException('Invalid OIDC JSON response.', previous: $exception);
        }

        if (! is_array($payload)) {
            throw new InvalidResponseException('Invalid OIDC JSON response.');
        }

        return $payload;
    }

    /**
     * @throws OidcException
     */
    public function tokenPayload(ResponseInterface $response): array
    {
        $payload = $this->json($response);

        if (! isset($payload['access_token']) || ! is_string($payload['access_token']) || $payload['access_token'] === '') {
            throw new MissingTokenException('OIDC access token missing.');
        }

        return $payload;
    }

    private function parameters(?string $state, ?string $scope, ?string $codeVerifier): string
    {
        $parameters = [
            'client_id' => $this->config->clientId(),
            'response_type' => 'code',
            'redirect_uri' => $this->config->redirectUri(),
        ];

        if ($state !== null && $state !== '') {
            $parameters['state'] = $state;
        }

        $scope = $this->scope($scope);

        if ($scope !== '') {
            $parameters['scope'] = $scope;
        }

        if ($codeVerifier !== null && $codeVerifier !== '') {
            $parameters['code_challenge'] = $this->codeChallenge($codeVerifier);
            $parameters['code_challenge_method'] = 'S256';
        }

        return http_build_query($parameters, '', null, PHP_QUERY_RFC3986);
    }

    private function prepareGrantTypeValue(string $grantType, array $grantValue): array
    {
        return array_merge(['grant_type' => $grantType], $grantValue);
    }

    private function scope(?string $scope): string
    {
        $scope = trim((string) $scope);

        return $scope === ''
            ? $this->config->scope()
            : trim(sprintf('%s %s', $this->config->scope(), $scope));
    }

    private function codeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
