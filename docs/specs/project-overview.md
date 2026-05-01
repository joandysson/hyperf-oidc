# Project Overview

## Summary

`joandysson/hyperf-oidc` is a Composer library that provides a generic OpenID Connect adapter for Hyperf applications.

The package handles common OIDC/OAuth2 client operations:

- build an authorization URL;
- exchange an authorization code for tokens;
- exchange a refresh token;
- use password grant when a provider allows it;
- use client credentials;
- introspect tokens;
- logout with a refresh token.

## Audience

The package is intended for Hyperf application developers who need to integrate with an OIDC provider without adding provider-specific code to their application layer.

Keycloak is a valid target provider, but the package must remain usable with any compliant OIDC provider.

## Non-Goals

- Do not provide provider administration APIs.
- Do not manage users, realms, tenants, clients, or provider-specific resources.
- Do not validate application callback `state`; the consuming application must do that before exchanging the authorization code.
- Do not decode, verify, or authorize JWT claims. Token validation and authorization policy belong to the consuming application unless a future task explicitly adds that feature.

## Runtime And Tooling

- PHP 8.1 or newer.
- Hyperf 3.1 components.
- Guzzle 7 for HTTP transport.
- PHPUnit 10.5 for tests.
- PHPStan for static analysis.
- PHP-CS-Fixer for formatting.

## Distribution

The package is distributed as a Composer library named `joandysson/hyperf-oidc`.

It provides a Hyperf `ConfigProvider` so applications can publish `config/autoload/oidc.php` with:

```bash
php bin/hyperf.php vendor:publish joandysson/hyperf-oidc
```
