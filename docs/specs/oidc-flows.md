# OIDC Flows

## Summary

`Oidc` exposes the high-level operations used by Hyperf applications. All network flow methods return `Psr\Http\Message\ResponseInterface`.

Use `json()` to parse a JSON response and `tokenPayload()` when the response must contain a non-empty `access_token`.

## Authorization URL

`getLoginUrl()` builds an authorization URL using:

- `client_id`;
- `response_type=code`;
- `redirect_uri`;
- configured scope;
- optional `state`;
- optional PKCE challenge.

`getLoginUrl($state, $scope, $codeVerifier)` receives request-specific values as arguments. The `scope` argument appends extra scopes to the configured default scope. Passing an empty, null or whitespace-only scope uses the configured default.

The consuming application must generate, store, and validate `state`. The adapter must not store `state`, extra scopes or PKCE verifiers on the service instance because Hyperf applications may reuse container services across concurrent requests.

## Authorization Code

`authorizationCode($code, $codeVerifier = null)` posts to the token endpoint with:

- `grant_type=authorization_code`;
- `code`;
- `redirect_uri`;
- `client_id`;
- optional `client_secret`;
- optional `code_verifier`.

If PKCE is used, the application must pass the verifier explicitly.

## PKCE

`generateCodeVerifier($codeVerifier = null)` returns the supplied verifier or generates a URL-safe verifier.

Pass the verifier to `getLoginUrl()` to include:

- `code_challenge`;
- `code_challenge_method=S256`.

The application must store the verifier between the authorization redirect and callback.

## Refresh Token

`authorizationToken($refreshToken)` posts to the token endpoint with:

- `grant_type=refresh_token`;
- `refresh_token`;
- `client_id`;
- optional `client_secret`.

The request does not include `redirect_uri`.

## Password Grant

`authorizationLogin($username, $password)` posts to the token endpoint with:

- `grant_type=password`;
- `username`;
- `password`;
- current scope;
- `client_id`;
- optional `client_secret`.

Only use this flow when the provider and application security model explicitly allow it.

## Client Credentials

`authorizationClientCredentials()` posts to the token endpoint with:

- `grant_type=client_credentials`;
- current scope;
- `client_id`;
- optional `client_secret`.

This flow is intended for service-to-service authentication.

## Introspection

`introspect($token, $tokenTypeHint = null)` posts to the introspection endpoint with:

- `token`;
- optional `token_type_hint`;
- `client_id`;
- optional `client_secret`.

Applications should reject tokens when the parsed introspection payload does not include `active=true`.

## Logout

`logout($refreshToken)` posts to the end-session endpoint with:

- `refresh_token`;
- `client_id`;
- optional `client_secret`.

Providers may return different success status codes. The adapter returns the raw response so the application can decide how to handle it.
