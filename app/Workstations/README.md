# Workstation Manager

A module to **install, update, configure and control**, from one place, the `gasmonitor` binary
(CO2/PH3 monitoring) running on warehouse Linux workstations.

- Admin portal: `https://<server>/workstations`
- Agent API: `/api/agent/bootstrap/{warehouse_code}`, `/api/agent/checkin`, `/api/agent/releases/{id}/download`
- Code: `app/Workstations` (namespace `App\Workstations`)

---

## 1. Setup

### 1.1 `.env` values

```dotenv
# Admin portal login
WSM_ADMIN_USER=admin
WSM_ADMIN_PASS=<long-random-password>

# Ed25519 public key (base64). Releases CANNOT be uploaded without it.
WSM_PUBLIC_KEY=<from step 1.2>

# Optional: shared enrollment key (only ever sent in a header).
# Leave empty to allow only the one-time tokens from the "Add Workstation" wizard (recommended).
WSM_BOOTSTRAP_KEY=

# Portal hardening
WSM_REQUIRE_HTTPS=true            # keep true in production (default: true when APP_ENV=production)
WSM_ALLOWED_IPS=                  # optional, e.g. 203.0.113.10,10.0.0.0/8

# Optional tuning (defaults shown)
# WSM_CHECK_INTERVAL=300          # seconds between agent check-ins
# WSM_OFFLINE_AFTER=900           # seconds without a check-in before "Offline"
# WSM_INSTALL_TOKEN_TTL=1440      # minutes an install command stays valid
# WSM_CHECKIN_PER_MINUTE=30
# WSM_BOOTSTRAP_PER_MINUTE=10
# WSM_DISK=local                  # storage disk for release files
```

Then:

```bash
php artisan config:clear
php artisan migrate --force
```

> **Existing tables:** the migration `2026_11_26_000001_create_wsm_tables` finds the tables of the previous module
> (`workstations`, `releases`, `commands`, `events`, `env_entries`) and renames them to `wsm_*`.
> No data is deleted. If the tables don't exist, it creates new ones. It also adds the `type` and `post_update`
> columns to `wsm_releases`, and the `wsm_install_tokens` table.

### 1.2 Generating the signing key (once)

**Do not keep the private key on the server.** Keep it on the machine that builds releases (or an offline laptop).

With OpenSSL 3:

```bash
openssl genpkey -algorithm ed25519 -out wsm-signing.key        # PRIVATE — keep it safe
openssl pkey -in wsm-signing.key -pubout -outform DER | tail -c 32 | base64
```

Put the output of the second command in `WSM_PUBLIC_KEY`.

Or with PHP (sodium):

```bash
php -r '$k=sodium_crypto_sign_keypair(); file_put_contents("wsm-signing.sk", base64_encode(sodium_crypto_sign_secretkey($k))); echo base64_encode(sodium_crypto_sign_publickey($k)),"\n";'
```

### 1.3 Signing a release

The signed message is always: `gasmonitor|<version>|<sha256 lowercase>`

```bash
V=1.2.0; F=./gasmonitor
SHA=$(sha256sum "$F" | cut -d' ' -f1)
printf 'gasmonitor|%s|%s' "$V" "$SHA" > msg
openssl pkeyutl -sign -inkey wsm-signing.key -rawin -in msg | base64 -w0; echo
```

With a sodium key:

```bash
php -r '[$v,$f,$sk]=array_slice($argv,1); echo base64_encode(sodium_crypto_sign_detached("gasmonitor|$v|".hash_file("sha256",$f), base64_decode(file_get_contents($sk)))),"\n";' 1.2.0 ./gasmonitor wsm-signing.sk
```

---

## 2. Uploading the first release

**From the web:** open `/workstations/releases`. Drag and drop the file, enter the version and signature, leave "latest" ticked, then click **Upload**.
The upload is rejected if the signature is wrong or `WSM_PUBLIC_KEY` is not set.

**From the CLI** (for large files):

```bash
php artisan workstations:release upload 1.2.0 ./gasmonitor --signature=<base64> --latest
php artisan workstations:release list
php artisan workstations:release latest 1.1.0      # make an older version the latest
php artisan workstations:release yank 1.2.0        # pull a bad release
php artisan workstations:release unyank 1.2.0
```

### Release types

| Type | File | Install |
|---|---|---|
| `binary` | `gasmonitor` executable | replaces `/opt/gasmonitor/gasmonitor`, keeps a `.bak` |
| `package` | `.tar.gz` | extracted into `/opt/gasmonitor/current` (old one moved to `previous`), then an optional post-update action |

Post-update actions come **only** from the fixed list in `config.php` → `post_update_actions` (e.g.
`composer_install_restart`). The admin only picks a name, and the agent receives fixed argv steps. No typed shell string
is ever run. To add a new action, change the config in code and deploy.

```bash
php artisan workstations:release upload 2.0.0 ./app.tar.gz --type=package --post-update=composer_install_restart --signature=<base64> --latest
```

---

## 3. Adding the first workstation

1. In the portal, open **+ Add Workstation**.
2. Choose the warehouse and how many machines will use this command. Then click **Create install command**.
3. Copy the command shown (it is shown only once):
   ```bash
   curl -fsSL -H "Authorization: Bearer wsi_…" https://<server>/api/agent/bootstrap/WH-01 | sudo bash
   ```
4. Run the command on the workstation. The script:
   - downloads the latest release
   - checks the **sha256**
   - verifies the **Ed25519 signature** (needs OpenSSL 3). If verification fails, the install stops.
   - installs into `/opt/gasmonitor`
   - sets `WAREHOUSE_CODE`, `CENTRAL_API`, `AUTO_UPDATE=1` and `UPDATE_PUBLIC_KEY` in `.env`
   - creates and starts the `gasmonitor` systemd service
5. Shortly after, the workstation shows up on the dashboard as **Pending**. **Approve** it.
   On its next check-in it receives its token, settings and update.

---

## 4. How it works

```
Admin ──(install command)──► Workstation ──GET /api/agent/bootstrap/{wh}  (Authorization header)
                                         ◄── install.sh (release, sha256, signature, public key)
                             Workstation ──POST /api/agent/checkin  (install token / X-API-Key)
                                         ◄── {status: pending}                ← admin clicks Approve
                             Workstation ──POST /api/agent/checkin
                                         ◄── {status: approved, token: wst_…} ← only the sha256 hash is stored
                             Workstation ──POST /api/agent/checkin  (Authorization: Bearer wst_…)
                                         ◄── {update?, env?, commands?}
                             Workstation ──GET /api/agent/releases/{id}/download (Bearer)
```

All decision logic lives in one class: `App\Workstations\Support\AgentProtocol::decide()`.

**Check-in response:**
- `update`: `{id, version, type, sha256, signature, size, post_update?}`. Sent only when all three hold:
  - auto-update is on
  - there is a pinned release (otherwise the latest), and it differs from the current version
  - it is not in the agent's `bad_versions` list

  **An unsigned or wrongly signed release is never offered.**
- `env`: `{hash, values}`. Settings are merged global < warehouse < workstation, and locked keys are never sent. Sent only when the agent's `env_hash` differs.
- `commands`: only `restart`, `sync_now`, `stop_polling`. Marked `sent` once delivered, then `done` or `failed` when the agent acks.

**What the agent sends:** `workstation_id`, `version`, telemetry (`hostname`, `devices_total`, `alerts`, …), `env_hash`,
`bad_versions`, `reports[]` (`update_installed`, `update_failed`, `update_ok`, `rollback`, `env_applied`, `env_error`),
and `command_results[]` (`{id, status: ok|failed, result}`).

**Required agent behaviour:** the agent must install an update only if the signature over `gasmonitor|version|sha256`
verifies against `UPDATE_PUBLIC_KEY`. The installer also writes `REQUIRE_SIGNED_UPDATES=1` to `.env`.
`run.sh` is a crash-loop guard: if the service crashes more than 3 times after an update, it rolls back to the previous binary/`.env`.

---

## 5. Security checklist

| What | How |
|---|---|
| No bootstrap key in URLs | Only the `Authorization: Bearer` / `X-API-Key` header. A key in the query string is ignored |
| Install command | One-time `wsi_` token: bound to a warehouse, limited to `max_uses`, expires after `WSM_INSTALL_TOKEN_TTL` minutes. Stored hashed |
| Mandatory signatures | Upload rejected without `WSM_PUBLIC_KEY`. Unsigned releases are never offered, downloaded or installed |
| Commands | Fixed whitelist. Even if another command is inserted into the DB, it is never sent to the agent |
| Tokens | Workstation and install tokens are stored only as sha256 hashes |
| Rate limits | check-in: `WSM_CHECKIN_PER_MINUTE` (IP + workstation), bootstrap/download: `WSM_BOOTSTRAP_PER_MINUTE` (IP), login: 5/min |
| Locked env keys | `DB_URL`, `WORKSTATION_ID`, `WAREHOUSE_CODE`, `CENTRAL_API*`, `CLOUD_API_KEY`, `UPDATE_PUBLIC_KEY`, `AUTO_UPDATE` |
| Numeric env keys | Integer / decimal validation, with an inline error for bad values |
| Portal | Session login, `WSM_REQUIRE_HTTPS`, optional `WSM_ALLOWED_IPS` |

---

## 6. Tests

```bash
php artisan test tests/Feature/Workstations
```

Covers: bootstrap (header-only key, install token, expiry, warehouse mismatch, rate limit), check-in states
(pending/approved/blocked, token issue), signature rejection, command whitelist, env merge/lock, update decision
(pinned/latest/auto_update off/bad versions/package post-update), admin portal pages and the CLI command.
