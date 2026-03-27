---
applyTo: "**/manifest*.yml,**/Procfile,**/.cfignore,**/.profile"
---

# Cloud.gov Deployment Instructions

This document provides guidance for deploying applications to cloud.gov using the Cloud Foundry CLI.

## Overview

Cloud.gov uses `cf push` as the primary deployment command. The platform builds your application using buildpacks and runs it in isolated containers.

## Prerequisites

- Cloud Foundry CLI installed (`cf` command available)
- Authenticated to cloud.gov: `cf login -a api.fr.cloud.gov --sso`
- Targeted the correct org and space: `cf target -o <ORG> -s <SPACE>`

## Basic Deployment Workflow

### 1. Prepare Your Application

Ensure your application follows these principles:

- **Stateless**: Don't write to local filesystem for persistent data
- **12-Factor**: Follow [Twelve-Factor App](https://12factor.net/) methodology
- **Quick startup**: Applications should start within 60 seconds
- **Health endpoint**: Implement `/health` or similar for monitoring

### 2. Create Required Files

#### manifest.yml

```yaml
---
applications:
  - name: my-application
    memory: 512M
    instances: 2
    buildpacks:
      - python_buildpack
    env:
      ENVIRONMENT: production
    services:
      - my-database
    routes:
      - route: my-app.app.cloud.gov
```

#### .cfignore

Exclude unnecessary files from deployment:

```
.git/
.gitignore
node_modules/
__pycache__/
*.pyc
.env
.env.*
tests/
docs/
*.md
!README.md
.vscode/
.idea/
```

#### .profile (optional)

Shell script run before app starts:

```bash
#!/bin/bash
# Set runtime environment variables
export APP_STARTED_AT=$(date)
```

#### Procfile (optional)

Define process types:

```
web: gunicorn app:app --bind 0.0.0.0:$PORT
worker: python worker.py
```

### 3. Deploy the Application

```bash
# Basic deployment
cf push

# Deploy with specific manifest
cf push -f manifest.yml

# Deploy without starting (useful for binding services first)
cf push --no-start

# Deploy with a specific app name (overrides manifest)
cf push my-app-name
```

### 4. Verify Deployment

```bash
# Check application status
cf app <APP_NAME>

# View recent logs
cf logs <APP_NAME> --recent

# Stream live logs
cf logs <APP_NAME>

# Check application health
curl https://<APP_NAME>.app.cloud.gov/health
```

## Deployment Strategies

### Blue-Green Deployment

Zero-downtime deployment by running two versions simultaneously:

```bash
# Deploy new version with temporary name
cf push my-app-venerable -f manifest.yml

# Map production route to new version
cf map-route my-app-venerable app.cloud.gov --hostname my-app

# Unmap route from old version
cf unmap-route my-app app.cloud.gov --hostname my-app

# Delete old version when confident
cf delete my-app -f

# Rename new version to production name
cf rename my-app-venerable my-app
```

### Rolling Deployment

Built-in rolling updates (CF CLI v7+):

```bash
cf push my-app --strategy rolling
```

### Canary Deployment

Route a percentage of traffic to new version:

```bash
# Deploy canary version
cf push my-app-canary -f manifest.yml

# Map same route (traffic splits across instances)
cf map-route my-app-canary app.cloud.gov --hostname my-app

# Monitor canary, then either promote or rollback
```

## Scaling Applications

### Horizontal Scaling (Instances)

```bash
# Scale to 3 instances
cf scale <APP_NAME> -i 3

# Scale to 1 instance (development only)
cf scale <APP_NAME> -i 1
```

### Vertical Scaling (Memory/Disk)

```bash
# Increase memory
cf scale <APP_NAME> -m 1G

# Increase disk
cf scale <APP_NAME> -k 2G

# Scale both
cf scale <APP_NAME> -m 1G -k 2G
```

**Best Practice**: Use 2+ instances for production to ensure availability during platform updates and restarts.

## Buildpacks

Cloud.gov supports these standard buildpacks:

| Language | Buildpack Name |
|----------|----------------|
| Python | `python_buildpack` |
| Node.js | `nodejs_buildpack` |
| Ruby | `ruby_buildpack` |
| Java | `java_buildpack` |
| Go | `go_buildpack` |
| PHP | `php_buildpack` |
| .NET Core | `dotnet_core_buildpack` |
| Static files | `staticfile_buildpack` |
| Binary | `binary_buildpack` |

List available buildpacks:

```bash
cf buildpacks
```

### Multi-Buildpack Applications

```yaml
applications:
  - name: my-app
    buildpacks:
      - nodejs_buildpack
      - python_buildpack
```

## Application Lifecycle Commands

```bash
# Restart application (uses existing droplet)
cf restart <APP_NAME>

# Restage application (rebuilds with current buildpack)
cf restage <APP_NAME>

# Stop application
cf stop <APP_NAME>

# Start stopped application
cf start <APP_NAME>

# Delete application
cf delete <APP_NAME>

# Delete without confirmation
cf delete <APP_NAME> -f
```

## Environment Variables

```bash
# Set environment variable
cf set-env <APP_NAME> DATABASE_URL postgres://...

# View all environment variables
cf env <APP_NAME>

# Unset environment variable
cf unset-env <APP_NAME> DATABASE_URL
```

**Note**: After changing environment variables, restart or restage the application.

## Route Management

```bash
# Map a route to an application
cf map-route <APP_NAME> app.cloud.gov --hostname my-app

# Unmap a route
cf unmap-route <APP_NAME> app.cloud.gov --hostname my-app

# View routes
cf routes

# Create a route without mapping
cf create-route <SPACE> app.cloud.gov --hostname my-app
```

## Troubleshooting

### Application Crashes

```bash
# View crash logs
cf logs <APP_NAME> --recent

# Check events
cf events <APP_NAME>

# SSH into running container (if available)
cf ssh <APP_NAME>
```

### Common Issues

| Issue | Possible Cause | Solution |
|-------|----------------|----------|
| App crashes on start | Missing dependencies | Check buildpack output, verify requirements files |
| Out of memory | Memory limit too low | Increase memory with `cf scale -m` |
| Health check fails | App takes too long to start | Configure longer health check timeout |
| Cannot connect to service | Egress rules blocking | Update space egress settings |

### Health Check Configuration

```yaml
applications:
  - name: my-app
    health-check-type: http
    health-check-http-endpoint: /health
    timeout: 180  # seconds to wait for app to start
```

## References

- [cloud.gov Deployment Documentation](https://docs.cloud.gov/platform/deployment/)
- [Cloud Foundry Deployment Guide](https://docs.cloudfoundry.org/devguide/deploy-apps/)
- [Twelve-Factor App](https://12factor.net/)
- [cloud.gov Production Ready Guide](https://docs.cloud.gov/platform/deployment/production-ready/)
