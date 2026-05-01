# Configuration

## Summary

The package reads configuration from the `oidc` config key and supports one or more named providers.

The published config file is `config/autoload/oidc.php` in a Hyperf application.

## Config Shape

The required top-level keys are:

- `default`: provider name used when resolving `Oidc` directly.
- `providers`: map of provider names to provider configuration.

Each provider should define:

- `issuer`;
- `client_id`;
- `client_secret`;
- `redirect_uri`;
- `timeout`;
- `scope`;
- `discovery.enabled`;
- `discovery.url`;
- `endpoints.authorization`;
- `endpoints.token`;
- `endpoints.introspection`;
- `endpoints.end_session`.

## Validation Rules

- `issuer` must be a valid URL.
- `redirect_uri` must be a valid URL.
- `client_id` must not be empty.
- `scope` must not be empty.
- `timeout` must be zero or greater.
- the selected provider must exist.
- endpoint values must not resolve to an empty string.

## Discovery

When `discovery.enabled` is true, the adapter reads metadata from `discovery.url`.

If `discovery.url` is empty, the default URL is:

```text
{issuer}/.well-known/openid-configuration
```

Discovery can provide these metadata keys:

- `authorization_endpoint`;
- `token_endpoint`;
- `introspection_endpoint`;
- `end_session_endpoint`.

If discovery fails, returns a non-200 response, returns invalid metadata, or omits a key, the adapter falls back to the manual endpoint configured for that flow.

## Endpoint Values

Absolute endpoint values are used as-is.

Relative endpoint values are appended to the configured issuer:

```php
'issuer' => 'https://idp.example.test',
'endpoints' => [
    'token' => '/oauth2/token',
],
```

resolves to:

```text
https://idp.example.test/oauth2/token
```

## Multiple Providers

The default provider is used when the application resolves `Oidc` directly from the Hyperf container.

Use `OidcFactory::forProvider($name)` when the application must select a provider at runtime.

## Public Clients

If `client_secret` is `null` or an empty string, token, introspection, and logout requests omit `client_secret`.

This supports public clients and providers that do not require a client secret for a given flow.
