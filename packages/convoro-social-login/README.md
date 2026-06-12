# Social Login

Let members sign in to your Convoro community with **GitHub, Google, Facebook, or X**.

- **Dependency-free.** A small, self-contained OAuth2 client built on Laravel's
  HTTP client — no Composer packages, so it installs as a drop-in extension on
  any host (including shared hosting).
- **Configure from the admin.** Open **Admin → Marketplace → Social Login** and
  paste each provider's Client ID / Secret. The exact redirect URI to register
  with the provider is shown on the page. A provider's button only appears on
  the sign-in dialog once its keys are saved.
- **Safe account linking.** Returning users are matched by their provider id,
  then by a verified email; new accounts are created with a random password.
- **Private GitHub repos (optional).** Tick *Request access to private
  repositories* under GitHub so the store owner can list private repos in the
  extension store. The token is stored in Settings (`github.token`) — never on a
  user row.

## How it works

The extension registers `/auth/{provider}/redirect` and
`/auth/{provider}/callback` and renders its buttons into core's
`auth:providers` slot. X uses OAuth2 with PKCE; the others use the standard
authorization-code flow.

No access tokens are persisted to the `users` table.
