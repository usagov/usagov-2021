---
applyTo: "**/manifest*.yml,**/vars*.yml"
---

# Cloud.gov Manifest Configuration Instructions

This document provides guidance for creating and configuring `manifest.yml` files for cloud.gov deployments.

## Overview

The `manifest.yml` file defines your application's deployment configuration. It specifies memory, instances, services, environment variables, and other settings.

## Basic Manifest Structure

```yaml
---
applications:
  - name: my-application
    memory: 512M
    disk_quota: 1G
    instances: 2
    buildpacks:
      - python_buildpack
    command: gunicorn app:app
    env:
      ENVIRONMENT: production
    services:
      - my-database
      - my-s3-bucket
    routes:
      - route: my-app.app.cloud.gov
```

## Manifest Properties Reference

### Required Properties

| Property | Description | Example |
|----------|-------------|---------|
| `name` | Application name (must be unique in space) | `my-app` |

### Resource Allocation

| Property | Description | Default | Example |
|----------|-------------|---------|---------|
| `memory` | Memory allocation per instance | `1G` | `512M`, `1G`, `2G` |
| `disk_quota` | Disk space per instance | `1G` | `512M`, `2G` |
| `instances` | Number of application instances | `1` | `2`, `3` |

**Best Practices:**
- Always specify `memory` explicitly
- Use `instances: 2` or more for production
- Size memory based on actual application needs

### Buildpack Configuration

```yaml
applications:
  - name: my-app
    buildpacks:
      - python_buildpack
```

Available buildpacks:
- `python_buildpack`
- `nodejs_buildpack`
- `ruby_buildpack`
- `java_buildpack`
- `go_buildpack`
- `php_buildpack`
- `dotnet_core_buildpack`
- `staticfile_buildpack`
- `binary_buildpack`

#### Multiple Buildpacks

```yaml
applications:
  - name: my-app
    buildpacks:
      - nodejs_buildpack   # Runs first
      - python_buildpack   # Runs second
```

### Command and Process Configuration

```yaml
applications:
  - name: my-app
    command: gunicorn app:app --bind 0.0.0.0:$PORT --workers 3
```

Alternatively, use a `Procfile`:

```
web: gunicorn app:app --bind 0.0.0.0:$PORT
```

### Environment Variables

```yaml
applications:
  - name: my-app
    env:
      ENVIRONMENT: production
      LOG_LEVEL: info
      FEATURE_FLAG_NEW_UI: "true"
```

**Important**: Never put secrets in manifest.yml. Use:
- Bound services (credentials in `VCAP_SERVICES`)
- `cf set-env` for sensitive values
- CI/CD secrets management

### Service Bindings

```yaml
applications:
  - name: my-app
    services:
      - my-postgres-db
      - my-s3-bucket
      - my-redis-cache
```

Services must be created before deployment:

```bash
cf create-service aws-rds micro-psql my-postgres-db
cf create-service s3 basic my-s3-bucket
```

### Route Configuration

#### Single Route

```yaml
applications:
  - name: my-app
    routes:
      - route: my-app.app.cloud.gov
```

#### Multiple Routes

```yaml
applications:
  - name: my-app
    routes:
      - route: my-app.app.cloud.gov
      - route: api.my-app.app.cloud.gov
      - route: my-custom-domain.gov
```

#### No External Route (Workers)

```yaml
applications:
  - name: my-worker
    no-route: true
```

#### Random Route (Development)

```yaml
applications:
  - name: my-app
    random-route: true
```

### Health Check Configuration

```yaml
applications:
  - name: my-app
    health-check-type: http
    health-check-http-endpoint: /health
    timeout: 180
```

Health check types:
- `http` - HTTP request to endpoint (recommended)
- `port` - TCP connection to port
- `process` - Check if process is running

### Stack Configuration

```yaml
applications:
  - name: my-app
    stack: cflinuxfs4
```

Check available stacks: `cf stacks`

## Multi-Application Manifests

Deploy multiple applications from one manifest:

```yaml
---
applications:
  - name: my-app-web
    memory: 512M
    instances: 2
    buildpacks:
      - python_buildpack
    command: gunicorn app:app
    services:
      - my-database
    routes:
      - route: my-app.app.cloud.gov

  - name: my-app-worker
    memory: 256M
    instances: 1
    buildpacks:
      - python_buildpack
    command: python worker.py
    no-route: true
    services:
      - my-database
```

## Variable Substitution

### Using Variables in Manifest

```yaml
---
applications:
  - name: ((app_name))
    memory: ((memory))
    instances: ((instances))
    env:
      DATABASE_URL: ((database_url))
```

### Variables File (vars.yml)

```yaml
app_name: my-production-app
memory: 1G
instances: 3
database_url: postgres://...
```

### Deploying with Variables

```bash
cf push --vars-file vars.yml

# Or inline variables
cf push --var app_name=my-app --var memory=512M
```

### Environment-Specific Manifests

```
manifest.yml           # Base manifest
vars-dev.yml           # Development variables
vars-staging.yml       # Staging variables
vars-prod.yml          # Production variables
```

Deploy to specific environment:

```bash
cf push -f manifest.yml --vars-file vars-prod.yml
```

## Complete Production Example

```yaml
---
applications:
  - name: production-app
    memory: 1G
    disk_quota: 2G
    instances: 3
    buildpacks:
      - python_buildpack
    command: gunicorn app:app --bind 0.0.0.0:$PORT --workers 4
    stack: cflinuxfs4
    health-check-type: http
    health-check-http-endpoint: /health
    timeout: 180
    env:
      ENVIRONMENT: production
      LOG_LEVEL: info
      LOG_FORMAT: json
    services:
      - prod-database
      - prod-s3-bucket
      - prod-redis
    routes:
      - route: myapp.app.cloud.gov
      - route: api.myapp.gov
```

## Manifest Inheritance (Advanced)

Create a base manifest and extend it:

### Base Manifest (manifest-base.yml)

```yaml
---
applications:
  - name: ((app_name))
    memory: ((memory))
    buildpacks:
      - python_buildpack
    services:
      - ((database_service))
```

### Environment Override

```bash
cf push -f manifest-base.yml --vars-file vars-prod.yml
```

## Common Patterns

### Web + Worker Pattern

```yaml
---
applications:
  - name: myapp-web
    memory: 512M
    instances: 2
    command: gunicorn app:app
    services:
      - shared-database
      - shared-redis
    routes:
      - route: myapp.app.cloud.gov

  - name: myapp-worker
    memory: 256M
    instances: 1
    command: celery -A tasks worker
    no-route: true
    services:
      - shared-database
      - shared-redis
```

### API + Frontend Pattern

```yaml
---
applications:
  - name: myapp-api
    memory: 512M
    instances: 2
    buildpacks:
      - python_buildpack
    services:
      - api-database
    routes:
      - route: api.myapp.app.cloud.gov

  - name: myapp-frontend
    memory: 128M
    instances: 2
    buildpacks:
      - staticfile_buildpack
    env:
      API_URL: https://api.myapp.app.cloud.gov
    routes:
      - route: myapp.app.cloud.gov
```

## Troubleshooting

### Manifest Validation

```bash
# Dry run to validate manifest
cf push --dry-run -f manifest.yml
```

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| "App not found" | Name mismatch | Verify `name` in manifest |
| "Buildpack not found" | Typo in buildpack name | Check `cf buildpacks` for valid names |
| "Service not found" | Service doesn't exist | Create service before push |
| "Route already exists" | Route used by another app | Choose unique route or use existing app |

## References

- [Cloud Foundry Manifest Documentation](https://docs.cloudfoundry.org/devguide/deploy-apps/manifest.html)
- [cloud.gov Deployment Guide](https://docs.cloud.gov/platform/deployment/)
- [Manifest Attribute Reference](https://docs.cloudfoundry.org/devguide/deploy-apps/manifest-attributes.html)
