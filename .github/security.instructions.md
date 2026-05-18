---
applyTo: "**/*.php,**/*.module,**/*.install,**/*.inc,**/*.theme,**/*.profile,**/*.js,**/*.ts,**/*.tsx,**/*.sh,manifest*.yml,**/manifest*.yml,.github/workflows/*.yml,.github/workflows/*.yaml,**/.github/workflows/*.yml,**/.github/workflows/*.yaml"
---

# Cloud.gov Security Instructions

This repository relies on cloud.gov inherited controls and repo-managed application controls. Security guidance here should follow the actual Drupal, shell-bootstrap, and route-service patterns already present in the codebase.

## Overview

- Cloud.gov provides the FedRAMP-authorized platform boundary.
- This repo is responsible for application secrets, trusted-host behavior, service consumption, route-service configuration, and audit visibility.
- Use the repo's existing service bindings and runtime configuration paths before proposing new ones.

## Agent Safety Rules

Before suggesting or running any mutating Cloud Foundry command:

- Inspect `cf target` and state the current org and space.
- If the space is `prod`, call that out explicitly as production.
- Require explicit user confirmation before `cf push`, `cf restage`, `cf restart`, `cf scale`, route changes, service binding changes, or secret/config changes.
- Prefer read-only verification first: `cf app`, `cf services`, `cf env`, `cf logs --recent`.

## Secrets and Credential Handling

`NIST 800-53: IA-5 - Authenticator Management`
`NIST 800-53: SC-28 - Protection of Information at Rest`
`NIST 800-53: CM-6 - Configuration Settings`

- Do not commit secrets to the repository.
- Do not place secrets in manifest env blocks or `vars*.yml`.
- Prefer bound services such as `secrets`, `secauthsecrets`, `database`, and `storage`.
- Treat `web/sites/default/settings.php` and bootstrap scripts as the canonical runtime locations for secret consumption.

Repo-specific examples:
- `settings.php` derives Drupal `hash_salt` from the `secrets` binding.
- `scripts/bootstrap.sh` reads runtime values such as `NEW_RELIC_*` and `CRON_KEY` from `secrets`.
- `scripts/bootstrap.sh` reads SAML material from `secauthsecrets` and writes runtime files under `/var/www/`.

Prefer this manifest style:

```yaml
# NIST 800-53: CM-6 - Configuration Settings
# Bind named services; do not embed credentials in the manifest.
services:
  - database
  - secrets
  - secauthsecrets
  - storage
```

Avoid this pattern:

```yaml
env:
  DATABASE_PASSWORD: plain-text-secret
  HASH_SALT: plain-text-secret
```

## Trusted Hosts, Authentication Boundaries, and Local Overrides

`NIST 800-53: IA-2 - Identification and Authentication`
`NIST 800-53: CM-6 - Configuration Settings`

- `web/sites/default/settings.php` derives `trusted_host_patterns` from `VCAP_APPLICATION.space_name`.
- The Cloud.gov spaces with explicit trusted-host behavior are `dev`, `dr`, `stage`, and `prod`.
- `web/sites/default/settings.local.php` contains local-only overrides such as localhost trusted hosts and `$settings['usagov_login_local_form'] = 1`.
- Treat the local login override as a local-development escape hatch only; do not extend it into Cloud.gov spaces without an explicit requirement.

Preferred PHP pattern:

```php
$cf_application_data = json_decode($_ENV['VCAP_APPLICATION'] ?? '{}', TRUE);
$space_name = strtolower($cf_application_data['space_name'] ?? '');
$settings['trusted_host_patterns'] = [];
```

## Protected Service Communication

`NIST 800-53: SC-8 - Transmission Confidentiality and Integrity`
`NIST 800-53: SC-13 - Cryptographic Protection`
`NIST 800-53: SC-28 - Protection of Information at Rest`

- `settings.php` enables MySQL TLS in Cloud.gov by setting the RDS CA bundle on the Drupal PDO connection.
- S3FS is configured to use the service-provided `fips_endpoint` and HTTPS.
- Redis is configured for TLS unless the service credentials indicate local development.
- When documenting or extending networked service access, preserve those encrypted-transport defaults.

## Route Service and Egress Patterns

`NIST 800-53: CM-6 - Configuration Settings`
`NIST 800-53: SC-8 - Transmission Confidentiality and Integrity`

- `manifest-egress.yml` is the repo's buildpack-based internal egress proxy pattern.
- `bin/cloudgov/deploy-waf` is the repo's authoritative route-service / WAF deployment helper.
- That script manages route-service setup, app-to-app policy, env updates, and host mapping for protected apps such as `cms` and `www`.
- Prefer the existing WAF and egress patterns over hand-written CF route-service flows unless the user specifically wants to change that architecture.

Do not turn this doc into a generic cloud.gov network tutorial. Keep guidance anchored to `manifest-egress.yml` and `bin/cloudgov/deploy-waf`.

## Validation and Safe Handling

`NIST 800-53: SI-10 - Information Input Validation`

- Parse `VCAP_APPLICATION` and `VCAP_SERVICES` as JSON and validate service names explicitly.
- Prefer exact checks such as `if ($service['name'] === 'database')` over loose assumptions about service ordering.
- In shell, prefer `jq` selectors that name the expected service rather than positional access.
- Do not silently fall back to invented credential shapes when a required binding is missing; fail clearly or use the repo's established warning behavior.

## Audit and Security Visibility

`NIST 800-53: AU-2 - Event Logging`
`NIST 800-53: AU-3 - Content of Audit Records`
`NIST 800-53: AU-12 - Audit Record Generation`

- Security-relevant application events should use Drupal logger channels or operational stdout/stderr, depending on whether the event is application-level or bootstrap/container-level.
- Do not log secrets, raw credentials, tokens, private keys, or sensitive personal data.
- For detailed audit/logging patterns in this repo, follow `logging.instructions.md`.

## Cross-References

- For service-binding and runtime parsing patterns, see `services.instructions.md`.
- For deployment and manifest behavior, see `deployment.instructions.md`.
- For logging and observability boundaries, see `logging.instructions.md`.
