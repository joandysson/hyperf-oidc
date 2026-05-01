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

namespace HyperfTest;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Hyperf\Config\Config;
use Joandysson\HyperfOidc\AdapterConfig;
use Joandysson\HyperfOidc\ConfigProvider;
use Joandysson\HyperfOidc\Exceptions\DiscoveryException;
use Joandysson\HyperfOidc\Exceptions\InvalidConfigException;
use Joandysson\HyperfOidc\Exceptions\InvalidResponseException;
use Joandysson\HyperfOidc\Exceptions\MissingTokenException;
use Joandysson\HyperfOidc\Exceptions\NotAuthenticatedException;
use Joandysson\HyperfOidc\Exceptions\NotAuthorizedException;
use Joandysson\HyperfOidc\Exceptions\OidcException;
use Joandysson\HyperfOidc\Oidc;
use Joandysson\HyperfOidc\OidcFactory;
use Joandysson\HyperfOidc\Utils\OidcAPI;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @internal
 */
class OidcTest extends TestCase
{
    private array $history = [];

    public function testBuildsLoginUrlWithStateAndAdditionalScope(): void
    {
        $oidc = $this->oidc();

        $parts = parse_url($oidc->getLoginUrl('state value', 'profile email'));
        parse_str($parts['query'] ?? '', $query);

        self::assertSame('https', $parts['scheme']);
        self::assertSame('idp.example.test', $parts['host']);
        self::assertSame('/authorize', $parts['path']);
        self::assertSame('client-app', $query['client_id']);
        self::assertSame('code', $query['response_type']);
        self::assertSame('https://app.example.test/callback', $query['redirect_uri']);
        self::assertSame('state value', $query['state']);
        self::assertSame('openid profile email', $query['scope']);
    }

    public function testBuildsLoginUrlWithoutOptionalState(): void
    {
        $parts = parse_url($this->oidc()->getLoginUrl());
        parse_str($parts['query'] ?? '', $query);

        self::assertArrayNotHasKey('state', $query);
        self::assertSame('openid', $query['scope']);
    }

    public function testExposesConfiguredClientAndProviderValues(): void
    {
        $oidc = $this->oidc();

        self::assertSame('https://idp.example.test', $oidc->getIssuer());
        self::assertSame('https://app.example.test/callback', $oidc->getRedirectUri());
        self::assertSame('client-app', $oidc->getClientId());
        self::assertSame('secret', $oidc->getClientSecret());
    }

    public function testEmptyAdditionalScopeResetsToDefaultScope(): void
    {
        $parts = parse_url($this->oidc()->getLoginUrl(scope: '  '));
        parse_str($parts['query'] ?? '', $query);

        self::assertSame('openid', $query['scope']);
    }

    public function testLoginUrlDoesNotKeepRequestStateBetweenCalls(): void
    {
        $oidc = $this->oidc();

        $firstParts = parse_url($oidc->getLoginUrl('first-state', 'profile', 'first-verifier'));
        parse_str($firstParts['query'] ?? '', $firstQuery);

        $secondParts = parse_url($oidc->getLoginUrl('second-state'));
        parse_str($secondParts['query'] ?? '', $secondQuery);

        self::assertSame('first-state', $firstQuery['state']);
        self::assertSame('openid profile', $firstQuery['scope']);
        self::assertArrayHasKey('code_challenge', $firstQuery);
        self::assertSame('second-state', $secondQuery['state']);
        self::assertSame('openid', $secondQuery['scope']);
        self::assertArrayNotHasKey('code_challenge', $secondQuery);
    }

    public function testSupportsAbsoluteConfiguredEndpoints(): void
    {
        $oidc = $this->oidc([
            'oidc' => [
                'providers' => [
                    'default' => [
                        'endpoints' => [
                            'authorization' => 'https://login.example.test/oauth2/auth',
                        ],
                    ],
                ],
            ],
        ]);

        self::assertStringStartsWith('https://login.example.test/oauth2/auth?', $oidc->getLoginUrl());
    }

    public function testRejectsInvalidRedirectUri(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Invalid redirect URI.');

        $this->adapterConfig([
            'oidc' => [
                'providers' => [
                    'default' => [
                        'redirect_uri' => 'invalid',
                    ],
                ],
            ],
        ]);
    }

    public function testRejectsInvalidIssuer(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Invalid OIDC issuer.');

        $this->adapterConfig([
            'oidc' => [
                'providers' => [
                    'default' => [
                        'issuer' => 'invalid',
                    ],
                ],
            ],
        ]);
    }

    public function testRejectsMissingDefaultProvider(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('OIDC provider "missing" is not configured.');

        $this->adapterConfig([
            'oidc' => [
                'default' => 'missing',
            ],
        ]);
    }

    public function testRejectsEmptyClientId(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Invalid OIDC client id.');

        $this->adapterConfig([
            'oidc' => [
                'providers' => [
                    'default' => [
                        'client_id' => '',
                    ],
                ],
            ],
        ]);
    }

    public function testRejectsEmptyScope(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Invalid OIDC scope.');

        $this->adapterConfig([
            'oidc' => [
                'providers' => [
                    'default' => [
                        'scope' => '',
                    ],
                ],
            ],
        ]);
    }

    public function testRejectsNegativeTimeout(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Invalid OIDC timeout.');

        $this->adapterConfig([
            'oidc' => [
                'providers' => [
                    'default' => [
                        'timeout' => -1,
                    ],
                ],
            ],
        ]);
    }

    public function testUsesConfiguredDefaultProvider(): void
    {
        $config = $this->adapterConfig([
            'oidc' => [
                'default' => 'secondary',
                'providers' => [
                    'secondary' => [
                        'issuer' => 'https://secondary.example.test',
                        'client_id' => 'secondary-client',
                        'client_secret' => null,
                        'redirect_uri' => 'https://secondary.example.test/callback',
                        'timeout' => 5,
                        'scope' => 'openid offline_access',
                    ],
                ],
            ],
        ]);

        self::assertSame('https://secondary.example.test', $config->issuer());
        self::assertSame('secondary-client', $config->clientId());
        self::assertNull($config->secret());
        self::assertSame(5.0, $config->timeout());
        self::assertSame('openid offline_access', $config->scope());
    }

    public function testDiscoveryEndpointsOverrideManualFallbacks(): void
    {
        $history = [];
        $config = $this->adapterConfig([
            'oidc' => [
                'providers' => [
                    'default' => [
                        'discovery' => ['enabled' => true],
                    ],
                ],
            ],
        ], $this->discoveryClient($history, [
            new Response(200, [], json_encode([
                'authorization_endpoint' => 'https://discovered.example.test/auth',
                'token_endpoint' => 'https://discovered.example.test/token',
                'introspection_endpoint' => 'https://discovered.example.test/introspect',
                'end_session_endpoint' => 'https://discovered.example.test/logout',
            ])),
        ]));

        self::assertSame('https://discovered.example.test/auth', $config->authorizationEndpoint());
        self::assertSame('https://discovered.example.test/token', $config->tokenEndpoint());
        self::assertSame('https://discovered.example.test/introspect', $config->introspectionEndpoint());
        self::assertSame('https://discovered.example.test/logout', $config->endSessionEndpoint());
        self::assertSame('/.well-known/openid-configuration', $history[0]['request']->getUri()->getPath());
    }

    public function testDiscoveryFailureFallsBackToConfiguredEndpoints(): void
    {
        $history = [];
        $config = $this->adapterConfig([
            'oidc' => [
                'providers' => [
                    'default' => [
                        'discovery' => ['enabled' => true],
                    ],
                ],
            ],
        ], $this->discoveryClient($history, [
            new Response(500, [], 'error'),
        ]));

        self::assertSame('https://idp.example.test/authorize', $config->authorizationEndpoint());
        self::assertSame('/.well-known/openid-configuration', $history[0]['request']->getUri()->getPath());
    }

    public function testDiscoveryCanUseCustomUrl(): void
    {
        $history = [];
        $config = $this->adapterConfig([
            'oidc' => [
                'providers' => [
                    'default' => [
                        'discovery' => [
                            'enabled' => true,
                            'url' => 'https://metadata.example.test/custom',
                        ],
                    ],
                ],
            ],
        ], $this->discoveryClient($history, [
            new Response(200, [], json_encode([
                'authorization_endpoint' => 'https://metadata.example.test/auth',
            ])),
        ]));

        self::assertSame('https://metadata.example.test/auth', $config->authorizationEndpoint());
        self::assertSame('metadata.example.test', $history[0]['request']->getUri()->getHost());
        self::assertSame('/custom', $history[0]['request']->getUri()->getPath());
    }

    public function testAuthorizationCodePostsTokenRequest(): void
    {
        $oidc = $this->oidcWithHttpHistory();

        $response = $oidc->authorizationCode('auth-code');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('/token', $this->history[0]['request']->getUri()->getPath());
        self::assertSame([
            'grant_type' => 'authorization_code',
            'code' => 'auth-code',
            'client_id' => 'client-app',
            'client_secret' => 'secret',
            'redirect_uri' => 'https://app.example.test/callback',
        ], $this->formParams($this->history[0]['request']));
    }

    public function testAuthorizationCodeCanSendPkceVerifier(): void
    {
        $oidc = $this->oidcWithHttpHistory();

        $oidc->authorizationCode('auth-code', 'code-verifier');

        self::assertSame([
            'grant_type' => 'authorization_code',
            'code' => 'auth-code',
            'code_verifier' => 'code-verifier',
            'client_id' => 'client-app',
            'client_secret' => 'secret',
            'redirect_uri' => 'https://app.example.test/callback',
        ], $this->formParams($this->history[0]['request']));
    }

    public function testPkceAddsChallengeToLoginUrlWithExplicitVerifier(): void
    {
        $oidc = $this->oidc();
        $verifier = $oidc->generateCodeVerifier('known-verifier');

        $parts = parse_url($oidc->getLoginUrl(codeVerifier: $verifier));
        parse_str($parts['query'] ?? '', $query);

        self::assertSame('known-verifier', $verifier);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertSame(
            rtrim(strtr(base64_encode(hash('sha256', 'known-verifier', true)), '+/', '-_'), '='),
            $query['code_challenge']
        );
    }

    public function testRefreshTokenPostsTokenRequestWithoutRedirectUri(): void
    {
        $oidc = $this->oidcWithHttpHistory();

        $oidc->authorizationToken('refresh-token');

        self::assertSame('/token', $this->history[0]['request']->getUri()->getPath());
        self::assertSame([
            'grant_type' => 'refresh_token',
            'refresh_token' => 'refresh-token',
            'client_id' => 'client-app',
            'client_secret' => 'secret',
        ], $this->formParams($this->history[0]['request']));
    }

    public function testPasswordGrantPostsScopeAndCredentials(): void
    {
        $oidc = $this->oidcWithHttpHistory();

        $oidc->authorizationLogin('user@example.test', 'password', 'profile');

        self::assertSame([
            'grant_type' => 'password',
            'username' => 'user@example.test',
            'password' => 'password',
            'scope' => 'openid profile',
            'client_id' => 'client-app',
            'client_secret' => 'secret',
        ], $this->formParams($this->history[0]['request']));
    }

    public function testClientCredentialsPostsScope(): void
    {
        $oidc = $this->oidcWithHttpHistory();

        $oidc->authorizationClientCredentials();

        self::assertSame([
            'grant_type' => 'client_credentials',
            'scope' => 'openid',
            'client_id' => 'client-app',
            'client_secret' => 'secret',
        ], $this->formParams($this->history[0]['request']));
    }

    public function testOauthErrorResponsesAreReturnedForParsing(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(400, [], '{"error":"invalid_grant"}'),
        ]));
        $stack->push(Middleware::history($history));

        $config = $this->adapterConfig();
        $api = new OidcAPI($config, new Client([
            'handler' => $stack,
            'base_uri' => $config->issuer(),
            'http_errors' => false,
        ]));
        $oidc = new Oidc($config, $api);

        $response = $oidc->authorizationClientCredentials();

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'invalid_grant'], $oidc->json($response));
    }

    public function testDefaultHttpClientDisablesHttpErrors(): void
    {
        $config = $this->adapterConfig();
        $api = new OidcAPI($config, new Client(['handler' => new MockHandler([])]));
        $method = new \ReflectionMethod(OidcAPI::class, 'clientConfig');

        $clientConfig = $method->invoke($api);

        self::assertIsArray($clientConfig);
        self::assertFalse($clientConfig['http_errors']);
    }

    public function testIntrospectionPostsTokenAndHint(): void
    {
        $oidc = $this->oidcWithHttpHistory();

        $oidc->introspect('access-token', 'access_token');

        self::assertSame('/introspect', $this->history[0]['request']->getUri()->getPath());
        self::assertSame([
            'token' => 'access-token',
            'token_type_hint' => 'access_token',
            'client_id' => 'client-app',
            'client_secret' => 'secret',
        ], $this->formParams($this->history[0]['request']));
    }

    public function testIntrospectionCanOmitTokenTypeHint(): void
    {
        $oidc = $this->oidcWithHttpHistory();

        $oidc->introspect('access-token');

        self::assertSame([
            'token' => 'access-token',
            'client_id' => 'client-app',
            'client_secret' => 'secret',
        ], $this->formParams($this->history[0]['request']));
    }

    public function testLogoutPostsRefreshToken(): void
    {
        $oidc = $this->oidcWithHttpHistory();

        $oidc->logout('refresh-token');

        self::assertSame('/logout', $this->history[0]['request']->getUri()->getPath());
        self::assertSame([
            'refresh_token' => 'refresh-token',
            'client_id' => 'client-app',
            'client_secret' => 'secret',
        ], $this->formParams($this->history[0]['request']));
    }

    public function testPublicClientOmitsClientSecret(): void
    {
        $oidc = $this->oidcWithHttpHistory([
            'oidc' => [
                'providers' => [
                    'default' => [
                        'client_secret' => null,
                    ],
                ],
            ],
        ]);

        $oidc->authorizationClientCredentials();

        self::assertSame([
            'grant_type' => 'client_credentials',
            'scope' => 'openid',
            'client_id' => 'client-app',
        ], $this->formParams($this->history[0]['request']));
    }

    public function testFactoryCreatesOidcForSelectedProvider(): void
    {
        $factory = new OidcFactory(new Config(array_replace_recursive($this->defaultConfig(), [
            'oidc' => [
                'providers' => [
                    'secondary' => [
                        'issuer' => 'https://secondary.example.test',
                        'client_id' => 'secondary-client',
                        'client_secret' => 'secondary-secret',
                        'redirect_uri' => 'https://secondary.example.test/callback',
                        'timeout' => 5,
                        'scope' => 'openid',
                        'discovery' => ['enabled' => false],
                        'endpoints' => [
                            'authorization' => '/oauth2/authorize',
                            'token' => '/oauth2/token',
                            'introspection' => '/oauth2/introspect',
                            'end_session' => '/oauth2/logout',
                        ],
                    ],
                ],
            ],
        ])));

        $oidc = $factory->forProvider('secondary');

        self::assertSame('https://secondary.example.test', $oidc->getIssuer());
        self::assertSame('secondary-client', $oidc->getClientId());
        self::assertStringStartsWith('https://secondary.example.test/oauth2/authorize?', $oidc->getLoginUrl());
    }

    public function testJsonHelperRejectsInvalidJson(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessage('Invalid OIDC JSON response.');

        $this->oidc()->json(new Response(200, [], 'invalid-json'));
    }

    public function testTokenPayloadRejectsMissingAccessToken(): void
    {
        $this->expectException(MissingTokenException::class);
        $this->expectExceptionMessage('OIDC access token missing.');

        $this->oidc()->tokenPayload(new Response(200, [], '{"refresh_token":"refresh"}'));
    }

    public function testTokenPayloadReturnsValidJsonPayload(): void
    {
        $payload = $this->oidc()->tokenPayload(new Response(200, [], '{"access_token":"access"}'));

        self::assertSame(['access_token' => 'access'], $payload);
    }

    public function testConfigProviderPublishesOidcConfig(): void
    {
        if (! defined('BASE_PATH')) {
            define('BASE_PATH', '/app');
        }

        $config = (new ConfigProvider())();

        self::assertSame([dirname(__DIR__) . '/src'], $config['annotations']['scan']['paths']);
        self::assertSame('config', $config['publish'][0]['id']);
        self::assertSame('The config for OIDC adapter.', $config['publish'][0]['description']);
        self::assertSame(dirname(__DIR__) . '/src/../publish/oidc.php', $config['publish'][0]['source']);
        self::assertSame('/app/config/autoload/oidc.php', $config['publish'][0]['destination']);
    }

    public function testPublishedConfigCanBeLoadedWithHyperfEnvFunction(): void
    {
        putenv('OIDC_ISSUER=https://idp.example.test');
        putenv('OIDC_CLIENT_ID=client-app');
        putenv('OIDC_CLIENT_SECRET=secret');
        putenv('OIDC_REDIRECT_URI=https://app.example.test/callback');

        $config = require dirname(__DIR__) . '/publish/oidc.php';

        self::assertSame('default', $config['default']);
        self::assertSame('https://idp.example.test', $config['providers']['default']['issuer']);
        self::assertSame('client-app', $config['providers']['default']['client_id']);
        self::assertSame('secret', $config['providers']['default']['client_secret']);
        self::assertSame('https://app.example.test/callback', $config['providers']['default']['redirect_uri']);

        putenv('OIDC_ISSUER');
        putenv('OIDC_CLIENT_ID');
        putenv('OIDC_CLIENT_SECRET');
        putenv('OIDC_REDIRECT_URI');
    }

    public function testDomainExceptionsCanBeInstantiated(): void
    {
        self::assertSame('auth', (new NotAuthenticatedException('auth'))->getMessage());
        self::assertSame('permission', (new NotAuthorizedException('permission'))->getMessage());
    }

    public function testSpecificExceptionsExtendOidcException(): void
    {
        self::assertInstanceOf(OidcException::class, new InvalidConfigException());
        self::assertInstanceOf(OidcException::class, new InvalidResponseException());
        self::assertInstanceOf(OidcException::class, new MissingTokenException());
        self::assertInstanceOf(OidcException::class, new DiscoveryException());
    }

    private function oidc(array $overrides = []): Oidc
    {
        $config = $this->adapterConfig($overrides);
        $history = [];

        return new Oidc($config, new OidcAPI($config, $this->httpClient($history)));
    }

    private function oidcWithHttpHistory(array $overrides = []): Oidc
    {
        $this->history = [];
        $config = $this->adapterConfig($overrides);

        return new Oidc($config, new OidcAPI($config, $this->httpClient($this->history)));
    }

    private function adapterConfig(array $overrides = [], ?Client $discoveryClient = null): AdapterConfig
    {
        return new AdapterConfig(new Config(array_replace_recursive($this->defaultConfig(), $overrides)), discoveryClient: $discoveryClient);
    }

    private function httpClient(array &$history): Client
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"ok":true}'),
        ]));
        $stack->push(Middleware::history($history));

        return new Client([
            'handler' => $stack,
            'base_uri' => 'https://idp.example.test',
            'http_errors' => false,
        ]);
    }

    private function discoveryClient(array &$history, array $responses): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new Client([
            'handler' => $stack,
            'http_errors' => false,
        ]);
    }

    private function formParams(RequestInterface $request): array
    {
        parse_str((string) $request->getBody(), $params);

        return $params;
    }

    private function defaultConfig(): array
    {
        return [
            'oidc' => [
                'default' => 'default',
                'providers' => [
                    'default' => [
                        'issuer' => 'https://idp.example.test',
                        'client_id' => 'client-app',
                        'client_secret' => 'secret',
                        'redirect_uri' => 'https://app.example.test/callback',
                        'timeout' => 0,
                        'scope' => 'openid',
                        'discovery' => [
                            'enabled' => false,
                            'url' => null,
                        ],
                        'endpoints' => [
                            'authorization' => '/authorize',
                            'token' => '/token',
                            'introspection' => '/introspect',
                            'end_session' => '/logout',
                        ],
                    ],
                ],
            ],
        ];
    }
}
