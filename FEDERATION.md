# Federation

This document follows [FEP-67ff](https://codeberg.org/fediverse/fep/src/branch/main/fep/67ff/fep-67ff.md)
and describes how Convoro federates over [ActivityPub](https://www.w3.org/TR/activitypub/).

Federation is **off by default** and enabled per-community by an admin
(Admin → Settings → Federation). Once enabled the community becomes a single
followable actor and each member can optionally federate as their own actor.

## Supported protocols & discovery

- **ActivityPub** (S2S) — the core protocol for actors, activities and objects.
- **WebFinger** (`/.well-known/webfinger`) — resolves `acct:user@host` to an
  actor, for both the community and individual members.
- **NodeInfo 2.0** ([FEP-f1d5](https://codeberg.org/fediverse/fep/src/branch/main/fep/f1d5/fep-f1d5.md))
  at `/.well-known/nodeinfo` → `/nodeinfo/2.0`.
- **HTTP Signatures** (draft-cavage, `rsa-sha256`) on every inbound and outbound
  request, including a SHA-256 `Digest`. Signed `GET`s are used to fetch remote
  actors ("authorized fetch").

## Actors

- **Community** — a single `Group` actor
  ([FEP-1b12](https://codeberg.org/fediverse/fep/src/branch/main/fep/1b12/fep-1b12.md)).
  Following it delivers every new topic to your inbox as an `Announce`.
- **Members** — each non-system member may federate as a `Person` actor at
  `/u/{id}/actor`, discoverable as `@{username}@{host}`.

Each actor publishes:

- `inbox`, `outbox`, `followers`, `preferredUsername`, `name`, `summary`, `url`,
  `icon`, `manuallyApprovesFollowers: false`, `discoverable: true`.
- A legacy `publicKey` (RSA-2048, `publicKeyPem`) **and** a FEP-521a
  `assertionMethod` `Multikey` ([FEP-521a](https://codeberg.org/fediverse/fep/src/branch/main/fep/521a/fep-521a.md))
  — the same key in both representations.
- A `schema:PropertyValue` `attachment` row (Mastodon profile metadata).

## Activities

**Outbound**

- `Create` (`Note`) — new topics and replies, attributed to the member author.
- `Announce` — the community boosts each new topic's `Note` to its followers,
  preserving the original author's attribution.
- `Accept` — sent in response to an inbound `Follow`.

**Inbound** (to `/federation/inbox` or `/u/{id}/inbox`)

- `Follow` / `Undo`(Follow) — add/remove a follower; a signed `Accept` is returned.
- `Create` (`Note` with `inReplyTo`) — imported as a reply to the topic.
- `Like` — recorded as a reaction on the topic's first post.
- `Delete` — removes a previously imported remote reply.

## Objects

`Note` objects carry `attributedTo`, `to`/`cc` (`as:Public` + followers),
`content`, `url`, `published`, `inReplyTo` (replies), and `tag` `Hashtag`s
derived from the topic's tags. Topics are also dereferenceable as a `Note` via
content negotiation on `GET /t/{slug}`.

## JSON-LD `@context`

Documents use `https://www.w3.org/ns/activitystreams`,
`https://w3id.org/security/v1`, and an inline extension object defining the
Mastodon/FEP terms we emit (`Hashtag`, `sensitive`, `discoverable`,
`manuallyApprovesFollowers`, `PropertyValue`, `Multikey`, `assertionMethod`,
`publicKeyMultibase`).

## Implemented FEPs

- **FEP-67ff** — this document.
- **FEP-f1d5** — NodeInfo.
- **FEP-1b12** — `Group` actor federation.
- **FEP-521a** — actor public keys as a `Multikey` (`assertionMethod`).

## Not yet implemented

- `sharedInbox` advertising (per-actor inboxes only).
- Outbound `Update`/`Delete`/`Like`; `following` and `featured` collections.
- Object integrity proofs (FEP-8b32) — integrity is provided at the transport
  layer by HTTP Signatures.
- FEP-8fcf followers-collection synchronization.

## Threat model / security notes

Outbound requests are SSRF-guarded (private-range and DNS-rebinding defenses).
Private keys are stored server-side. Inbound activities are rejected unless the
HTTP Signature verifies against the signing actor's published key and the
`actor` matches the signer.
