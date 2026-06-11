# CF Troubleshoot Skill

This skill provides expert guidance for diagnosing and resolving common cloud.gov and Cloud Foundry issues.

## When to Use This Skill

- User reports an application error or unexpected behavior
- Application fails to start or crashes repeatedly
- Deployment fails with an error message
- Service binding or connectivity issues
- Performance problems or resource constraints

## Diagnostic Workflow

### Step 1: Gather Basic Information

Always start by collecting:

```bash
# Check application status
cf app <APP_NAME>

# View recent logs
cf logs <APP_NAME> --recent

# Check environment variables
cf env <APP_NAME>

# List bound services
cf services
```

### Step 2: Identify Error Category

Categorize the issue based on symptoms:

| Symptom | Category | Start With |
|---------|----------|------------|
| App won't start | Startup failure | Logs, health check config |
| App crashes after running | Runtime error | Logs, memory usage |
| Deployment fails | Push failure | Build logs, manifest |
| Can't reach app | Routing issue | Routes, domains |
| Service connection fails | Binding issue | VCAP_SERVICES, network |
| Slow performance | Resource issue | Memory, instances, scaling |

## Common Issues and Solutions

### Application Startup Failures

#### "Instance never healthy" / Health Check Timeout

**Symptoms:**
- `cf push` hangs then fails
- Logs show app starting but then killed
- Error: "Process has crashed" or "failed to accept connections"

**Diagnosis:**
```bash
# Check health check configuration
cf get-health-check <APP_NAME>

# Look for port binding in logs
cf logs <APP_NAME> --recent | grep -i "listen\|port\|bind"
```

**Solutions:**
1. **App not listening on correct port** - Bind to `$PORT` environment variable, not hardcoded port
   ```python
   port = int(os.environ.get('PORT', 8080))
   ```

2. **Health check type mismatch** - Use `process` type for workers, `http` for web apps
   ```bash
   cf set-health-check <APP_NAME> process
   ```

3. **Startup too slow** - Increase timeout in manifest
   ```yaml
   timeout: 180
   ```

4. **Health check endpoint missing** - Ensure `/health` or configured endpoint exists and returns 200

#### "Staging failed" / Buildpack Errors

**Symptoms:**
- Push fails during staging phase
- "None of the buildpacks detected a compatible application"

**Diagnosis:**
```bash
# View staging logs (run during push)
cf logs <APP_NAME>
```

**Solutions:**
1. **Missing dependency file** - Ensure `requirements.txt`, `package.json`, `Gemfile`, etc. exists
2. **Wrong buildpack** - Explicitly specify in manifest:
   ```yaml
   buildpacks:
     - python_buildpack
   ```
3. **Incompatible version** - Check buildpack supports your runtime version

#### Out of Memory (OOM) Crashes

**Symptoms:**
- App starts then crashes
- Logs show "Memory quota exceeded" or "OOMKilled"
- `cf app` shows instances crashing/restarting

**Diagnosis:**
```bash
# Check memory allocation vs usage
cf app <APP_NAME>

# Look for memory errors in logs
cf logs <APP_NAME> --recent | grep -i "memory\|oom\|killed"
```

**Solutions:**
1. **Increase memory allocation:**
   ```bash
   cf scale <APP_NAME> -m 1G
   ```

2. **Optimize application memory usage:**
   - Reduce worker processes/threads
   - Implement proper garbage collection
   - Check for memory leaks

3. **For Java apps** - Set JVM heap size:
   ```yaml
   env:
     JAVA_OPTS: "-Xmx512m -Xms256m"
   ```

### Service Connectivity Issues

#### Database Connection Failures

**Symptoms:**
- "Connection refused" or "Connection timeout"
- "Authentication failed"
- App works locally but fails on cloud.gov

**Diagnosis:**
```bash
# Verify service is bound
cf services

# Check credentials are in environment
cf env <APP_NAME> | grep -A 30 VCAP_SERVICES

# SSH into container to test connectivity
cf ssh <APP_NAME> -c "nc -zv <db-host> <db-port>"
```

**Solutions:**
1. **Service not bound** - Bind and restage:
   ```bash
   cf bind-service <APP_NAME> <SERVICE_NAME>
   cf restage <APP_NAME>
   ```

2. **Credentials not refreshed** - Restage after binding:
   ```bash
   cf restage <APP_NAME>
   ```

3. **Parsing VCAP_SERVICES incorrectly** - Use proper JSON parsing:
   ```python
   import json, os
   vcap = json.loads(os.environ.get('VCAP_SERVICES', '{}'))
   ```

4. **Connection pool exhaustion** - Configure connection limits and timeouts

#### S3 Access Denied

**Symptoms:**
- "Access Denied" when accessing bucket
- "NoSuchBucket" errors
- Credentials appear correct but operations fail

**Diagnosis:**
```bash
# Check S3 credentials in environment
cf env <APP_NAME> | grep -A 20 s3
```

**Solutions:**
1. **Using wrong bucket name** - Get from `VCAP_SERVICES`, don't hardcode
2. **Wrong region** - Use region from service credentials
3. **Credentials expired** - Rebind service:
   ```bash
   cf unbind-service <APP_NAME> <S3_SERVICE>
   cf bind-service <APP_NAME> <S3_SERVICE>
   cf restage <APP_NAME>
   ```

### Routing and Network Issues

#### "404 Not Found" on Valid Routes

**Symptoms:**
- App is running but returns 404
- Route appears correct in `cf routes`

**Diagnosis:**
```bash
# List routes for the app
cf app <APP_NAME> | grep routes

# Check all routes in space
cf routes
```

**Solutions:**
1. **Route not mapped** - Map the route:
   ```bash
   cf map-route <APP_NAME> app.cloud.gov --hostname <HOSTNAME>
   ```

2. **Old route from failed deployment** - Remap route:
   ```bash
   cf unmap-route <OLD_APP> app.cloud.gov --hostname <HOSTNAME>
   cf map-route <NEW_APP> app.cloud.gov --hostname <HOSTNAME>
   ```

#### External API Calls Blocked

**Symptoms:**
- Timeout when calling external services
- "Connection refused" to external APIs
- Works locally, fails on cloud.gov

**Diagnosis:**
```bash
# Test from container
cf ssh <APP_NAME> -c "curl -v https://api.example.com"
```

**Solutions:**
1. **Egress restricted** - Contact cloud.gov support to allow external access
2. **Firewall on external service** - Whitelist cloud.gov IP ranges
3. **DNS resolution failure** - Verify hostname resolves correctly

### Deployment Failures

#### "App has not started" After Push

**Symptoms:**
- `cf push` completes but app won't start
- Multiple instances show as "crashed"

**Diagnosis:**
```bash
# Get detailed crash information
cf events <APP_NAME>

# Check recent logs
cf logs <APP_NAME> --recent
```

**Solutions:**
1. **Missing start command** - Add Procfile or command in manifest:
   ```yaml
   command: gunicorn app:app
   ```

2. **Missing environment variables** - Set required vars:
   ```bash
   cf set-env <APP_NAME> SECRET_KEY "value"
   cf restage <APP_NAME>
   ```

3. **File permissions** - Ensure scripts are executable in repo

#### Blue-Green Deployment Stuck

**Symptoms:**
- Old app still receiving traffic
- New app running but not accessible
- Routes in inconsistent state

**Diagnosis:**
```bash
# Check routes on both apps
cf app <APP_NAME>
cf app <APP_NAME>-venerable

# List all routes
cf routes
```

**Solutions:**
1. **Manually complete the cutover:**
   ```bash
   # Map route to new app
   cf map-route <APP_NAME> app.cloud.gov --hostname <HOSTNAME>
   
   # Unmap from old app
   cf unmap-route <APP_NAME>-venerable app.cloud.gov --hostname <HOSTNAME>
   
   # Delete old app
   cf delete <APP_NAME>-venerable -f
   ```

### Performance Issues

#### Slow Response Times

**Symptoms:**
- High latency on requests
- Timeouts under load
- Performance degradation over time

**Diagnosis:**
```bash
# Check instance count and resource usage
cf app <APP_NAME>

# Look for slow queries or operations in logs
cf logs <APP_NAME> --recent
```

**Solutions:**
1. **Scale horizontally:**
   ```bash
   cf scale <APP_NAME> -i 3
   ```

2. **Scale vertically:**
   ```bash
   cf scale <APP_NAME> -m 1G
   ```

3. **Optimize database queries** - Add indexes, reduce N+1 queries

4. **Add caching** - Use Redis service for frequently accessed data

#### Instances Restarting Frequently

**Symptoms:**
- `cf app` shows instances cycling
- Intermittent 502/503 errors
- Logs show repeated startup messages

**Diagnosis:**
```bash
# Check crash events
cf events <APP_NAME>

# Monitor logs in real-time
cf logs <APP_NAME>
```

**Solutions:**
1. **Memory leak** - Profile application, increase memory temporarily
2. **Unhandled exceptions** - Add proper error handling
3. **Health check issues** - Adjust health check settings

## Log Analysis Patterns

### Key Log Patterns to Search

```bash
# Application errors
cf logs <APP_NAME> --recent | grep -i "error\|exception\|failed"

# Memory issues
cf logs <APP_NAME> --recent | grep -i "memory\|oom\|heap"

# Connection issues
cf logs <APP_NAME> --recent | grep -i "connection\|timeout\|refused"

# Startup issues
cf logs <APP_NAME> --recent | grep -i "listen\|port\|bind\|starting"

# Database issues
cf logs <APP_NAME> --recent | grep -i "database\|postgres\|mysql\|query"
```

### Log Timestamp Analysis

```bash
# Get logs from specific time window
cf logs <APP_NAME> --recent 2>&1 | grep "2026-01-16T14:"
```

## Escalation Paths

If standard troubleshooting doesn't resolve the issue:

1. **Platform issues** - Check [cloud.gov status page](https://cloudgov.statuspage.io/)
2. **Support request** - File ticket at cloud.gov support with:
   - App name and space
   - Error messages
   - Steps already tried
   - Timeline of when issue started
3. **Emergency** - Use cloud.gov emergency contact for production outages

## Quick Reference Commands

```bash
# Full diagnostic dump
cf app <APP_NAME> && cf logs <APP_NAME> --recent && cf events <APP_NAME>

# Restart without restaging
cf restart <APP_NAME>

# Restart with fresh staging
cf restage <APP_NAME>

# SSH into running container
cf ssh <APP_NAME>

# Run one-off task
cf run-task <APP_NAME> --command "python manage.py migrate"

# View all apps in space
cf apps

# View service details
cf service <SERVICE_NAME>
```
