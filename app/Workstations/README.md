# Workstation Manager

Warehouses ke Linux workstations par chalne wali `gasmonitor` binary (CO2/PH3 monitoring) ko ek jagah se
**install, update, configure aur control** karne ka module.

- Admin portal: `https://<server>/workstations`
- Agent API: `/api/agent/bootstrap/{warehouse_code}`, `/api/agent/checkin`, `/api/agent/releases/{id}/download`
- Code: `app/Workstations` (namespace `App\Workstations`)

---

## 1. Setup

### 1.1 `.env` values

```dotenv
# Admin portal login
WSM_ADMIN_USER=admin
WSM_ADMIN_PASS=<lamba-random-password>

# Ed25519 public key (base64). Iske bina release upload NAHI hoga.
WSM_PUBLIC_KEY=<step 1.2 se>

# Optional: shared enrollment key (sirf header mein bheji jaati hai).
# Khaali chhod dein to sirf "Add Workstation" wizard ke ek-baar ke tokens chalenge (recommended).
WSM_BOOTSTRAP_KEY=

# Portal hardening
WSM_REQUIRE_HTTPS=true            # production mein true rakhein (default: APP_ENV=production par true)
WSM_ALLOWED_IPS=                  # optional, jaise: 203.0.113.10,10.0.0.0/8

# Optional tuning (defaults dikhaye gaye hain)
# WSM_CHECK_INTERVAL=300          # agent kitne second mein check-in kare
# WSM_OFFLINE_AFTER=900           # itne second check-in na ho to "Offline"
# WSM_INSTALL_TOKEN_TTL=1440      # install command kitne minute valid rahe
# WSM_CHECKIN_PER_MINUTE=30
# WSM_BOOTSTRAP_PER_MINUTE=10
# WSM_DISK=local                  # release files kis storage disk par jayein
```

Phir:

```bash
php artisan config:clear
php artisan migrate --force
```

> **Purane tables:** migration `2026_11_26_000001_create_wsm_tables` pehle wale module ke tables
> (`workstations`, `releases`, `commands`, `events`, `env_entries`) mil jaane par unhe `wsm_*` naam de deti hai.
> Data delete nahi hota. Tables na hon to naye bana deti hai. Saath mein `wsm_releases` mein `type` aur `post_update`
> columns, aur `wsm_install_tokens` table add hoti hai.

### 1.2 Signing key generate karna (sirf ek baar)

Private key **server par mat rakhiye**. Ise release banane wali machine (ya offline laptop) par rakhein.

OpenSSL 3 ke saath:

```bash
openssl genpkey -algorithm ed25519 -out wsm-signing.key        # PRIVATE — safe jagah rakhein
openssl pkey -in wsm-signing.key -pubout -outform DER | tail -c 32 | base64
```

Doosri command ka output `WSM_PUBLIC_KEY` mein daalein.

PHP (sodium) se bhi bana sakte hain:

```bash
php -r '$k=sodium_crypto_sign_keypair(); file_put_contents("wsm-signing.sk", base64_encode(sodium_crypto_sign_secretkey($k))); echo base64_encode(sodium_crypto_sign_publickey($k)),"\n";'
```

### 1.3 Release sign karna

Signed message hamesha ye hota hai: `gasmonitor|<version>|<sha256 lowercase>`

```bash
V=1.2.0; F=./gasmonitor
SHA=$(sha256sum "$F" | cut -d' ' -f1)
printf 'gasmonitor|%s|%s' "$V" "$SHA" > msg
openssl pkeyutl -sign -inkey wsm-signing.key -rawin -in msg | base64 -w0; echo
```

Agar key sodium wali hai:

```bash
php -r '[$v,$f,$sk]=array_slice($argv,1); echo base64_encode(sodium_crypto_sign_detached("gasmonitor|$v|".hash_file("sha256",$f), base64_decode(file_get_contents($sk)))),"\n";' 1.2.0 ./gasmonitor wsm-signing.sk
```

---

## 2. Pehla release upload

**Web se:** `/workstations/releases` kholen. File drag-and-drop karein, version aur signature daalein, "latest" tick rehne dein, phir **Upload** dabayein.
Signature galat ho, ya `WSM_PUBLIC_KEY` set na ho, to upload reject ho jaata hai.

**CLI se** (badi files ke liye):

```bash
php artisan workstations:release upload 1.2.0 ./gasmonitor --signature=<base64> --latest
php artisan workstations:release list
php artisan workstations:release latest 1.1.0      # purane version ko latest banana
php artisan workstations:release yank 1.2.0        # kharab release wapas lena
php artisan workstations:release unyank 1.2.0
```

### Release types

| Type | File | Install |
|---|---|---|
| `binary` | `gasmonitor` executable | `/opt/gasmonitor/gasmonitor` replace, `.bak` rakha jaata hai |
| `package` | `.tar.gz` | `/opt/gasmonitor/current` mein extract (purana `previous` mein), phir optional post-update action |

Post-update actions **sirf** `config.php` → `post_update_actions` ki fixed list se aate hain (jaise
`composer_install_restart`). Admin sirf naam chunta hai, aur agent ko fixed argv steps milte hain. Koi typed shell string kabhi
nahi chalti. Naya action chahiye to code mein config badal kar deploy karein.

```bash
php artisan workstations:release upload 2.0.0 ./app.tar.gz --type=package --post-update=composer_install_restart --signature=<base64> --latest
```

---

## 3. Pehla workstation add karna

1. Portal mein **+ Add Workstation** kholen.
2. Warehouse chunein, aur batayein kitni machines is command se judengi. Phir **Install command banao** dabayein.
3. Jo command dikhe use copy karein (ye sirf ek baar dikhta hai):
   ```bash
   curl -fsSL -H "Authorization: Bearer wsi_…" https://<server>/api/agent/bootstrap/WH-01 | sudo bash
   ```
4. Workstation par ye command chalayein. Script ye kaam karti hai:
   - latest release download karti hai
   - **sha256** check karti hai
   - **Ed25519 signature** verify karti hai (OpenSSL 3 chahiye). Verify fail hua to install ruk jaata hai.
   - `/opt/gasmonitor` mein install karti hai
   - `.env` mein `WAREHOUSE_CODE`, `CENTRAL_API`, `AUTO_UPDATE=1`, `UPDATE_PUBLIC_KEY` set karti hai
   - `gasmonitor` systemd service banakar start karti hai
5. Kuch der mein workstation dashboard par **Pending** mein dikhega. Use **Approve** karein.
   Agle check-in par use apna token, settings aur update mil jayenge.

---

## 4. Kaam kaise hota hai

```
Admin ──(install command)──► Workstation ──GET /api/agent/bootstrap/{wh}  (Authorization header)
                                         ◄── install.sh (release, sha256, signature, public key)
                             Workstation ──POST /api/agent/checkin  (install token / X-API-Key)
                                         ◄── {status: pending}                ← admin Approve karta hai
                             Workstation ──POST /api/agent/checkin
                                         ◄── {status: approved, token: wst_…} ← token DB mein sirf sha256 hash
                             Workstation ──POST /api/agent/checkin  (Authorization: Bearer wst_…)
                                         ◄── {update?, env?, commands?}
                             Workstation ──GET /api/agent/releases/{id}/download (Bearer)
```

Saari decision logic ek hi class mein hai: `App\Workstations\Support\AgentProtocol::decide()`.

**Check-in response:**
- `update`: `{id, version, type, sha256, signature, size, post_update?}`. Ye tabhi aata hai jab teeno baatein sach hon:
  - auto-update on ho
  - pinned release ho (warna latest), aur wo current version ke barabar na ho
  - wo agent ki `bad_versions` list mein na ho

  **Unsigned ya galat-signed release kabhi offer nahi hota.**
- `env`: `{hash, values}`. Settings ka order global < warehouse < workstation hai, aur locked keys kabhi nahi bheji jaatin. Ye tabhi aata hai jab agent ka `env_hash` alag ho.
- `commands`: sirf `restart`, `sync_now`, `stop_polling`. Bhejne ke baad unka status `sent` ho jaata hai. Agent ka ack aane par `done` ya `failed`.

**Agent jo bhejta hai:** `workstation_id`, `version`, telemetry (`hostname`, `devices_total`, `alerts`, …), `env_hash`,
`bad_versions`, `reports[]` (`update_installed`, `update_failed`, `update_ok`, `rollback`, `env_applied`, `env_error`),
aur `command_results[]` (`{id, status: ok|failed, result}`).

**Agent side zaroori niyam:** agent update tabhi install kare jab `UPDATE_PUBLIC_KEY` se
`gasmonitor|version|sha256` ka signature verify ho jaaye. Installer `.env` mein `REQUIRE_SIGNED_UPDATES=1` bhi likhta hai.
`run.sh` crash-loop guard hai: update ke baad service 3 baar se zyada crash ho to wo purane binary/`.env` par rollback kar deta hai.

---

## 5. Security checklist

| Kya | Kaise |
|---|---|
| Bootstrap key URL mein nahi | Sirf `Authorization: Bearer` / `X-API-Key` header. Query string wali key ignore hoti hai |
| Install command | Ek-baar ka `wsi_` token: warehouse se bandha hua, `max_uses` tak, `WSM_INSTALL_TOKEN_TTL` minute mein expire. DB mein hash |
| Signature mandatory | `WSM_PUBLIC_KEY` ke bina upload reject. Unsigned release na offer hota hai, na download, na install |
| Commands | Fixed whitelist. DB mein koi aur command daal bhi di jaaye to agent ko nahi bheji jaati |
| Tokens | Workstation aur install tokens sirf sha256 hash ke roop mein store |
| Rate limit | check-in: `WSM_CHECKIN_PER_MINUTE` (IP + workstation), bootstrap/download: `WSM_BOOTSTRAP_PER_MINUTE` (IP), login: 5/min |
| Locked env keys | `DB_URL`, `WORKSTATION_ID`, `WAREHOUSE_CODE`, `CENTRAL_API*`, `CLOUD_API_KEY`, `UPDATE_PUBLIC_KEY`, `AUTO_UPDATE` |
| Numeric env keys | Integer / decimal validation, galat value par inline error |
| Portal | Login (session), `WSM_REQUIRE_HTTPS`, optional `WSM_ALLOWED_IPS` |

---

## 6. Tests

```bash
php artisan test tests/Feature/Workstations
```

Inme ye cover hai: bootstrap (header-only key, install token, expiry, warehouse mismatch, rate limit), check-in states
(pending/approved/blocked, token issue), signature reject, command whitelist, env merge/lock, update decision
(pinned/latest/auto_update off/bad versions/package post-update), admin portal pages aur CLI command.
