# 7. Blobs are served to anyone who asks

## Status

Accepted

## Context

`innis/nostr-blossom` offers three read policies. `GetAccessPolicy::Public` serves any blob to any caller, `Authenticated` requires a valid authorisation event on a read, and `Tenant` restricts reads to the server's own tenants; a decorator narrows it further to blobs the caller uploaded. This server constructs its policy with the default and exposes no configuration key for the others, so every deployment serves reads to anyone.

That reads like an oversight. A server offering three modes and a host silently picking one is exactly the shape of a forgotten wiring, which is why this record exists.

It is not an oversight, because of what a Blossom server is for. A blob is addressed by the hash of its contents and published by putting its URL into a Nostr event. The overwhelming case is a user posting a picture, a video or a file attachment to the network, where the point is that every client that renders that event can fetch the blob. Those clients are arbitrary software belonging to arbitrary people, holding no credential from this server and having no reason to authenticate to it. A read policy that asks them to authenticate does not protect the content; it stops the content being displayed, which is the one thing the upload was for.

Gating reads also protects less than it appears to. A blob's address is the hash of its bytes, so possession of the URL already implies someone was told the address. The URL travels inside a public event. Anyone who can read the event can read the blob, and on a relay-published event that is everyone. Requiring authentication at the blob server closes a door in a wall that has no other side.

Where a blob genuinely must stay private, the protection is encryption, not access control at the server. A gift-wrapped message carries its attachment encrypted, and the server holds ciphertext it could serve to the whole world without disclosing anything. That is the mechanism this stack already uses for private content, and it works regardless of who can fetch the bytes.

Writes are a different question and are gated. Only a tenant may upload or delete, which is what stops the server becoming free storage for strangers. Read and write are not symmetric here, and treating them as though they were is the mistake this record guards against.

## Decision

This server serves reads to anyone. It constructs the library's policy with `GetAccessPolicy::Public` and exposes no configuration key to change it.

Uploading and deleting stay restricted to configured tenants.

## Consequences

- A client rendering a Nostr event can fetch the blob it references without holding any credential from this server, which is the behaviour that makes published content work.
- An operator cannot make this server private. That is deliberate. Content that must not be readable is encrypted before it is uploaded, and the server never needs to know the difference.
- The library's `Authenticated` and `Tenant` read policies and its owner-only decorator are unreachable from this host. They remain in the library for a different kind of deployment; do not add a configuration key here without first deciding that this server is no longer serving a public network.
- Do not read the absence of a config key as a gap to fill. A future contributor finding three policies in the library and one in use here is looking at this decision, not at a missing feature.
- Rate limiting and the transport's own limits remain the defence against a reader who is merely expensive rather than unauthorised. Access control was never doing that job.
