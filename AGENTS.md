# Agent Guide

This repository contains `joandysson/hyperf-oidc`, a generic OpenID Connect adapter for Hyperf applications.

Use this file as the first read before changing the project. The specs in `docs/specs/` provide the project context, architecture, configuration rules, OIDC flows, and quality expectations.

## Project Purpose

- Provide common OIDC/OAuth2 client operations for Hyperf applications.
- Stay provider-neutral. Keycloak is a supported OIDC provider, not the package identity.
- Keep provider administration APIs outside this package.
- Expose a Composer library with Hyperf config publishing support.

## Repository Structure

- `src/`: package source code.
- `src/Exceptions/`: package-specific exception hierarchy.
- `src/Utils/`: low-level grant constants and HTTP request helper.
- `publish/oidc.php`: config file published into Hyperf applications.
- `test/`: PHPUnit tests for configuration, flows, requests, exceptions, and publishing.
- `tools/check-coverage.php`: statement coverage gate used by `composer test-coverage`.
- `docs/specs/`: implementation and maintenance specs for agents and contributors.

## Development Rules

- Keep the package generic OIDC. Do not reintroduce Keycloak-only names, namespaces, config keys, or admin APIs.
- Preserve the public namespace `Joandysson\HyperfOidc`.
- Prefer small, focused changes that match the current class boundaries.
- Do not change generated or dependency files unless the task explicitly requires it.
- Do not commit runtime artifacts such as `.phpunit.cache/`, `build/`, or `vendor/`.
- Update README and specs when public behavior, configuration, or supported flows change.

## Validation Commands

Run the relevant checks before handing work back:

```bash
composer validate --strict
composer test
composer analyse
composer test-coverage
```

`composer test-coverage` requires a coverage driver such as Xdebug and fails when statement coverage is below 90%.

## Implementation Notes

- `Oidc` is the user-facing adapter.
- `AdapterConfig` resolves provider config, validates required values, reads OIDC discovery, and normalizes endpoints.
- `OidcAPI` performs HTTP form requests with Guzzle.
- `OidcFactory` creates an adapter for a named provider.
- `ConfigProvider` integrates the package with Hyperf publish and scan configuration.

## Pull Request Checklist

- The change keeps the adapter provider-neutral.
- Public configuration and usage docs are updated when behavior changes.
- Tests cover success and failure scenarios for changed behavior.
- Coverage remains at or above 90%.
- Static analysis passes.
- Composer metadata still validates.
