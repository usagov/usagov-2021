---
applyTo: "**/*.php,**/*.module,**/*.install,**/*.inc,**/*.theme,**/*.profile,**/*.js,**/*.ts,**/*.tsx,**/*.sh,manifest*.yml,**/manifest*.yml"
---

# Cloud.gov Services Instructions

This repository uses Cloud Foundry service bindings as the primary source of runtime credentials. Service guidance in this repo should be based on the exact binding names and parsing patterns already present in Drupal settings and bootstrap scripts.

## Overview

- Drupal runtime configuration is built from `VCAP_SERVICES` and `VCAP_APPLICATION` in `web/sites/default/settings.php`.
- Shell bootstrap scripts such as `scripts/bootstrap.sh`, `scripts/static-bootstrap.sh`, `scripts/tome-run.sh`, and `scripts/tome-sync.sh` parse the same Cloud Foundry JSON with `jq`.
- When extending this repo, prefer those existing parsing locations instead of inventing new credential-loading paths.

## Repo Service Names

Use the service names already present in the manifests unless the user explicitly asks to change them:

| Service Name | Where Used | Notes |
|-------------|------------|-------|
| `database` | `cms` | Drupal database binding; credentials become `$databases['default']['default']` in `settings.php` |
| `storage` | `cms`, `www` | S3-backed storage used by S3FS and static-site sync scripts |
| `secrets` | `cms`, `www`, `waf` | Hash salt and runtime secrets such as New Relic and cron-related values |
| `secauthsecrets` | `cms` | SAML-related key/cert material consumed during bootstrap |
| tagged `cache-service` | optional | Enables Redis caching when a matching service is bound |
| `cron-state-storage`, `cron-event-storage`, `cron-callwait-storage`, `cron-service-account`, `cron-secrets` | `cron` | Cron-specific runtime services |
| `AnalyticsReporterServices` | reporter manifest | Specialized reporter service binding |

Do not invent alternate binding names in examples when the repo already uses a concrete name.

## Where This Repo Reads Cloud Foundry Service Data

### Drupal Runtime: `settings.php`

The primary Drupal pattern is:

```php
$cf_application_data = json_decode($_ENV['VCAP_APPLICATION'] ?? '{}', TRUE);
$cf_service_data = json_decode($_ENV['VCAP_SERVICES'] ?? '{}', TRUE);

foreach ($cf_service_data as $service_list) {
  foreach ($service_list as $service) {
    if ($service['name'] === 'database') {
      // Configure Drupal DB connection.
    }
    elseif ($service['name'] === 'storage') {
      // Configure S3FS.
    }
  }
}
```

Use this existing parse-once pattern when documenting or extending service consumption in PHP.

### Shell Runtime: Bootstrap and Static-Site Scripts

The primary shell pattern is:

```bash
S3_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.["space_name"]')
```

Use `jq` against the bound service name that already exists in the manifest. Do not introduce parallel `.env` files or hand-maintained secret maps for Cloud.gov runtime credentials.

## Real Data Flow in This Repo

### `database`

- `settings.php` maps the bound `database` service to Drupal's MySQL connection.
- In Cloud.gov spaces, the DB config also sets the RDS CA bundle for TLS.
- The DB binding should remain the canonical source of host, port, username, password, and schema name.

```php
$databases['default']['default'] = [
  'database' => $service['credentials']['db_name'],
  'username' => $service['credentials']['username'],
  'password' => $service['credentials']['password'],
  'host' => $service['credentials']['host'],
  'port' => $service['credentials']['port'],
  'driver' => 'mysql',
];
```

### `storage`

- `settings.php` configures S3FS from the bound `storage` service.
- `scripts/tome-run.sh` and `scripts/tome-sync.sh` use the same binding for static-site generation and S3 sync.
- The S3 configuration relies on service-provided `bucket`, `region`, and endpoint values; do not hardcode them.

Relevant repo behavior includes:
- `root_folder = cms`
- `public_folder = public`
- `private_folder = private`
- `fips_endpoint` for the S3FS hostname
- `S3_PROXY_PATH_CMS` for the public path exposed through the CMS host

### `secrets` and `secauthsecrets`

- `secrets` provides values such as `HASH_SALT`, `NEW_RELIC_*`, `CRON_KEY`, and other runtime-only secrets used by bootstrap scripts.
- `secauthsecrets` provides SAML-related certificate and key material that bootstrap writes into runtime files.
- These services are preferred over manifest env secrets or hardcoded credentials.

### tagged `cache-service`

- Redis is optional and enabled only when `settings.php` sees a bound service whose tags include `cache-service`.
- When present, the repo prefers TLS for Redis unless the credentials explicitly indicate local development.

## Space-Aware Runtime Configuration

- `VCAP_APPLICATION.space_name` drives `trusted_host_patterns` and config split toggles in `settings.php`.
- Shell scripts also use the space name to choose the correct public hostname, S3 behavior, and environment-specific logic.
- When changing service behavior, check both the PHP runtime and shell runtime paths for space-specific logic.

## Preferred Pattern / Avoid

- Prefer parsing Cloud Foundry JSON once in `settings.php` or the relevant bootstrap script.
- Prefer exact manifest service names such as `database`, `storage`, `secrets`, and `secauthsecrets`.
- Prefer bound services over manually managed secrets in code or manifests.
- Prefer extending the existing Drupal + shell runtime patterns instead of introducing parallel config systems.

Avoid:
- hardcoding database, S3, Redis, or secret credentials
- inventing alternative service names in examples
- adding ad hoc credential shapes that do not match `VCAP_SERVICES`
- duplicating the same service parsing logic in multiple new locations when an existing runtime path already owns it

## Cross-References

- For manifest and deploy behavior, see `deployment.instructions.md`.
- For secret handling, TLS, and trusted-host guidance, see `security.instructions.md`.
- For operational logging around service startup and failures, see `logging.instructions.md`.
