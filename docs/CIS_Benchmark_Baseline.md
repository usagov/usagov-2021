# CIS Benchmark Baseline Report

**Generated:** January 14, 2026  
**Tool:** Docker Bench for Security v1.6.0  
**CIS Benchmark:** v1.6.0  
**Sections Scanned:** 4 (Container Images) and 5 (Container Runtime)  
**Containers Analyzed:** cms, cypress, minio, node, database, cache  

**Overall Score:** 1/44 checks

---

## Executive Summary

| Status | Count | Description |
|--------|-------|-------------|
| ✅ PASS | 14 | Security requirement met |
| ⚠️ WARN | 20 | Security concern - needs review |
| ℹ️ INFO | 6 | Informational - manual review needed |
| 📝 NOTE | 4 | Manual check required |

### Critical Findings
1. **All containers running as root** (4.1)
2. **No AppArmor/SELinux security profiles** (5.2, 5.3)
3. **No resource limits** (5.11, 5.12, 5.29)
4. **No health checks** (5.27)
5. **Containers can acquire additional privileges** (5.26)
6. **Privileged ports exposed** (5.8)

---

## Section 4: Container Images and Build File

### ✅ PASS (1/12 checks)

#### 4.5 - Content trust for Docker is Enabled
**Status:** PASS ✅  
**Finding:** DOCKER_CONTENT_TRUST=1 is properly set  
**Action:** None required - keep this enabled

---

### ⚠️ WARN (2/12 checks)

#### 4.1 - Ensure that a user for the container has been created
**Status:** WARN ⚠️  
**Priority:** HIGH  
**Finding:**
- cms - Running as root
- cypress - Running as root
- minio - Running as root
- database - Running as root
- cache - Running as root

**Impact:** Running as root provides full system privileges inside container. If compromised, attacker has root access.

**Recommendation:**
```dockerfile
# In each Dockerfile, add before CMD/ENTRYPOINT:
USER www-data  # or appropriate non-root user
```

**Acceptable:** Database container may need root for initialization, but should drop privileges  
**Action Required:** Review and fix CMS, cypress, minio, node containers  
**Owner:** DevOps Team  
**Target:** Q1 2026

---

#### 4.6 - Ensure HEALTHCHECK instructions have been added to container images
**Status:** WARN ⚠️  
**Priority:** MEDIUM  
**Finding:** No HEALTHCHECK in images:
- usagov-2021-cypress:latest
- usagov-2021-node:latest
- usagov-2021-composer:latest
- usagov-2021-minio:latest
- usagov-2021-cache:latest
- External images (mariadb, postgres, snyk, validator, etc.)

**Impact:** Docker cannot detect unhealthy containers automatically. Failed services may continue running.

**Recommendation:**
```dockerfile
# CMS Dockerfile
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
  CMD curl -f http://localhost:80/ || exit 1

# Database Dockerfile  
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
  CMD mysqladmin ping -h localhost || exit 1

# Cache Dockerfile
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD redis-cli ping || exit 1
```

**Action Required:** Add HEALTHCHECK to all custom Dockerfiles  
**Owner:** DevOps Team  
**Target:** Sprint 2

---

### ℹ️ INFO (3/12 checks)

#### 4.7 - Update instructions are not used alone in Dockerfile
**Status:** INFO ℹ️  
**Priority:** LOW  
**Finding:**
- usagov-2021-cache:latest - Has update instruction
- snyk/snyk:docker - Has update instruction
- app-php:latest - Has update instruction

**Recommendation:** When using `apt-get update` or similar, combine with install in same RUN command to avoid caching issues:
```dockerfile
RUN apt-get update && apt-get install -y package && rm -rf /var/lib/apt/lists/*
```

**Action Required:** Review during next Dockerfile refactor  
**Owner:** DevOps Team  
**Target:** Q2 2026

---

#### 4.9 - Ensure COPY is used instead of ADD in Dockerfiles
**Status:** INFO ℹ️  
**Priority:** LOW  
**Finding:** ADD instruction found in:
- usagov-2021-cypress:latest
- External images (validator, mariadb, php-dumper)

**Reason:** ADD has implicit tar extraction and URL download capabilities. COPY is more explicit and safer.

**Recommendation:** Use COPY unless you specifically need ADD's features  
**Action Required:** Review custom Dockerfiles  
**Owner:** DevOps Team  
**Target:** Q2 2026

---

### 📝 NOTE (6/12 manual checks)

The following require manual review:
- 4.2 - Ensure containers use only trusted base images
- 4.3 - Ensure unnecessary packages are not installed
- 4.4 - Ensure images are scanned and rebuilt with security patches
- 4.8 - Ensure setuid and setgid permissions are removed
- 4.10 - Ensure secrets are not stored in Dockerfiles
- 4.11 - Ensure only verified packages are installed
- 4.12 - Ensure all signed artifacts are validated

**Action Required:** Manual security audit  
**Owner:** Security Team  
**Target:** Q1 2026

---

## Section 5: Container Runtime

### ✅ PASS (13/32 checks)

- 5.1 - Swarm mode not enabled ✅
- 5.5 - No privileged containers ✅
- 5.6 - No sensitive host directories mounted ✅
- 5.7 - No sshd running in containers ✅
- 5.10 - Host network namespace not shared ✅
- 5.15 - Restart policy set appropriately ✅
- 5.16 - Host process namespace not shared ✅
- 5.17 - Host IPC namespace not shared ✅
- 5.18 - Host devices not exposed ✅
- 5.20 - Mount propagation not shared ✅
- 5.21 - Host UTS namespace not shared ✅
- 5.22 - Seccomp profile enabled ✅
- 5.25 - Cgroup usage confirmed ✅
- 5.30 - Not using docker0 bridge ✅
- 5.31 - Host user namespaces not shared ✅
- 5.32 - Docker socket not mounted ✅

**Action:** None required - maintain current configuration

---

### ⚠️ WARN (18/32 checks)

#### 5.2 - AppArmor Profile is enabled (if applicable)
**Status:** WARN ⚠️  
**Priority:** MEDIUM  
**Finding:** No AppArmor profiles on:
- cms, cypress, minio, node, database, cache

**Platform:** macOS - AppArmor not available on Docker Desktop for Mac

**Recommendation:** 
- On macOS: This is expected and acceptable
- On Linux production: Enable AppArmor profiles

**Action Required:** Document as acceptable for local dev (macOS)  
**Production Action:** Verify Linux hosts have AppArmor enabled  
**Owner:** DevOps Team  
**Status:** ACCEPTABLE (macOS limitation)

---

#### 5.3 - SELinux security options are set (if applicable)
**Status:** WARN ⚠️  
**Priority:** MEDIUM  
**Finding:** No SELinux options on:
- cms, cypress, minio, node, database, cache

**Platform:** macOS - SELinux not available on Docker Desktop for Mac

**Recommendation:**
- On macOS: This is expected and acceptable
- On Linux production: Enable SELinux with `--security-opt label=type:container_t`

**Action Required:** Document as acceptable for local dev (macOS)  
**Production Action:** Verify Linux hosts have SELinux enabled  
**Owner:** DevOps Team  
**Status:** ACCEPTABLE (macOS limitation)

---

#### 5.4 - Linux kernel capabilities are restricted within containers
**Status:** WARN ⚠️  
**Priority:** MEDIUM  
**Finding:**
- database - Has CAP_SYS_NICE capability added

**Reason:** MariaDB may use this for process priority management

**Recommendation:** Review if CAP_SYS_NICE is actually needed. If so, document reason.

**Action Required:** Research and document necessity  
**Owner:** DevOps Team  
**Target:** Sprint 3

---

#### 5.8 - Privileged ports are not mapped within containers
**Status:** WARN ⚠️  
**Priority:** MEDIUM  
**Finding:**
- cms - Port 80 (HTTP)
- cms - Port 443 (HTTPS)

**Reason:** Web servers commonly use ports 80/443

**Recommendation:**
- Development: Acceptable - standard ports for web services
- Production: Use non-privileged ports (>1024) and reverse proxy, OR run with explicit capability

**Action Required:** Document as acceptable for development  
**Owner:** DevOps Team  
**Status:** ACCEPTABLE (web server standard ports)

---

#### 5.9 - Only needed ports are open on the container
**Status:** WARN ⚠️ (Manual)  
**Priority:** LOW  
**Finding:** Ports in use:
- cms: 80, 443
- database: 3306
- cache: 6379

**Recommendation:** Manual review - all appear to be necessary service ports

**Action Required:** Document port usage justification  
**Owner:** DevOps Team  
**Status:** LIKELY ACCEPTABLE

---

#### 5.11 - Memory usage for containers is limited
**Status:** WARN ⚠️  
**Priority:** HIGH  
**Finding:** No memory limits on:
- cms, cypress, minio, node, database, cache

**Impact:** Containers can consume unlimited memory, potentially causing:
- Host system instability
- OOM killer terminating random processes
- Denial of service

**Recommendation:**
```yaml
# docker-compose.yml
services:
  cms:
    deploy:
      resources:
        limits:
          memory: 2G
        reservations:
          memory: 512M
  
  database:
    deploy:
      resources:
        limits:
          memory: 2G
        reservations:
          memory: 1G
  
  cache:
    deploy:
      resources:
        limits:
          memory: 512M
        reservations:
          memory: 128M
```

**Action Required:** Add memory limits based on actual usage patterns  
**Owner:** DevOps Team  
**Target:** Sprint 2  
**Priority:** HIGH

---

#### 5.12 - CPU priority is set appropriately on containers
**Status:** WARN ⚠️  
**Priority:** HIGH  
**Finding:** No CPU limits on:
- cms, cypress, minio, node, database, cache

**Impact:** Single container can monopolize CPU, causing:
- Poor performance for other containers
- Unresponsive system
- Unpredictable resource allocation

**Recommendation:**
```yaml
# docker-compose.yml
services:
  cms:
    deploy:
      resources:
        limits:
          cpus: '2'
        reservations:
          cpus: '0.5'
  
  database:
    deploy:
      resources:
        limits:
          cpus: '2'
        reservations:
          cpus: '1'
```

**Action Required:** Add CPU limits based on actual usage patterns  
**Owner:** DevOps Team  
**Target:** Sprint 2  
**Priority:** HIGH

---

#### 5.13 - Container's root filesystem is mounted as read only
**Status:** WARN ⚠️  
**Priority:** MEDIUM  
**Finding:** Root FS is read-write on:
- cms, cypress, minio, node, database, cache

**Impact:** Attackers can modify container filesystem

**Recommendation:**
```yaml
# docker-compose.yml - only where possible
services:
  cms:
    read_only: true
    tmpfs:
      - /tmp
      - /var/run
```

**Caveat:** Many applications need writable filesystem. May not be feasible for all containers.

**Action Required:** Evaluate on per-container basis  
**Owner:** DevOps Team  
**Target:** Q2 2026  
**Priority:** MEDIUM

---

#### 5.14 - Incoming container traffic is bound to a specific host interface
**Status:** WARN ⚠️  
**Priority:** MEDIUM  
**Finding:** Ports bound to 0.0.0.0 (all interfaces):
- cms: 80, 443
- database: 3306
- cache: 6379

**Impact:** Services accessible from all network interfaces

**Recommendation:**
```yaml
# docker-compose.yml
services:
  database:
    ports:
      - "127.0.0.1:3306:3306"  # Only localhost
  
  cache:
    ports:
      - "127.0.0.1:6379:6379"  # Only localhost
```

**Action Required:**
- CMS: Keep 0.0.0.0 (needs external access)
- Database/Cache: Bind to 127.0.0.1 if only internal access needed

**Owner:** DevOps Team  
**Target:** Sprint 3  
**Priority:** MEDIUM

---

#### 5.26 - Container is restricted from acquiring additional privileges
**Status:** WARN ⚠️  
**Priority:** HIGH  
**Finding:** Privileges not restricted on:
- cms, cypress, minio, node, database, cache

**Impact:** Processes can gain additional privileges via setuid, setgid, etc.

**Recommendation:**
```yaml
# docker-compose.yml
services:
  cms:
    security_opt:
      - no-new-privileges:true
```

**Action Required:** Add to all containers  
**Owner:** DevOps Team  
**Target:** Sprint 2  
**Priority:** HIGH

---

#### 5.27 - Container health is checked at runtime
**Status:** WARN ⚠️  
**Priority:** MEDIUM  
**Finding:** No health checks on:
- cypress, minio, node, database, cache

**Note:** CMS has health check configured

**Impact:** Docker cannot detect and restart unhealthy containers

**Recommendation:** See 4.6 - Add HEALTHCHECK to Dockerfiles or docker-compose.yml

**Action Required:** Add health checks  
**Owner:** DevOps Team  
**Target:** Sprint 2  
**Priority:** MEDIUM

---

#### 5.29 - PIDs cgroup limit is used
**Status:** WARN ⚠️  
**Priority:** MEDIUM  
**Finding:** No PIDs limit on:
- cms, cypress, minio, node, database, cache

**Impact:** Fork bombs or runaway processes can exhaust PIDs

**Recommendation:**
```yaml
# docker-compose.yml
services:
  cms:
    pids_limit: 100
  
  database:
    pids_limit: 200
```

**Action Required:** Set appropriate PIDs limits  
**Owner:** DevOps Team  
**Target:** Sprint 3  
**Priority:** MEDIUM

---

### ℹ️ INFO (3/32 checks)

#### 5.19 - Default ulimit is overwritten at runtime if needed
**Status:** INFO ℹ️  
**Priority:** LOW  
**Finding:** No custom ulimits on any container

**Recommendation:** Review if custom ulimits are needed. Usually defaults are fine.

**Action Required:** None unless specific issues arise  
**Status:** ACCEPTABLE

---

#### 5.28 - Docker commands use latest version of their image
**Status:** INFO ℹ️  
**Priority:** LOW  
**Recommendation:** Use specific version tags instead of `:latest`

**Action Required:** Pin versions in production  
**Owner:** DevOps Team  
**Target:** Q2 2026

---

### 📝 NOTE (2/32 manual checks)

- 5.23 - Docker exec commands not used with privileged option
- 5.24 - Docker exec commands not used with user=root option

**Action Required:** Team awareness and code review  
**Owner:** All Developers

---

## Priority Matrix

### 🔴 HIGH Priority (Fix in Sprint 2)

| Check | Issue | Impact | Containers |
|-------|-------|--------|------------|
| 4.1 | Running as root | Full system access if compromised | cms, cypress, minio, database, cache |
| 5.11 | No memory limits | System instability, OOM issues | All containers |
| 5.12 | No CPU limits | Performance degradation | All containers |
| 5.26 | Can acquire privileges | Privilege escalation possible | All containers |

### 🟡 MEDIUM Priority (Fix in Sprint 3)

| Check | Issue | Impact | Containers |
|-------|-------|--------|------------|
| 4.6 | No HEALTHCHECK | Undetected failures | Custom images |
| 5.4 | Extra capabilities | Unnecessary privileges | database |
| 5.8 | Privileged ports | Standard web ports | cms |
| 5.13 | Root FS writable | Filesystem modification | All containers |
| 5.14 | Ports on all interfaces | Unnecessary exposure | database, cache |
| 5.27 | No runtime health checks | Undetected failures | Most containers |
| 5.29 | No PIDs limit | Fork bomb risk | All containers |

### 🟢 LOW Priority (Review Q2 2026)

| Check | Issue | Impact | Containers |
|-------|-------|--------|------------|
| 4.7 | Update instruction usage | Build cache issues | cache, snyk, app-php |
| 4.9 | ADD vs COPY | Security best practice | cypress |
| 5.9 | Port review | Documentation | All with ports |
| 5.19 | Ulimit defaults | Usually acceptable | All containers |
| 5.28 | Version pinning | Reproducibility | All images |

### ✅ ACCEPTABLE (Document & Monitor)

| Check | Issue | Reason |
|-------|-------|--------|
| 5.2 | No AppArmor | macOS limitation |
| 5.3 | No SELinux | macOS limitation |
| 5.8 | Ports 80/443 | Standard web server ports |

---

## Recommended Action Plan

### Phase 1: Quick Wins (Sprint 2) ✅

1. **Add security-opt for no-new-privileges** (30 min)
   ```yaml
   security_opt:
     - no-new-privileges:true
   ```

2. **Add HEALTHCHECK to Dockerfiles** (2 hours)
   - CMS, database, cache, minio, node

3. **Add resource limits** (1 hour + testing)
   - Memory limits
   - CPU limits
   - PIDs limits

### Phase 2: Security Hardening (Sprint 3) 🔒

1. **Configure non-root users** (4 hours + testing)
   - Review each container
   - Ensure permissions are correct
   - Test functionality

2. **Review Linux capabilities** (2 hours)
   - Document database CAP_SYS_NICE usage
   - Remove if not needed

3. **Bind internal services to localhost** (1 hour)
   - Database: 127.0.0.1
   - Cache: 127.0.0.1

### Phase 3: Advanced Hardening (Q2 2026) 🛡️

1. **Read-only root filesystem** (varies by container)
   - Evaluate feasibility
   - Implement where possible

2. **AppArmor/SELinux for production** (4 hours)
   - Document production security profiles
   - Test on Linux environments

3. **Regular security audits** (ongoing)
   - Monthly CIS scans
   - Compare against baseline
   - Update documentation

---

## Baseline Expectations File

For automated checking, here's the expected results format:

```text
# Expected CIS Scan Results
# Format: check_id|status|container_pattern|reason

# Section 4: Container Images
4.1|WARN|cms|web server - to be fixed Q1 2026
4.1|WARN|database|initialization requires root - acceptable
4.1|WARN|cache|to be fixed Q1 2026
4.1|WARN|minio|to be fixed Q1 2026
4.1|WARN|cypress|test container - acceptable
4.6|WARN|.*|HEALTHCHECK to be added Sprint 2

# Section 5: Container Runtime
5.2|WARN|.*|AppArmor not available on macOS - acceptable
5.3|WARN|.*|SELinux not available on macOS - acceptable
5.4|WARN|database|CAP_SYS_NICE for MariaDB - under review
5.8|WARN|cms|Ports 80/443 standard web ports - acceptable
5.9|WARN|.*|All ports are necessary - acceptable
5.11|WARN|.*|Memory limits to be added Sprint 2
5.12|WARN|.*|CPU limits to be added Sprint 2
5.13|WARN|.*|Read-only FS to be evaluated Q2 2026
5.14|WARN|cms|Web server needs all interfaces - acceptable
5.14|WARN|database|To be bound to localhost Sprint 3
5.14|WARN|cache|To be bound to localhost Sprint 3
5.26|WARN|.*|no-new-privileges to be added Sprint 2
5.27|WARN|.*|Health checks to be added Sprint 2
5.29|WARN|.*|PIDs limit to be added Sprint 3
```

---

## Next Steps

1. ✅ **Review this baseline with team** (This week)
2. ✅ **Get approval on priorities** (This week)
3. 🔄 **Implement Phase 1 changes** (Sprint 2)
4. 🔄 **Create automated comparison script** (Sprint 2)
5. 🔄 **Enable build failure on new issues** (Sprint 3)
6. 🔄 **Implement Phase 2 changes** (Sprint 3)

---

## Monitoring & Maintenance

- **Frequency:** Run CIS scan on every CI/CD build
- **Review:** Monthly security review meeting
- **Updates:** Quarterly baseline review and updates
- **Escalation:** New WARN findings immediately reported to DevOps lead

---

**Document Owner:** DevOps Team  
**Last Updated:** January 14, 2026  
**Next Review:** February 14, 2026
