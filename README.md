# Loops Transit

Loops Transit is a self-hostable witness for account migrations between [Loops](https://joinloops.org) servers.

When an account moves, the old server signs a `MigrationIntent` and the new server signs a `MigrationReceipt`. Transit checks both signatures, records the fingerprint of each server's key, adds every step to a public append-only Merkle log and returns an Ed25519 seal. Transit never sees videos, profiles or any other content. A sealed move stays verifiable after either server rotates its keys or goes offline.

## How it works

1. The old server sends an `Offer` to the new server and submits the signed intent to Transit. Transit logs an `intent` entry and returns a signed entry with a timestamp.
2. The new server copies the account, signs a receipt and submits the intent and receipt to Transit. Transit logs a `seal` entry and returns the seal.
3. The new server puts the seal in its `Accept`. The old server verifies the seal against Transit's published keys before it finalizes the move.

Both servers must list the same Transit for a move to be witnessed. The protocol is described in the Account Migration section of the Loops `FEDERATION.md`.

## Requirements

- PHP 8.3 or newer with the `sodium`, `openssl` and PDO extensions
- Composer
- SQLite, MySQL, MariaDB or PostgreSQL
- Node.js to build the two public pages
- A public HTTPS hostname, for example `transit.example`

## Installation

```sh
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Set `APP_URL`, `TRANSIT_URL` and the operator details in `.env`, then:

```sh
php artisan migrate --force
php artisan transit:key:generate
npm install && npm run build
```

Add the scheduler to cron so stale intents get pruned:

```
* * * * * cd /path/to/transit && php artisan schedule:run >> /dev/null 2>&1
```

Back up the database and `APP_KEY` together. The signing key is stored in the database, encrypted with `APP_KEY`. If you lose either one you will need a new key, and seals made with the old key can only be checked if its public key is still in the database.

When using SQLite, `DB_JOURNAL_MODE=wal` lets the public pages and log reads run while entries are being added. Appends are serialized on every database driver.

If Transit runs behind a reverse proxy or load balancer, configure Laravel's trusted proxies so the per-IP rate limit sees real client addresses.

## Configuration

| Variable | Default | Purpose |
|---|---|---|
| `TRANSIT_URL` | `APP_URL` | The URL servers put in their intents. It must match exactly. |
| `TRANSIT_OPERATOR_NAME` | `APP_NAME` | Shown on the public pages and in discovery |
| `TRANSIT_OPERATOR_CONTACT` | | Contact address shown publicly |
| `TRANSIT_OPERATOR_URL` | | Link to the operator |
| `TRANSIT_REQUIRE_ADVERTISED` | `true` | Both servers must list this Transit in NodeInfo `metadata.loops.migration.transit` |
| `TRANSIT_ALLOWED_HOSTS` | | Comma separated list. When set, only these servers and their subdomains are accepted |
| `TRANSIT_BLOCKED_HOSTS` | | Comma separated list of servers that are refused, including subdomains |
| `TRANSIT_CLOCK_SKEW` | `300` | Seconds of clock difference tolerated |
| `TRANSIT_MAX_INTENT_DAYS` | `30` | Longest intent lifetime accepted |
| `TRANSIT_MAX_RECEIPTS` | `10` | Receipts that can be sealed for one intent |
| `TRANSIT_RATE_LIMIT_IP` | `60` | Submissions per minute per IP address |
| `TRANSIT_RATE_LIMIT_HOST` | `120` | Submissions per minute per server |
| `TRANSIT_PRUNE_AFTER_DAYS` | `90` | Days after expiry before an unsealed intent is deleted |

## Connecting Loops servers

In the Loops admin dashboard, open the migration settings and add your Transit URL to the Transit list, written exactly as `TRANSIT_URL`. Loops then lists it in NodeInfo. For each move, the old server uses the first Transit in its own list that the new server also lists. Admins can also require a seal for every migration.

## Commands

| Command | Purpose |
|---|---|
| `php artisan transit:key:generate` | Create the first signing key |
| `php artisan transit:key:rotate` | Retire the active key and create a new one. Retired public keys stay published so existing seals keep verifying, and their secret keys are deleted |
| `php artisan transit:log:verify` | Recompute the whole log from its entries and check every stored node and signed tree head |
| `php artisan transit:prune` | Delete intents that expired without a seal. Their log entries are kept |

## API

Errors use one format, with a machine readable `error` code:

```json
{ "error": "wrong_transit", "message": "The intent names a different Transit" }
```

### `GET /.well-known/loops-transit`

```json
{
  "version": 1,
  "software": { "name": "loops-transit", "version": "1.0.0" },
  "transit": "https://transit.example",
  "operator": { "name": "Example Transit", "contact": "admin@transit.example" },
  "keys": [
    { "kid": "6kn10-f3bN1m...", "kty": "OKP", "crv": "Ed25519", "x": "...", "alg": "EdDSA", "use": "sig", "status": "active", "created": "2026-11-18T10:00:00Z" }
  ],
  "endpoints": {
    "intents": "https://transit.example/api/v1/intents",
    "receipts": "https://transit.example/api/v1/receipts",
    "log": "https://transit.example/api/v1/log",
    "migrations": "https://transit.example/api/v1/migrations"
  },
  "log": { "size": 42, "root": "sha256:..." },
  "policy": { "requireAdvertised": true, "restricted": false, "maxIntentDays": 30, "maxReceipts": 10 }
}
```

The `kid` is the RFC 7638 thumbprint of the key.

### `POST /api/v1/intents`

Body: `{"intent": "<JWS>"}`. Returns `201` for a new intent and `200` when the same intent was already logged.

```json
{
  "entry": {
    "jws": "<EdDSA JWS, type TransitEntry>",
    "index": 41,
    "size": 42,
    "root": "sha256:...",
    "intentHash": "sha256:...",
    "received": "2026-11-18T10:05:00Z"
  }
}
```

### `POST /api/v1/receipts`

Body: `{"intent": "<JWS>", "receipt": "<JWS>"}`. Returns `201` with a new seal, or `200` with the stored seal when the same receipt is sent again. A different receipt for the same intent, for example after a retry, is sealed too and replaces the previous one. Both stay in the log.

```json
{
  "seal": {
    "jws": "<EdDSA JWS, type TransitSeal>",
    "kid": "6kn10-f3bN1m...",
    "index": 42,
    "size": 43,
    "root": "sha256:...",
    "receiptHash": "sha256:...",
    "sealed": "2026-11-18T10:20:00Z"
  }
}
```

The seal payload:

```json
{
  "type": "TransitSeal",
  "version": 1,
  "transit": "https://transit.example",
  "intent": "https://old.example/ap/migrations/9b1e...",
  "intentHash": "sha256:<hash of the intent JWS>",
  "receiptHash": "sha256:<hash of the receipt JWS>",
  "subject": "https://old.example/ap/users/123",
  "target": "https://new.example/ap/users/456",
  "source": "old.example",
  "destination": "new.example",
  "manifestRoot": "sha256:...",
  "mapRoot": "sha256:...",
  "keys": { "source": "sha256:<key fingerprint>", "destination": "sha256:<key fingerprint>" },
  "entry": 41,
  "log": { "index": 42, "size": 43, "root": "sha256:..." },
  "issued": "2026-11-18T10:20:00Z"
}
```

`entry` is the index of the intent entry and is missing if the old server never submitted its intent. Key fingerprints are the SHA-256 of the DER encoded public key.

To verify a seal, find its `kid` in the discovery document, check the Ed25519 signature over the first two JWS segments, then compare `intentHash` and `receiptHash` with the SHA-256 of the intent and receipt you hold.

### `GET /api/v1/migrations/{intentHash}`

Returns what Transit knows about one migration: both hostnames, key fingerprints, the entry and the seal. You need the intent hash, so only people who hold the intent can look a migration up.

### Log

| Endpoint | Returns |
|---|---|
| `GET /api/v1/log` | The latest signed tree head: `size`, `root`, `timestamp`, `jws` |
| `GET /api/v1/log/entries?start=0&end=99` | Up to 1000 entries |
| `GET /api/v1/log/proof/inclusion?index=5&size=42` | Inclusion proof for an entry. `hash=sha256:...` works in place of `index` |
| `GET /api/v1/log/proof/consistency?first=10&second=42` | Proof that the tree of size `second` extends the tree of size `first` |

The log follows RFC 6962. A leaf hash is `sha256(0x00 || entry)` and a node hash is `sha256(0x01 || left || right)`. Each entry is its `data` object encoded as JSON with keys sorted recursively and slashes and unicode unescaped. Proof hashes are hex encoded and are verified with the algorithms in RFC 9162 section 2.1. `app/Transit/Log/ProofVerifier.php` is a reference implementation.

Entries never contain account addresses:

```json
{ "kind": "intent", "intentHash": "sha256:...", "source": "old.example", "destination": "new.example", "sourceKey": "sha256:...", "received": "2026-11-18T10:05:00Z" }
```

```json
{ "kind": "seal", "intentHash": "sha256:...", "receiptHash": "sha256:...", "source": "old.example", "destination": "new.example", "sourceKey": "sha256:...", "destinationKey": "sha256:...", "manifestRoot": "sha256:...", "mapRoot": "sha256:...", "sealed": "2026-11-18T10:20:00Z" }
```

Every append produces a signed tree head (`type: "TransitTreeHead"`) with the new `size` and `root`.

## What Transit checks

Before logging an intent:

- It is a compact JWS with `alg: RS256` and `typ: loops-migration+jws`, and its `kid` is on the `source` server
- The signature matches the public key in the source server's instance actor document. Keys are fetched over HTTPS from public addresses only, with no redirects
- The intent id and subject are on the source server, the target is on the destination server, and the two servers differ
- `transit` is this Transit's URL
- It is not expired, was not issued in the future and does not live longer than `TRANSIT_MAX_INTENT_DAYS`
- Both servers pass the allow and block lists and, by default, list this Transit in NodeInfo

Before sealing a receipt:

- The receipt is signed by the destination server's key
- `intent` and `intentHash` point at the submitted intent
- `subject`, `target`, `source`, `destination` and `manifestRoot` match the intent
- The intent signature is checked against the fingerprint recorded when it was logged, so a key rotation on the old server in between does not block the seal. If the intent was never logged, its signature is checked against the old server's current key

## Privacy

The public log holds hashes, hostnames, key fingerprints and timestamps. Account addresses are only in the stored intent and receipt, and those are only returned to someone who already holds the intent. Moves are public anyway through `movedTo`, but the log is never a browsable list of who left which server.

## Testing

```sh
php artisan test
```

## License

AGPL-3.0-only
