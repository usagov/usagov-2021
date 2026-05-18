---
applyTo: "manifest*.yml,**/manifest*.yml,Procfile,**/Procfile,.cfignore,**/.cfignore,.profile,**/.profile,**/*.sh"
---

# Cloud.gov Deployment Instructions

This repository deploys a Drupal application stack to cloud.gov as multiple Cloud Foundry apps. The guidance below is intentionally repo-specific and should be used instead of generic buildpack-first cloud.gov examples.

## Overview

- The primary deployment file is `manifest.yml`.
- The main apps are `cms`, `www`, `waf`, and `cron`.
- The normal deployment path is Docker-image based, not source-buildpack based.
- The normal health check pattern in this repo is `health-check-type: process`.
- Runtime behavior is finalized by shell bootstrap scripts such as `scripts/bootstrap.sh` and `scripts/static-bootstrap.sh`.

## Repo Deployment Model

### Main Multi-App Manifest

`manifest.yml` defines the site's main Cloud.gov apps and should be treated as the source of truth for names and service bindings:

```yaml
applications:
  - name: cms
    docker:
      image: gsatts/usagov-2021:cms-latest
    services:
      - database
      - secrets
      - secauthsecrets
      - storage
    health-check-type: process

  - name: www
    docker:
      image: gsatts/usagov-2021:www-latest
    services:
      - secrets
      - storage
    health-check-type: process
```

- `cms` is the Drupal runtime.
- `www` is the static-serving app.
- `waf` is the route-service/WAF app managed with additional helper scripts.
- `cron` is the no-route background job app with cron-specific service bindings.

### Specialized Manifests

- `manifest-reporter.yml` is a specialized analytics reporter manifest.
- `manifest-egress.yml` is the important exception to the Docker-first rule:
  it uses buildpacks, an internal route, and `command: ./start.sh` for the egress proxy pattern.

```yaml
applications:
  - name: ((proxyname))
    path: .docker/src-egress
    buildpacks:
      - https://github.com/cloudfoundry/apt-buildpack
      - binary_buildpack
    command: ./start.sh
    health-check-type: process
```

Do not rewrite the main site manifests to match the egress example. Treat it as a special-purpose pattern.

## Agent Safety Rules

Before suggesting or running mutating Cloud Foundry commands:

- Run `cf target` and identify the active org and space.
- State which org/space will be affected.
- If the space is `prod`, treat it as production and call that out explicitly.
- Get explicit user confirmation before `cf push`, `cf restage`, `cf restart`, `cf scale`, `cf set-env`, route changes, service binding changes, or service updates/deletes.
- Prefer read-only inspection first: `cf app`, `cf services`, `cf env`, `cf logs --recent`, `cf routes`, `cf target`.

This repo already contains helper scripts that assume the operator understands the current target space. Agents should not improvise around that safety boundary.

## Preferred Workflow

1. Inspect the current target:
   `cf target`
2. Read the relevant manifest before proposing changes:
   `manifest.yml`, `manifest-reporter.yml`, or `manifest-egress.yml`
3. Use repo helpers when they match the task:
   - `bin/deploy/check-current-deployment <space>` for current deployment inspection
   - `bin/cloudgov/deploy-waf` for WAF / route-service deployment behavior
   - `bin/deploy/includes` for common CF helper logic used by deployment scripts
4. Verify post-deploy state with app status and recent logs.

### Verification Commands

```bash
cf app cms
cf app www
cf app waf
cf app cron

cf logs cms --recent
cf logs www --recent
```

For deployment comparison in an existing space, prefer the repo helper:

```bash
bin/deploy/check-current-deployment dev
```

That helper uses `cf ssh` against `cms` and `waf` to inspect the deployed version markers.

## Runtime Entry Points

The main post-push runtime configuration lives in shell scripts, not in manifest-only env blocks:

- `scripts/bootstrap.sh` configures the `cms` runtime from bound services and environment.
- `scripts/static-bootstrap.sh` configures the static-serving runtime.
- `scripts/tome-run.sh` and `scripts/tome-sync.sh` depend on Cloud Foundry service bindings and app metadata.

When editing deployment behavior, review the manifest and the relevant bootstrap script together.

## Avoid

- Do not assume the repo uses a single-app source-buildpack deployment.
- Do not introduce `gunicorn`, generic Python worker, or Node buildpack examples into the main deployment path.
- Do not default to `health-check-type: http`; the main manifests here use `process`.
- Do not replace `bin/cloudgov/deploy-waf` with ad hoc route-service commands unless the user explicitly wants that refactor.
- Do not put secrets directly in manifests; see `services.instructions.md` and `security.instructions.md` for binding and secret-handling guidance.
