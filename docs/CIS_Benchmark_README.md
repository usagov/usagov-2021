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
./bin/scan-cis-with-benchmark

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
- **[bin/scan-cis-with-benchmark](../bin/scan-cis-with-benchmark)** - Runs CIS scan and compares against expected baseline

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

- **Overall Score:** 5/44 checks passing (improved from 1)
- **PASS:** 16 checks (up from 14)
- **WARN:** 18 checks (down from 20, all documented and tracked)
- **INFO:** 6 checks
- **NOTE:** 4 checks

### Recently Completed (Sprint 1 - Jan 16, 2026)

1. ✅ **HEALTHCHECK added** - Now in Dockerfiles (cron, minio, cache, cypress)
2. ✅ **no-new-privileges** - Security option added to all containers
3. ✅ **Localhost binding** - Database and cache now bound to 127.0.0.1
4. ✅ **SELinux security options** - Check 5.3 now passing

### High Priority Issues (Sprint 2)

1. 🔄 **Resource limits** - Memory, CPU, PIDs (to be added)
2. 🔄 **Health checks at runtime** - docker-compose config (to be added)
3. 🔄 **Non-root users** - Configure where feasible

### Acceptable Issues

- **AppArmor:** Not available on macOS (development only)
- **Privileged ports 80/443:** Standard web server ports
- **Root filesystem writable:** Required for most containers

## Using the Comparison Script

The `scan-cis-with-benchmark` script runs the scan and validates against baseline:

```bash
./bin/scan-cis-with-benchmark                            # Run scan automatically
./bin/scan-cis-with-benchmark --update                   # Update baseline with all improvements
./bin/scan-cis-with-benchmark --update=5.26              # Update only specific check
./bin/scan-cis-with-benchmark <scan-results.log>         # Use existing scan file
```

### Key Features

- **Automatic scanning** - No need to manually save scan output
- **Live output** - See scan results as they run
- **Baseline comparison** - Only shows unexpected changes
- **Auto-update baseline** - Use `--update` flag to accept improvements
- **Selective updates** - Use `--update=CHECK_ID` for specific checks
- **Detailed summary** - Shows all outstanding issues at the end

### Exit Codes

- **0** - All results match baseline (build can proceed)
- **1** - Unexpected issues found (build should fail)

### Example Output

```bash
✅ All checks match baseline expectations!

Total checks scanned: 44

Known issues (baselined):
  - WARN/FAIL: 12 checks
  - INFO: 4 checks

Outstanding WARN/FAIL checks:
  • 4.1 (WARN): Multiple containers running as root - to be fixed Q1 2026
  • 5.2 (WARN): AppArmor not available on macOS Docker Desktop - acceptable for dev
  • 5.11 (WARN): Memory limits to be added in Sprint 2 - tracked in backlog
  ...
```

When improvements are detected:

```bash
✅ IMPROVED: Check 5.26 changed from WARN to PASS
   Container is restricted from acquiring additional privileges

✅ No new failures, but there were some changes.
Consider updating the baseline file with: --update
```

## Updating the Baseline

### Automatic Updates (Recommended)

When you fix security issues, use the `--update` flag:

```bash
# Update all improved checks
./bin/scan-cis-with-benchmark --update

# Update only a specific check
./bin/scan-cis-with-benchmark --update=5.26
```

The script will:
- Remove improved checks from baseline
- Update the "Last updated" timestamp
- Show confirmation of changes

### Manual Updates

If you need to accept new issues:

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
      # Run scan and compare against baseline
      ./bin/scan-cis-with-benchmark
      
      # Exit code 0 = pass, 1 = fail
      # Build will automatically fail if unexpected issues found
```

## Roadmap

### ✅ Phase 1: Baseline & Quick Wins (COMPLETE - Jan 16, 2026)
- [x] Run CIS scan
- [x] Create baseline documentation
- [x] Add HEALTHCHECK to Dockerfiles
- [x] Create comparison script with auto-update
- [x] Add no-new-privileges security option
- [x] Bind database/cache to localhost
- [x] Enhanced summary output

### 🔄 Phase 2: Resource Limits (Sprint 2)
- [ ] Add memory limits to docker-compose
- [ ] Add CPU limits to docker-compose
- [ ] Add PIDs limits to docker-compose
- [ ] Enable build failure on new issues in CI/CD

### 📅 Phase 3: Security Hardening (Sprint 3)
- [ ] Configure non-root users where feasible
- [ ] Review Linux capabilities
- [ ] Add remaining health checks
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
# Run scan and check against baseline
./bin/scan-cis-with-benchmark

# If improvements detected, update baseline
./bin/scan-cis-with-benchmark --update

# Commit the updated baseline
git add .circleci/expected-cis-results.txt
git commit -m "chore: update CIS baseline after security improvements"
```

### Can I update just one check in the baseline?

Yes! Use the `--update=CHECK_ID` flag:

```bash
# Only update check 5.26 in the baseline
./bin/scan-cis-with-benchmark --update=5.26
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

**Last Updated:** January 16, 2026  
**Owner:** DevOps Team  
**Next Review:** February 14, 2026  
**Recent Changes:**
- Added `--update` flag for automatic baseline updates
- Implemented no-new-privileges security option (Sprint 1)
- Bound database/cache to localhost (Sprint 1)
- Enhanced summary output with outstanding checks
- Score improved from 1 to 5
