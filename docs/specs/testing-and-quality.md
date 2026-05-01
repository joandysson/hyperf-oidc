# Testing And Quality

## Summary

The project is tested with PHPUnit and checked with PHPStan. Coverage must stay at or above 90% statement coverage.

## Required Commands

Run these before submitting changes:

```bash
composer validate --strict
composer test
composer analyse
composer test-coverage
```

`composer test-coverage` requires a coverage driver such as Xdebug.

## Current Test Style

Tests use:

- `Hyperf\Config\Config` for in-memory configuration;
- Guzzle `MockHandler` for HTTP responses;
- Guzzle history middleware for asserting outgoing requests;
- PHPUnit assertions against request paths, form parameters, response parsing, and exceptions.

Keep tests close to package behavior. Do not require a real OIDC provider for normal automated test runs.

## Scenarios To Preserve

Tests should continue covering:

- login URL construction with state and scopes;
- login URL construction without optional state;
- PKCE challenge generation and verifier handling;
- authorization code payloads with and without PKCE;
- refresh token payloads;
- password grant payloads;
- client credentials payloads;
- introspection with and without token type hint;
- logout payloads;
- public clients without `client_secret`;
- multiple providers through `OidcFactory`;
- discovery success, fallback, and custom discovery URL;
- invalid provider configuration;
- invalid JSON responses;
- missing access token responses;
- Hyperf config publishing metadata;
- package exception hierarchy.

## Adding Tests

Add or update tests when changing:

- public methods on `Oidc`;
- config shape or validation behavior;
- endpoint resolution;
- request payloads;
- exception behavior;
- publishing metadata;
- Composer scripts or quality gates.

Prefer focused unit tests with mocked HTTP. Temporary end-to-end experiments with Docker or a real provider may be useful for analysis, but they should not become required automated tests unless the project explicitly adds integration test infrastructure.
