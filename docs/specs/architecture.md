# Architecture

## Summary

The package is intentionally small. Configuration resolution, user-facing OIDC operations, and HTTP request construction are separated so each part can be tested independently.

## Main Components

- `Oidc` is the public adapter used by applications. It builds login URLs, enables PKCE, triggers token flows, introspection, logout, and parses token responses.
- `AdapterConfig` reads `oidc` configuration, validates required values, resolves discovery metadata, and normalizes endpoints.
- `OidcAPI` builds HTTP form requests and sends them through Guzzle.
- `OidcFactory` creates an `Oidc` instance for a named provider.
- `ConfigProvider` registers package scan paths and config publishing metadata for Hyperf.

## Data Flow

1. Hyperf loads `oidc` config from the published config file.
2. `AdapterConfig` selects the default provider or a provider requested by `OidcFactory`.
3. `AdapterConfig` validates issuer, redirect URI, client ID, scope, and timeout.
4. When discovery is enabled, `AdapterConfig` reads provider metadata from `/.well-known/openid-configuration` or a custom discovery URL.
5. `Oidc` builds user-facing flow parameters or delegates token, introspection, and logout requests to `OidcAPI`.
6. `OidcAPI` sends `application/x-www-form-urlencoded` requests to the resolved endpoints.

## Endpoint Resolution

Discovery metadata takes precedence when it is enabled and provides a valid endpoint value. If discovery fails or a metadata key is missing, manual endpoints from config are used.

Endpoint values can be absolute URLs or relative paths. Relative paths are appended to the configured issuer.

## Exception Policy

All package-specific exceptions extend `Joandysson\HyperfOidc\Exceptions\OidcException`.

Current package exceptions cover:

- invalid configuration;
- invalid JSON or unexpected response payload shape;
- missing access token in a token payload;
- authentication and authorization domain errors;
- reserved discovery-specific errors.

Guzzle transport failures are not wrapped today and may be thrown directly.

## Provider Boundary

Provider-specific admin behavior must not be added to this package. Provider-specific endpoint paths may appear in examples or user configuration, but class names, namespaces, package metadata, and public APIs must remain generic OIDC.
