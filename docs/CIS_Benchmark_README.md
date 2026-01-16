# CIS Benchmark Implementation Guide

This directory contains tools and documentation for CIS (Center for Internet Security) benchmark scanning of Docker containers.

## Quick Start

### Run a CIS Scan

```bash
# Build the scanner (first time only)
cd /tmp
git clone --depth 1 https://github.com/docker/docker-bench-security.git
cd docker-bench-security
docker build --no-cache -t docker-bench-security .

# Run the scan
cd /path/to/usagov-2021
./bin/scan-container-cis | tee /tmp/cis-results.log
```

### Check Against Baseline

```bash
# Compare scan results against expected baseline
./bin/check-cis-results /tmp/cis-results.log

# If scan passes:
# ✅ All checks match baseline expectations!

# If scan fails:
# ❌ BUILD FAILED: Unexpected security issues found!
```

## Files and Scripts

### Documentation

- **[CIS_Benchmark_Analysis.md](./CIS_Benchmark_Analysis.md)** - Comprehensive analysis of CIS benchmarking, explaining all checks and providing recommendations
- **[CIS_Benchmark_Baseline.md](./CIS_Benchmark_Baseline.md)** - Current baseline report showing the state of all checks as of January 14, 2026

### Scripts

- **[bin/scan-container-cis](../bin/scan-container-cis)** - Runs Docker Bench for Security scan on containers
- **[bin/check-cis-results](../bin/check-cis-results)** - Compares scan results against expected baseline

### Configuration

- **[.circleci/expected-cis-results.txt](../.circleci/expected-cis-results.txt)** - Baseline of expected/acceptable CIS scan results

## Understanding the Scan

### What Gets Scanned

The CIS scan checks two main areas:

1. **Section 4: Container Images** (12 checks)
   - User configuration (root vs non-root)
   - Health checks
   - Dockerfile best practices
   - Image security

2. **Section 5: Container Runtime** (32 checks)
   - Security profiles (AppArmor, SELinux)
   - Linux capabilities
   - Resource limits
   - Network configuration
   - Privilege restrictions

### Check Results

| Status | Meaning | Action |
|--------|---------|--------|
| ✅ PASS | Security requirement met | None - maintain |
| ⚠️ WARN | Security concern | Review - fix or document |
| ℹ️ INFO | Informational | Manual review |
| 📝 NOTE | Manual check needed | Review by team |

## Current Status (Jan 2026)

- **Overall Score:** 1/44 checks passing
- **PASS:** 14 checks
- **WARN:** 20 checks (all documented and tracked)
- **INFO:** 6 checks
- **NOTE:** 4 checks

### High Priority Issues (Sprint 2)

1. ✅ **HEALTHCHECK added** - Now in Dockerfiles (cron, minio, cache, cypress)
2. 🔄 **Resource limits** - Memory, CPU, PIDs (to be added)
3. 🔄 **no-new-privileges** - Security option (to be added)
4. 🔄 **Health checks at runtime** - docker-compose config (to be added)

### Acceptable Issues

- **AppArmor/SELinux:** Not available on macOS (development only)
- **Privileged ports 80/443:** Standard web server ports
- **Root filesystem writable:** Required for most containers

## Using the Comparison Script

The `check-cis-results` script automates baseline checking:

```bash
./bin/check-cis-results <scan-results.log> [expected-results.txt]
```

### Exit Codes

- **0** - All results match baseline (build can proceed)
- **1** - Unexpected issues found (build should fail)

### Example Output

```bash
✅ IMPROVED: Check 4.6 changed from WARN to PASS
   HEALTHCHECK instructions added

❌ UNEXPECTED: Check 5.11 changed from PASS to WARN (not in baseline)
   Memory limits removed - investigate!
```

## Updating the Baseline

When you fix issues or need to accept new ones:

1. Edit `.circleci/expected-cis-results.txt`
2. Add new entries with format: `check_id|status|pattern|reason`
3. Document WHY the issue is acceptable
4. Commit the updated baseline

Example entry:
```
5.11|WARN|.*|Memory limits to be added in Sprint 2 - tracked in JIRA-123
```

## Integration with CI/CD

### Current Setup (No Enforcement)

```yaml
# .circleci/config.yml
- run:
    name: Check CIS Benchmarks
    command: |
      ./bin/scan-container-cis | tee /tmp/results/scan-cms-cis.log
      exit 0  # Always pass
```

### Recommended Setup (With Enforcement)

```yaml
# .circleci/config.yml
- run:
    name: Check CIS Benchmarks
    command: |
      # Run scan
      ./bin/scan-container-cis | tee /tmp/results/scan-cms-cis.log
      
      # Compare against baseline
      ./bin/check-cis-results /tmp/results/scan-cms-cis.log
      CIS_RESULT=$?
      
      # Fail build if unexpected issues
      exit $CIS_RESULT
```

## Roadmap

### ✅ Phase 1: Baseline (COMPLETE)
- [x] Run CIS scan
- [x] Create baseline documentation
- [x] Add HEALTHCHECK to Dockerfiles
- [x] Create comparison script

### 🔄 Phase 2: Quick Wins (Sprint 2)
- [ ] Add no-new-privileges security option
- [ ] Add memory limits to docker-compose
- [ ] Add CPU limits to docker-compose
- [ ] Add PIDs limits to docker-compose
- [ ] Enable build failure on new issues

### 📅 Phase 3: Security Hardening (Sprint 3)
- [ ] Configure non-root users where feasible
- [ ] Review Linux capabilities
- [ ] Bind internal services to localhost
- [ ] Document AppArmor/SELinux for production

### 📅 Phase 4: Advanced (Q2 2026)
- [ ] Evaluate read-only root filesystem
- [ ] Version pinning for all images
- [ ] Regular security audits
- [ ] Automated remediation

## Troubleshooting

### Script says "unexpected failures" but they look normal

Check if the issue is in the baseline file:
```bash
grep "^5.11" .circleci/expected-cis-results.txt
```

If missing, add it with proper justification.

### Scan shows different results locally vs CI

This can happen due to:
- Different containers running
- macOS (local) vs Linux (CI)
- AppArmor/SELinux availability

Check the baseline for platform-specific exceptions.

### How do I test changes before committing?

```bash
# Run scan locally
./bin/scan-container-cis | tee /tmp/test-results.log

# Compare against baseline
./bin/check-cis-results /tmp/test-results.log

# If it passes, you're good to commit!
```

## Resources

- [Docker Bench for Security](https://github.com/docker/docker-bench-security)
- [CIS Docker Benchmark v1.6.0](https://www.cisecurity.org/benchmark/docker/)
- [Docker Security Best Practices](https://docs.docker.com/engine/security/)

## Support

Questions? Check:
1. [CIS_Benchmark_Analysis.md](./CIS_Benchmark_Analysis.md) - Detailed explanations
2. [CIS_Benchmark_Baseline.md](./CIS_Benchmark_Baseline.md) - Current status
3. Team DevOps channel
4. Security Team

---

**Last Updated:** January 14, 2026  
**Owner:** DevOps Team  
**Next Review:** February 14, 2026
