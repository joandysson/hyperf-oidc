# Hyperf OIDC

Generic OpenID Connect adapter for Hyperf applications.

You can use it to integrate login with any compatible OIDC/OAuth2 provider, including Google, Facebook, GitHub, Microsoft Entra ID, Keycloak, Auth0, Ory Hydra and other providers. Each provider still needs its own client credentials, redirect URI and supported endpoint configuration.

The package handles the common OIDC/OAuth2 calls your application needs:

- build the authorization URL;
- exchange an authorization code for tokens;
- refresh tokens;
- use password grant when your provider allows it;
- use client credentials;
- introspect tokens;
- logout with a refresh token.

It does not include provider-specific admin APIs. User creation, realm management and provider administration should be handled by each provider's own SDK or API.

## Requirements

- PHP 8.1 or newer
- Hyperf 3.1
- Composer

## Install

```bash
composer require joandysson/hyperf-oidc
```

## Publish Config

```bash
php bin/hyperf.php vendor:publish joandysson/hyperf-oidc
```

The published file is:

```text
config/autoload/oidc.php
```

Published config:

```php
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
```

## Configuration Reference

| Key | Default | Description |
| --- | --- | --- |
| `default` | `default` | Provider name used when `Oidc::class` is resolved directly from the Hyperf container. |
| `providers` | `default` provider | Map of named provider configurations. Add more entries when the same app needs multiple OIDC providers. |
| `providers.*.issuer` | `OIDC_ISSUER` | Base issuer URL for the provider. For Keycloak, this is usually the realm URL, such as `https://idp.example.com/realms/my-realm`. |
| `providers.*.client_id` | `OIDC_CLIENT_ID` | OAuth/OIDC client identifier registered in the provider. |
| `providers.*.client_secret` | `OIDC_CLIENT_SECRET` | OAuth/OIDC client secret. Set to `null` or an empty value for public clients. |
| `providers.*.redirect_uri` | `OIDC_REDIRECT_URI` | Callback URL registered in the provider and used by the authorization code flow. |
| `providers.*.timeout` | `0` | Guzzle request timeout in seconds. `0` keeps Guzzle's default no-timeout behavior. |
| `providers.*.scope` | `openid` | Default scopes sent in authorization, password and client credentials flows. Extra scopes passed to methods are appended to this value. |
| `providers.*.discovery.enabled` | `true` | Enables OIDC Discovery through the provider metadata document. |
| `providers.*.discovery.url` | `null` | Optional custom discovery URL. When empty, the adapter uses `{issuer}/.well-known/openid-configuration`. |
| `providers.*.endpoints.authorization` | `/authorize` | Manual authorization endpoint fallback used to build login URLs when discovery is disabled or does not provide `authorization_endpoint`. |
| `providers.*.endpoints.token` | `/token` | Manual token endpoint fallback used for authorization code, refresh token, password and client credentials flows. |
| `providers.*.endpoints.introspection` | `/introspect` | Manual introspection endpoint fallback used by `introspect()`. |
| `providers.*.endpoints.end_session` | `/logout` | Manual logout/end-session endpoint fallback used by `logout()`. |

## Environment

For a generic provider whose endpoints are directly under the issuer:

```dotenv
OIDC_ISSUER=https://idp.example.com
OIDC_CLIENT_ID=my-client
OIDC_CLIENT_SECRET=my-secret
OIDC_REDIRECT_URI=https://app.example.com/auth/callback
OIDC_SCOPE="openid profile email"
OIDC_TIMEOUT=5
OIDC_DISCOVERY_ENABLED=true
```

For Keycloak:

```dotenv
OIDC_ISSUER=http://localhost:8080/realms/my-realm
OIDC_CLIENT_ID=my-client
OIDC_CLIENT_SECRET=my-secret
OIDC_REDIRECT_URI=http://localhost:9501/auth/callback
OIDC_SCOPE="openid profile email"
OIDC_TIMEOUT=5
OIDC_DISCOVERY_ENABLED=true
```

When discovery is enabled, the adapter reads endpoints from `OIDC_ISSUER/.well-known/openid-configuration`. If discovery fails or the provider does not expose a specific endpoint, the manual endpoint values are used as fallback. If an endpoint value is an absolute URL, it is used as-is. If it is a relative path, it is appended to `OIDC_ISSUER`.

For providers where discovery is disabled or unavailable, configure endpoints manually:

```dotenv
OIDC_DISCOVERY_ENABLED=false
OIDC_AUTHORIZATION_ENDPOINT=/protocol/openid-connect/auth
OIDC_TOKEN_ENDPOINT=/protocol/openid-connect/token
OIDC_INTROSPECTION_ENDPOINT=/protocol/openid-connect/token/introspect
OIDC_END_SESSION_ENDPOINT=/protocol/openid-connect/logout
```

## Usage

Resolve the adapter from the Hyperf container:

```php
use Joandysson\HyperfOidc\Oidc;

use function Hyperf\Support\make;

$oidc = make(Oidc::class);
```

### Login URL

Use this URL to redirect the user to the provider login page.

```php
$state = bin2hex(random_bytes(16));

// Store $state in the user's session before redirecting.

$loginUrl = $oidc->getLoginUrl(
    state: $state,
    scope: 'profile email',
);
```

The `scope` argument appends scopes to the configured default scope. If `OIDC_SCOPE` is `openid`, passing `scope: 'profile email'` sends `openid profile email`.

Request-specific values such as `state`, extra scopes and PKCE verifiers are passed as method arguments. The adapter does not store them on the service instance, so it remains safe when resolved as a long-lived container service.

### PKCE

PKCE is optional and can be enabled per authorization request:

```php
$codeVerifier = $oidc->generateCodeVerifier();

// Store $codeVerifier in the user's session before redirecting.

$loginUrl = $oidc->getLoginUrl(
    state: $state,
    scope: 'profile email',
    codeVerifier: $codeVerifier,
);
```

Store `$codeVerifier` in the user's session. The login URL will include `code_challenge` and `code_challenge_method=S256`.

### Authorization Callback

After login, your provider redirects back to `OIDC_REDIRECT_URI` with a `code`.

```php
$code = $request->input('code');
$state = $request->input('state');

// Validate $state against the value stored in your session before using $code.

$response = $oidc->authorizationCode($code);
$tokens = $oidc->tokenPayload($response);

$accessToken = $tokens['access_token'] ?? null;
$refreshToken = $tokens['refresh_token'] ?? null;
$idToken = $tokens['id_token'] ?? null;
```

If PKCE was used, pass the stored verifier:

```php
$response = $oidc->authorizationCode($code, $codeVerifierFromSession);
$tokens = $oidc->tokenPayload($response);
```

### Refresh Token

```php
$response = $oidc->authorizationToken($refreshToken);
$tokens = $oidc->tokenPayload($response);
```

### Password Grant

Only use this flow when your provider and security model explicitly allow it.

```php
$response = $oidc->authorizationLogin('user@example.com', 'password');
$tokens = $oidc->tokenPayload($response);
```

### Client Credentials

Use this flow for service-to-service authentication.

```php
$response = $oidc->authorizationClientCredentials();
$tokens = $oidc->tokenPayload($response);
```

### Token Introspection

```php
$response = $oidc->introspect($accessToken, 'access_token');
$introspection = $oidc->json($response);

if (($introspection['active'] ?? false) !== true) {
    // Reject the token.
}
```

The second argument is optional:

```php
$response = $oidc->introspect($accessToken);
```

### Logout

```php
$response = $oidc->logout($refreshToken);
```

Most providers invalidate the session/token and return `200` or `204`.

## Multiple Providers

You can configure more than one provider and choose the default one:

```php
return [
    'default' => 'internal',
    'providers' => [
        'internal' => [
            'issuer' => env('OIDC_INTERNAL_ISSUER'),
            'client_id' => env('OIDC_INTERNAL_CLIENT_ID'),
            'client_secret' => env('OIDC_INTERNAL_CLIENT_SECRET'),
            'redirect_uri' => env('OIDC_INTERNAL_REDIRECT_URI'),
            'timeout' => 5,
            'scope' => 'openid profile email',
            'discovery' => [
                'enabled' => true,
                'url' => null,
            ],
            'endpoints' => [
                'authorization' => '/authorize',
                'token' => '/token',
                'introspection' => '/introspect',
                'end_session' => '/logout',
            ],
        ],
        'keycloak' => [
            'issuer' => env('KEYCLOAK_ISSUER'),
            'client_id' => env('KEYCLOAK_CLIENT_ID'),
            'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
            'redirect_uri' => env('KEYCLOAK_REDIRECT_URI'),
            'timeout' => 5,
            'scope' => 'openid profile email',
            'discovery' => [
                'enabled' => true,
                'url' => null,
            ],
            'endpoints' => [
                'authorization' => '/protocol/openid-connect/auth',
                'token' => '/protocol/openid-connect/token',
                'introspection' => '/protocol/openid-connect/token/introspect',
                'end_session' => '/protocol/openid-connect/logout',
            ],
        ],
    ],
];
```

The default adapter instance uses the configured `default` provider. To use a specific provider at runtime, use the factory:

```php
use Joandysson\HyperfOidc\OidcFactory;

use function Hyperf\Support\make;

$oidc = make(OidcFactory::class)->forProvider('keycloak');
```

## Public Clients

If your provider uses a public client, set `client_secret` to `null`:

```php
'client_secret' => null,
```

When the secret is `null` or empty, the adapter omits `client_secret` from token, introspection and logout requests.

## Returned Responses

All network methods return `Psr\Http\Message\ResponseInterface`.

```php
$response = $oidc->authorizationClientCredentials();

$status = $response->getStatusCode();
$payload = $oidc->json($response);
```

Use `tokenPayload()` when the response must contain `access_token`:

```php
$tokens = $oidc->tokenPayload($response);
```

## Exceptions

All package-specific exceptions extend `Joandysson\HyperfOidc\Exceptions\OidcException`.

```php
use GuzzleHttp\Exception\GuzzleException;
use Joandysson\HyperfOidc\Exceptions\InvalidConfigException;
use Joandysson\HyperfOidc\Exceptions\InvalidResponseException;
use Joandysson\HyperfOidc\Exceptions\MissingTokenException;
use Joandysson\HyperfOidc\Exceptions\OidcException;

try {
    $response = $oidc->authorizationClientCredentials();
    $tokens = $oidc->tokenPayload($response);
} catch (InvalidConfigException $exception) {
    // Provider config is missing or invalid.
} catch (InvalidResponseException $exception) {
    // Provider returned invalid JSON or an unexpected payload shape.
} catch (MissingTokenException $exception) {
    // Token response did not include access_token.
} catch (OidcException $exception) {
    // Any other package-specific OIDC error.
} catch (GuzzleException $exception) {
    // Transport-level failure from Guzzle.
}
```

Current exception types:

- `InvalidConfigException`: invalid provider config, missing provider, invalid endpoints.
- `InvalidResponseException`: invalid JSON or non-object JSON payload.
- `MissingTokenException`: token payload without a valid `access_token`.
- `DiscoveryException`: reserved for strict discovery flows; discovery currently falls back silently.

Guzzle exceptions may still be thrown for transport-level failures.

## Testing

Install dependencies:

```bash
composer install
```

Run unit tests:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Run coverage. This requires a coverage driver such as Xdebug:

```bash
composer test-coverage
```

The coverage script fails when statement coverage is below 90%.

## Notes

- Always validate the `state` value in your callback route before exchanging the authorization code.
- Use HTTPS in production for your issuer and redirect URI.
- Keep provider-specific admin APIs outside this package.
- Configure Keycloak-specific endpoint paths explicitly; generic defaults are intentionally provider-neutral.
