# CIS Benchmark Analysis and Recommendations

## Executive Summary

This document provides an analysis of the Docker Bench for Security (CIS Benchmarking) implementation currently in use for the USA.gov project, along with recommendations for improvement.

**Current State:**
- Running Docker Bench Security against CMS, WWW, and CRON containers during CircleCI builds
- Only scanning sections 4 (Container Images) and 5 (Container Runtime)
- Currently ignoring failures (exit 0) in the pipeline
- No catalog of expected/acceptable failures
- Results are logged but not systematically reviewed

**Date:** January 14, 2026
**Ticket:** USAGOV-2439

---

## Current Implementation

### 1. What's Being Scanned

The project currently runs Docker Bench for Security with the following configuration:

```bash
docker run ... docker-bench-security -b \
  -c "container_images,container_runtime"
```

This runs only two sections of the CIS Docker Benchmark v1.6.0:

#### Section 4: Container Images and Build File
Tests the security configuration of container images, including:
- **4.1** - Ensure a user for the container has been created (non-root user)
- **4.2** - Ensure containers use only trusted base images (Manual)
- **4.3** - Ensure unnecessary packages are not installed (Manual)
- **4.4** - Ensure images are scanned and rebuilt to include security patches (Manual)
- **4.5** - Ensure Content trust for Docker is Enabled (checks DOCKER_CONTENT_TRUST)
- **4.6** - Ensure HEALTHCHECK instructions have been added to container images
- **4.7** - Ensure update instructions are not used alone in Dockerfile (Manual)
- **4.8** - Ensure setuid and setgid permissions are removed (Manual)
- **4.9** - Ensure COPY is used instead of ADD in Dockerfiles (Manual)
- **4.10** - Ensure secrets are not stored in Dockerfiles (Manual)
- **4.11** - Ensure only verified packages are installed (Manual)
- **4.12** - Ensure all signed artifacts are validated (Manual)

#### Section 5: Container Runtime
Tests the security configuration of running containers (32 checks), including:
- **5.1** - Ensure swarm mode is not enabled if not needed
- **5.2** - Ensure AppArmor Profile is enabled (if applicable)
- **5.3** - Ensure SELinux security options are set (if applicable)
- **5.4** - Ensure Linux kernel capabilities are restricted
- **5.5** - Ensure privileged containers are not used
- **5.6** - Ensure sensitive host directories are not mounted
- **5.7** - Ensure sshd is not run within containers
- **5.8** - Ensure privileged ports are not mapped within containers
- **5.9** - Ensure only needed ports are open (Manual)
- **5.10** - Ensure host's network namespace is not shared
- **5.11** - Ensure memory usage is limited
- **5.12** - Ensure CPU priority is set appropriately
- **5.13** - Ensure container's root filesystem is mounted as read only
- **5.14** - Ensure incoming container traffic is bound to a specific host interface
- **5.15** - Ensure 'on-failure' restart policy is set to '5'
- **5.16** - Ensure host's process namespace is not shared
- **5.17** - Ensure host's IPC namespace is not shared
- **5.18** - Ensure host devices are not directly exposed (Manual)
- **5.19** - Ensure default ulimit is overwritten at runtime if needed (Manual)
- **5.20** - Ensure mount propagation mode is not set to shared
- **5.21** - Ensure host's UTS namespace is not shared
- **5.22** - Ensure default seccomp profile is not disabled
- **5.23** - Ensure docker exec commands are not used with privileged option (Manual/Note)
- **5.24** - Ensure docker exec commands are not used with user=root (Manual/Note)
- **5.25** - Ensure cgroup usage is confirmed
- **5.26** - Ensure container is restricted from acquiring additional privileges
- **5.27** - Ensure container health is checked at runtime
- **5.28** - Ensure Docker commands use latest version of their image (Manual/Info)
- **5.29** - Ensure PIDs cgroup limit is used
- **5.30** - Ensure Docker's default bridge 'docker0' is not used (Manual)
- **5.31** - Ensure host's user namespaces are not shared
- **5.32** - Ensure Docker socket is not mounted inside containers

### 2. What's NOT Being Scanned

The current configuration excludes several CIS benchmark sections:

- **Section 1:** Host Configuration (file permissions, service configurations)
- **Section 2:** Docker daemon configuration
- **Section 3:** Docker daemon configuration files
- **Section 6:** Docker security operations
- **Section 7:** Docker swarm configuration (if applicable)
- **Section 8:** Docker enterprise configuration (if applicable)

### 3. Current Execution Flow

The scans run in three jobs within CircleCI:

1. **cms-build** job:
   - Builds docker-bench-security container
   - Runs scan and logs to `/tmp/results/scan-cms-cis.log`
   - **Always exits 0 (ignoring failures)**

2. **www-waf-lint-test** job:
   - Builds docker-bench-security container
   - Runs scan and logs to `/tmp/results/scan-cms-container-cis.log`
   - **Always exits 0 (ignoring failures)**

3. **cron-build-scan** job:
   - Builds docker-bench-security container
   - Runs scan and logs to `/tmp/results/scan-cron-container-cis.log`
   - **Always exits 0 (ignoring failures)**

---

## Understanding Check Results

Docker Bench for Security reports results in four categories:

### PASS ✓
The check passed. The container/image meets the security requirement.

### WARN ⚠️
The check failed. This indicates a security concern that should be addressed or documented as an accepted risk.

### INFO ℹ️
Informational output. The check found something notable but not necessarily a security issue. May require manual review.

### NOTE 📝
Manual check required. The tool cannot automatically determine compliance. Human review needed.

---

## Problems with Current Implementation

### 1. No Failure Handling
Currently, all CIS scans exit with code 0 regardless of results:

```yaml
exit 0
```

This means:
- Build never fails due to security issues
- No enforcement of security standards
- Issues can accumulate unnoticed
- Defeats the purpose of running the scans

### 2. No Baseline Documentation
There is no documented baseline of:
- Which checks are expected to fail
- Why certain failures are acceptable
- What the current state of each container is
- When issues were first identified

### 3. No Tracking or Alerting
- Results are logged to artifacts but not actively reviewed
- No diff checking between builds
- New security issues can slip through
- No notification when new issues appear

### 4. Limited Scope
Only checking containers, not:
- Docker daemon configuration
- Host system configuration
- File permissions
- Security operations

### 5. Incomplete Context
The scans run with flags that may limit effectiveness:
- `-b` flag removes color (good for CI)
- Missing host mounts that some checks need
- Running in CircleCI environment may affect some checks

---

## Recommendations

### Phase 1: Establish Baseline (Immediate)

#### 1.1 Create Baseline Report
Run the CIS scan locally and document current state:

```bash
# Run scan locally and save output
./bin/scan-container-cis > baseline-$(date +%Y%m%d).log 2>&1

# Review all WARN and INFO items
# Categorize each issue as:
# - To Fix: Security issue that should be resolved
# - Acceptable: Known issue with documented reason
# - To Investigate: Needs research to determine action
```

#### 1.2 Document Acceptable Failures
Create a file `docs/CIS_Benchmark_Baseline.md` listing:
- Each check that currently fails
- Reason why failure is acceptable (if it is)
- Mitigation or compensating controls (if any)
- Owner responsible for reviewing
- Date to re-evaluate

Example format:
```markdown
## 5.4 - Linux kernel capabilities are restricted
**Status:** WARN
**Reason:** CMS container requires CAP_NET_BIND_SERVICE for port 80
**Mitigation:** Container runs as non-root user; only specific capability added
**Owner:** DevOps Team
**Review Date:** 2026-06-01
```

### Phase 2: Implement Automated Checking (Short-term)

#### 2.1 Create Expected Results File
Store expected scan results in repository:

```bash
# File: .circleci/expected-cis-results.txt
# Format: check_id|status|container_pattern
5.4|WARN|cms.*|Capability CAP_NET_BIND_SERVICE added
5.11|WARN|www.*|Memory limits not set for static content server
```

#### 2.2 Create Comparison Script
Develop script to compare actual vs expected results:

```bash
#!/bin/bash
# File: bin/scan-cis-with-benchmark

# Parse docker-bench output
# Compare against expected-cis-results.txt
# Exit 0 if matches expectations
# Exit 1 if new issues found
# Report any new WARNs or changes
```

#### 2.3 Update CircleCI Config
Modify `.circleci/config.yml` to:

```yaml
- run:
    name: Check CIS Benchmarks
    command: |
      # Run scan
      ./bin/scan-container-cis | tee /tmp/results/scan-cms-cis.log

      # Compare against baseline
      ./bin/scan-cis-with-benchmark /tmp/results/scan-cms-cis.log .circleci/expected-cis-results.txt
      CIS_RESULT=$?

      # Fail build if unexpected issues
      exit $CIS_RESULT
```

### Phase 3: Remediation (Medium-term)

Address fixable security issues identified in baseline:

#### 3.1 Low-Hanging Fruit

**Add HEALTHCHECK to Dockerfiles:**
```dockerfile
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost/ || exit 1
```

**Set non-root USER:**
```dockerfile
USER www-data
```

**Add memory/CPU limits to docker-compose:**
```yaml
services:
  cms:
    deploy:
      resources:
        limits:
          memory: 2G
          cpus: '2'
```

#### 3.2 Capability Review
Review and minimize Linux capabilities:
```dockerfile
# Drop all capabilities, add only what's needed
docker run --cap-drop=all --cap-add=NET_BIND_SERVICE ...
```

#### 3.3 Read-only Root Filesystem
Where possible:
```bash
docker run --read-only --tmpfs /tmp ...
```

### Phase 4: Expand Coverage (Long-term)

#### 4.1 Add Host and Daemon Checks
Include sections 1-3 for complete coverage:
```bash
docker run ... docker-bench-security -b \
  -c "host_configuration,docker_daemon_configuration,docker_daemon_files,container_images,container_runtime"
```

#### 4.2 Integrate with Security Dashboard
- Send results to security monitoring system
- Track trends over time
- Alert on regressions

#### 4.3 Automate Remediation
- Create Ansible/Terraform to enforce configurations
- Add pre-commit hooks for Dockerfile linting
- Implement policy-as-code (e.g., Open Policy Agent)

---

## Recommended Approach

### Option A: Strict Enforcement (Recommended)
**Goal:** All checks must PASS or be documented as acceptable

**Pros:**
- High security posture
- Clear accountability
- No security debt accumulation
- Enforced standards

**Cons:**
- May block deployments initially
- Requires team buy-in
- More initial work

**Implementation:**
1. Create baseline with all current failures documented
2. Mark baseline as "acceptable" state
3. Fail builds on any NEW issues
4. Gradually fix issues and update baseline
5. Eventually require all PASS

### Option B: Monitored Progress (Alternative)
**Goal:** Track and report, but don't block deployments

**Pros:**
- Less disruptive
- Easier initial adoption
- Still provides visibility

**Cons:**
- No enforcement
- Issues can accumulate
- May lose momentum

**Implementation:**
1. Create baseline
2. Generate report on every build
3. Set goals for reduction
4. Review quarterly
5. Never fails builds

### Recommended: **Option A with Grace Period**
1. Implement Option A framework
2. Allow 3-month grace period with warnings only
3. Fix high-priority issues during grace period
4. Switch to enforcement after grace period
5. Maintain exception process for legitimate cases

---

## Specific Checks Likely to Flag

Based on the Dockerfiles reviewed, these checks will likely show issues:

### CMS Container (Dockerfile-cms)
- **4.1**: ✓ Likely PASS - Uses www-data user
- **5.4**: ⚠️ Likely WARN - May have extra capabilities
- **5.11**: ⚠️ Likely WARN - No memory limits in Dockerfile
- **5.12**: ⚠️ Likely WARN - No CPU limits in Dockerfile
- **5.13**: ⚠️ Likely WARN - Root FS not read-only

### WWW Container (Dockerfile-www)
- **4.1**: ✓ Likely PASS - Uses nginx user
- **4.6**: ⚠️ Likely WARN - No HEALTHCHECK instruction
- **5.11**: ⚠️ Likely WARN - No memory limits
- **5.12**: ⚠️ Likely WARN - No CPU limits
- **5.13**: ⚠️ Likely WARN - Root FS not read-only

### CRON Container (Dockerfile-cron)
- **4.6**: ⚠️ Likely WARN - No HEALTHCHECK instruction
- **5.11**: ⚠️ Likely WARN - No memory limits
- **5.12**: ⚠️ Likely WARN - No CPU limits

---

## Action Items

### Immediate (This Sprint)
1. ☐ Run scan locally and capture full output
2. ☐ Document current baseline in `docs/CIS_Benchmark_Baseline.md`
3. ☐ Review with team and categorize all issues
4. ☐ Get approval on recommended approach

### Short-term (Next 2 Sprints)
1. ☐ Create expected-results baseline file
2. ☐ Develop comparison script
3. ☐ Update CircleCI to compare results
4. ☐ Add easy fixes (HEALTHCHECK, etc.)

### Medium-term (Next Quarter)
1. ☐ Implement remaining remediations
2. ☐ Add memory/CPU limits where appropriate
3. ☐ Review and minimize capabilities
4. ☐ Enable build failures for new issues

### Long-term (6+ Months)
1. ☐ Expand to include all CIS sections
2. ☐ Integrate with security dashboard
3. ☐ Automate configuration enforcement
4. ☐ Regular reviews and updates

---

## Resources

### Documentation
- [Docker Bench for Security GitHub](https://github.com/docker/docker-bench-security)
- [CIS Docker Benchmark v1.6.0](https://www.cisecurity.org/benchmark/docker/)
- [Docker Security Best Practices](https://docs.docker.com/engine/security/)

### Internal Files
- Scan script: [bin/scan-container-cis](../bin/scan-container-cis)
- CircleCI config: [.circleci/config.yml](../.circleci/config.yml)
- CMS Dockerfile: [.docker/Dockerfile-cms](../.docker/Dockerfile-cms)
- WWW Dockerfile: [.docker/Dockerfile-www](../.docker/Dockerfile-www)
- CRON Dockerfile: [.docker/Dockerfile-cron](../.docker/Dockerfile-cron)

### Tools
- `bin/scan-container-cis` - Run CIS scan
- `bin/build-cis-scanner` - Build scanner container

---

## Conclusion

The current CIS benchmarking implementation provides a foundation but lacks enforcement and tracking. By implementing the recommendations in this document, we can:

1. **Understand** our current security posture through proper baseline documentation
2. **Prevent** security regressions by comparing against expected results
3. **Improve** security over time by systematically addressing issues
4. **Maintain** compliance through automated checks and clear ownership

The recommended "Option A with Grace Period" approach balances security enforcement with practical implementation, giving the team time to address issues while establishing a clear path toward strict compliance.

**Next Step:** Review this analysis with the team and decide on the approach to implement.
