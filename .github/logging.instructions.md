---
applyTo: "**/*.php,**/*.module,**/*.install,**/*.inc,**/*.theme,**/*.profile,**/*.js,**/*.ts,**/*.tsx,**/*.sh,manifest*.yml,**/manifest*.yml"
---

# Cloud.gov Logging Instructions

This repository uses a split logging model: shell/bootstrap/container logs go to stdout or stderr, while Drupal application and audit events go through Drupal logger channels. Logging guidance should extend those existing patterns instead of introducing generic logging frameworks from unrelated stacks.

## Overview

- Cloud.gov captures process stdout and stderr.
- Bootstrap and operational scripts in this repo already emit status and warning lines with `echo`.
- Drupal business and audit events in this repo are commonly written with `\Drupal::logger(...)`.
- Local and container New Relic setup is configured to write agent and daemon logs to stderr.
- `config/sync/system.logging.yml` hides end-user PHP errors, so user-facing error display and operator logging are intentionally separate concerns.

## Repo Logging Model

### 1. Bootstrap and Container Logs

Use stdout/stderr for deployment, bootstrap, and background-job visibility.

Example patterns already in the repo:

```bash
echo "Deployment: bootstrap starting"
echo "WARNING: The aws-rds variable is not set in the VCAP_SERVICES ..."
echo "Checking current deployment in $TARGET_ENV" | toLogs
```

- Keep these messages concise and operationally useful.
- Include app/space/context when it helps operators understand what failed.
- Do not move bootstrap logging into file-based loggers.

### 2. Drupal Application and Audit Logs

Use Drupal logger channels for application-level events.

Repo example:

```php
\Drupal::logger('usagov_login')->notice(
  "Role {$role} added to user {$user_performed_on} ({$user_performed_named}) by user {$uid} ({$uname})."
);
```

- Use a channel that matches the owning module or subsystem.
- Prefer `notice`, `warning`, and `error` levels that reflect operational meaning.
- Include enough context to investigate, but do not log secrets or sensitive raw payloads.

### 3. New Relic and Observability Tooling

The repo's local/container New Relic setup already follows the stdout/stderr rule:

```sh
-e 's/;\?newrelic.logfile =.*/newrelic.logfile = \/dev\/stderr/'
-e 's/;\?newrelic.daemon.logfile =.*/newrelic.daemon.logfile = \/dev\/stderr/'
```

Bootstrap scripts also configure New Relic from runtime environment values such as `NEW_RELIC_LICENSE_KEY` and `NEW_RELIC_APP_NAME`. Keep observability configuration env-driven.

## Log Consumption in Cloud.gov

Use standard Cloud Foundry log inspection against the real app names in this repo:

```bash
cf logs cms --recent
cf logs www --recent
cf logs waf --recent
cf logs cron --recent
```

For deployment confirmation, pair recent logs with:

```bash
cf app cms
cf app www
```

## Sensitive Data Rules

`NIST 800-53: AU-2 - Event Logging`
`NIST 800-53: AU-3 - Content of Audit Records`
`NIST 800-53: AU-12 - Audit Record Generation`

- Never log credentials, tokens, private keys, raw secret payloads, or sensitive personal data.
- Treat service credentials from `VCAP_SERVICES` and secrets loaded by bootstrap scripts as non-loggable.
- Keep operator-visible messages informative without dumping raw environment data.
- For broader secret-handling and transport protections, follow `security.instructions.md`.

## Preferred Pattern / Avoid

Prefer:
- `\Drupal::logger('<channel>')->notice()/warning()/error()` for application events
- stdout/stderr output for shell/bootstrap/deploy/runtime scripts
- env-driven observability configuration
- concise messages with enough context to debug the failing subsystem

Avoid:
- introducing file-based application loggers
- pasting generic Python, Ruby, or Node logging framework examples into repo guidance
- replacing Drupal logger-channel usage with unrelated raw JSON frameworks
- exposing internal errors to end users; `config/sync/system.logging.yml` sets `error_level: hide`

## Cross-References

- For audit and security boundaries, see `security.instructions.md`.
- For deployment-time operational checks, see `deployment.instructions.md`.
- For service-binding failures and startup dependency issues, see `services.instructions.md`.
