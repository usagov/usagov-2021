---
applyTo: "**/*.py,**/*.js,**/*.ts,**/*.rb,**/*.java,**/*.go,**/manifest*.yml,**/.github/workflows/*.yml"
---

# Cloud.gov Security Instructions

This document provides security guidance for applications deployed to cloud.gov, including FedRAMP compliance, secrets management, and security best practices.

## Overview

Cloud.gov is FedRAMP Moderate authorized (Package ID: F1607067912). This means:
- You inherit ~60% of NIST SP 800-53 controls
- The platform handles infrastructure security
- You are responsible for application-level security

## Shared Responsibility Model

### Cloud.gov Manages (Inherited Controls)

- Physical security of data centers
- Network infrastructure security
- Operating system patching
- Platform vulnerability scanning
- Encryption of data at rest and in transit
- Backup of platform infrastructure

### You Manage (Customer Controls)

- Application code security
- Application dependencies and patching
- Access control within your application
- Secure handling of user data
- Application-level logging and monitoring
- Secrets management for your application

### Shared Controls

- Incident response (platform + application)
- Security monitoring (platform + application logs)
- Access management (platform users + service accounts)

## Secrets Management

### Never Commit Secrets

**CRITICAL**: Never commit secrets to version control.

Bad (never do this):

```python
# DO NOT DO THIS
DATABASE_URL = "postgres://user:password@host:5432/db"
API_KEY = "sk-1234567890abcdef"
```

### Use Environment Variables

Good approach - read from environment:

```python
import os

DATABASE_URL = os.environ.get('DATABASE_URL')
API_KEY = os.environ.get('API_KEY')
```

### Use Bound Services

Best approach - read from VCAP_SERVICES:

```python
import os
import json

vcap_services = json.loads(os.environ.get('VCAP_SERVICES', '{}'))
db_credentials = vcap_services.get('aws-rds', [{}])[0].get('credentials', {})

DATABASE_URL = db_credentials.get('uri')
```

### Set Environment Variables Securely

```bash
# Set via CF CLI (not in manifest.yml)
cf set-env my-app SECRET_KEY "your-secret-value"
cf restage my-app
```

### Use User-Provided Services for External Secrets

```bash
# Create a user-provided service with credentials
cf cups my-api-credentials -p '{"api_key":"secret123","api_url":"https://api.example.com"}'

# Bind to your app
cf bind-service my-app my-api-credentials
cf restage my-app
```

Access in code:

```python
vcap_services = json.loads(os.environ.get('VCAP_SERVICES', '{}'))
api_creds = next(
    (s['credentials'] for s in vcap_services.get('user-provided', [])
     if s['name'] == 'my-api-credentials'),
    {}
)
```

### Rotate Secrets Regularly

```bash
# Rotate database credentials
cf update-service my-database -c '{"rotate_credentials": true}'

# Wait, then rebind
cf unbind-service my-app my-database
cf bind-service my-app my-database
cf restage my-app --strategy rolling
```

## Egress Control

### Understanding Egress Rules

By default, new spaces have restricted egress (closed-egress):
- **closed-egress**: No outbound connections (except internal routes)
- **trusted-local-egress**: Access to cloud.gov brokered services only
- **public-egress**: Access to public internet

### Check Current Egress Settings

```bash
# List security groups for your space
cf security-groups

# View details of a security group
cf security-group public_networks_egress
```

### Enable Egress for Services

Most applications need `trusted-local-egress` to access databases:

```bash
# Enable access to brokered services (as SpaceManager)
cf bind-security-group trusted_local_networks_egress your-org --space your-space
```

### Enable Public Internet Access

If your app needs to call external APIs:

```bash
# Enable public internet egress (as SpaceManager)
cf bind-security-group public_networks_egress your-org --space your-space
```

### Production Configuration

Many production apps need both:

```bash
# Access to databases AND external APIs
cf bind-security-group trusted_local_networks_egress your-org --space prod
cf bind-security-group public_networks_egress your-org --space prod
```

## Authentication & Authorization

### Use cloud.gov Identity Provider

For federal applications requiring authentication:

```bash
# Create identity provider service
cf create-service cloud-gov-identity-provider oauth-client my-identity

# Bind to your app
cf bind-service my-app my-identity
cf restage my-app
```

### Implement Proper Session Management

```python
# Python Flask example
from flask import Flask, session
import os

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY')

# Use secure session settings
app.config.update(
    SESSION_COOKIE_SECURE=True,     # HTTPS only
    SESSION_COOKIE_HTTPONLY=True,   # No JavaScript access
    SESSION_COOKIE_SAMESITE='Lax',  # CSRF protection
    PERMANENT_SESSION_LIFETIME=1800  # 30 minute timeout
)
```

### Implement HTTPS Redirects

Cloud.gov terminates TLS at the platform level, but your app should enforce HTTPS:

```python
# Flask example
@app.before_request
def redirect_https():
    # Check X-Forwarded-Proto header (set by cloud.gov)
    if request.headers.get('X-Forwarded-Proto') == 'http':
        url = request.url.replace('http://', 'https://', 1)
        return redirect(url, code=301)
```

## Secure Coding Practices

### Input Validation

```python
from wtforms import Form, StringField, validators
from bleach import clean

class UserForm(Form):
    name = StringField('Name', [
        validators.Length(min=1, max=100),
        validators.Regexp(r'^[a-zA-Z\s]+$')
    ])
    
    def sanitize(self):
        self.name.data = clean(self.name.data)
```

### SQL Injection Prevention

Use parameterized queries:

```python
# Good - parameterized query
cursor.execute("SELECT * FROM users WHERE id = %s", (user_id,))

# Bad - string concatenation (vulnerable!)
# cursor.execute(f"SELECT * FROM users WHERE id = {user_id}")
```

### XSS Prevention

```python
# Use auto-escaping templates (Jinja2 does this by default)
# Explicitly escape when needed
from markupsafe import escape

safe_output = escape(user_input)
```

### CSRF Protection

```python
# Flask-WTF handles this automatically
from flask_wtf.csrf import CSRFProtect

csrf = CSRFProtect(app)
```

## Logging Security

### Never Log Sensitive Data

```python
import logging

logger = logging.getLogger(__name__)

# Good - log event without sensitive data
logger.info("User login successful", extra={"user_id": user.id})

# Bad - never do this!
# logger.info(f"User {user.email} logged in with password {password}")
```

### Mask Sensitive Fields

```python
def mask_sensitive(data):
    """Mask sensitive fields in log data"""
    sensitive_fields = ['password', 'token', 'api_key', 'ssn', 'credit_card']
    masked = data.copy()
    for field in sensitive_fields:
        if field in masked:
            masked[field] = '***REDACTED***'
    return masked
```

### Structure Logs for SIEM Integration

```python
import json
import logging
from datetime import datetime

class JSONFormatter(logging.Formatter):
    def format(self, record):
        log_record = {
            "timestamp": datetime.utcnow().isoformat(),
            "level": record.levelname,
            "message": record.getMessage(),
            "logger": record.name,
            "app": os.environ.get('VCAP_APPLICATION', {}).get('name', 'unknown')
        }
        if hasattr(record, 'user_id'):
            log_record['user_id'] = record.user_id
        return json.dumps(log_record)
```

## Dependency Security

### Keep Dependencies Updated

```bash
# Python - check for vulnerabilities
pip install safety
safety check -r requirements.txt

# Node.js - audit dependencies
npm audit

# Fix vulnerabilities
npm audit fix
```

### Pin Dependency Versions

```
# requirements.txt - pin versions
Django==4.2.7
requests==2.31.0
psycopg2-binary==2.9.9
```

### Use Dependabot or Renovate

Create `.github/dependabot.yml`:

```yaml
version: 2
updates:
  - package-ecosystem: "pip"
    directory: "/"
    schedule:
      interval: "weekly"
    open-pull-requests-limit: 10
```

## Vulnerability Scanning

### Static Application Security Testing (SAST)

Add to CI/CD pipeline:

```yaml
# GitHub Actions example
- name: Run security scan
  uses: github/codeql-action/analyze@v2
  with:
    languages: python
```

### Container Scanning

If using Docker:

```yaml
- name: Scan container
  uses: aquasecurity/trivy-action@master
  with:
    image-ref: 'my-app:latest'
    format: 'sarif'
    output: 'trivy-results.sarif'
```

## Compliance Documentation

### Document Your Controls

Maintain documentation for:
- Your System Security Plan (SSP)
- Control implementation statements
- Evidence of control operation

### Inherited Controls

Reference cloud.gov's FedRAMP package (ID: F1607067912) for inherited controls.

### Customer Controls

Document how your application implements:
- Access Control (AC family)
- Audit and Accountability (AU family)
- Identification and Authentication (IA family)
- System and Communications Protection (SC family)

## Documenting NIST Controls in Code

When generating code or configuration files, **always include NIST SP 800-53 control references** in comments and documentation. This supports the Authority to Operate (ATO) process by creating traceable evidence of control implementation.

### Control Reference Format

Use this standard format for control references:

```
NIST 800-53: <CONTROL-ID> - <CONTROL-NAME>
```

Examples:
- `NIST 800-53: AC-2 - Account Management`
- `NIST 800-53: AU-2 - Audit Events`
- `NIST 800-53: SC-8 - Transmission Confidentiality and Integrity`

### Controls by Artifact Type

Use this reference to determine which controls apply to different types of code:

| Artifact Type | Relevant Control Families | Common Controls |
|---------------|---------------------------|------------------|
| Authentication code | IA (Identification & Auth) | IA-2, IA-5, IA-8 |
| Authorization/RBAC | AC (Access Control) | AC-2, AC-3, AC-6 |
| Logging/Audit code | AU (Audit & Accountability) | AU-2, AU-3, AU-6, AU-12 |
| Input validation | SI (System & Info Integrity) | SI-10, SI-11 |
| Encryption/TLS | SC (System & Comm Protection) | SC-8, SC-13, SC-28 |
| Session management | AC, SC | AC-12, SC-23 |
| Error handling | SI, AU | SI-11, AU-6 |
| Configuration files | CM (Config Management) | CM-2, CM-6 |
| CI/CD workflows | SA, CM | SA-10, CM-3 |

### Code Documentation Examples

#### Python Function with Control Documentation

```python
def authenticate_user(username: str, password: str) -> Optional[User]:
    """
    Authenticate a user with username and password.
    
    Security Controls:
        NIST 800-53: IA-2 - Identification and Authentication (Organizational Users)
        NIST 800-53: IA-5 - Authenticator Management
        NIST 800-53: AU-2 - Audit Events (login attempts are logged)
    
    Args:
        username: The user's unique identifier
        password: The user's password (never logged)
    
    Returns:
        User object if authentication succeeds, None otherwise
    """
    # Log authentication attempt (but never the password)
    # NIST 800-53: AU-3 - Content of Audit Records
    logger.info("auth_attempt", extra={"username": username, "timestamp": datetime.utcnow()})
    
    user = User.query.filter_by(username=username).first()
    if user and user.verify_password(password):
        # NIST 800-53: AU-2 - Log successful authentication
        logger.info("auth_success", extra={"user_id": user.id})
        return user
    
    # NIST 800-53: AU-2 - Log failed authentication
    logger.warning("auth_failure", extra={"username": username})
    return None
```

#### JavaScript/Node.js with Control Documentation

```javascript
/**
 * Validates and sanitizes user input before processing.
 * 
 * Security Controls:
 *   NIST 800-53: SI-10 - Information Input Validation
 *   NIST 800-53: SI-11 - Error Handling
 * 
 * @param {Object} input - Raw user input
 * @returns {Object} Sanitized input object
 * @throws {ValidationError} If input fails validation
 */
function validateUserInput(input) {
  // NIST 800-53: SI-10 - Validate input format and content
  const schema = Joi.object({
    email: Joi.string().email().required(),
    name: Joi.string().max(100).pattern(/^[a-zA-Z\s]+$/).required()
  });
  
  const { error, value } = schema.validate(input);
  
  if (error) {
    // NIST 800-53: SI-11 - Generate error messages that don't reveal system info
    logger.warn('input_validation_failed', { field: error.details[0].path });
    throw new ValidationError('Invalid input provided');
  }
  
  // NIST 800-53: SI-10 - Sanitize validated input
  return {
    email: value.email.toLowerCase().trim(),
    name: sanitizeHtml(value.name)
  };
}
```

#### Configuration File with Control Documentation

```yaml
# manifest.yml
# 
# Security Controls Implemented:
#   NIST 800-53: CM-2 - Baseline Configuration
#   NIST 800-53: CM-6 - Configuration Settings
#   NIST 800-53: SC-8 - Transmission Confidentiality (HTTPS enforced by platform)
#   NIST 800-53: SI-2 - Flaw Remediation (buildpack provides patched runtime)
---
applications:
  - name: my-secure-app
    memory: 512M
    instances: 2  # NIST 800-53: CP-10 - System Recovery and Reconstitution
    buildpacks:
      - python_buildpack
    env:
      # NIST 800-53: CM-6 - Security configuration settings
      SECURE_COOKIES: "true"
      SESSION_TIMEOUT: "1800"
    services:
      - my-database  # NIST 800-53: SC-28 - Protection of Information at Rest
```

#### CI/CD Workflow with Control Documentation

```yaml
# .github/workflows/deploy.yml
#
# Security Controls Implemented:
#   NIST 800-53: SA-10 - Developer Configuration Management
#   NIST 800-53: SA-11 - Developer Security Testing
#   NIST 800-53: CM-3 - Configuration Change Control
#   NIST 800-53: AU-6 - Audit Review, Analysis, and Reporting

name: Secure Deployment Pipeline

on:
  push:
    branches: [main]

jobs:
  # NIST 800-53: SA-11 - Security testing before deployment
  security-scan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      # NIST 800-53: RA-5 - Vulnerability Scanning
      - name: Dependency vulnerability scan
        run: |
          pip install safety
          safety check -r requirements.txt

  deploy:
    needs: security-scan
    runs-on: ubuntu-latest
    steps:
      # NIST 800-53: CM-3 - Controlled deployment from approved branch
      - uses: actions/checkout@v4
      
      # NIST 800-53: IA-2 - Service account authentication
      - name: Deploy to Cloud.gov
        uses: cloud-gov/cg-cli-tools@main
        with:
          cf_username: ${{ secrets.CG_USERNAME }}
          cf_password: ${{ secrets.CG_PASSWORD }}
```

#### Database Migration with Control Documentation

```python
"""
Database migration: Add audit logging table

Security Controls:
    NIST 800-53: AU-3 - Content of Audit Records
    NIST 800-53: AU-9 - Protection of Audit Information  
    NIST 800-53: AU-12 - Audit Generation
"""

def upgrade():
    # NIST 800-53: AU-3 - Create table to store required audit fields
    op.create_table(
        'audit_log',
        sa.Column('id', sa.Integer(), primary_key=True),
        sa.Column('timestamp', sa.DateTime(), nullable=False),  # AU-3(a)
        sa.Column('event_type', sa.String(50), nullable=False),  # AU-3(b)
        sa.Column('user_id', sa.Integer(), nullable=True),  # AU-3(c)
        sa.Column('source_ip', sa.String(45), nullable=True),  # AU-3(d)
        sa.Column('outcome', sa.String(20), nullable=False),  # AU-3(e)
        sa.Column('details', sa.JSON(), nullable=True)  # AU-3(f)
    )
    
    # NIST 800-53: AU-9 - Protect audit records from modification
    op.execute("REVOKE UPDATE, DELETE ON audit_log FROM app_user")
```

### Key Controls Reference

Common NIST SP 800-53 controls to reference in cloud.gov applications:

| Control ID | Control Name | When to Reference |
|------------|--------------|-------------------|
| AC-2 | Account Management | User registration, account lifecycle |
| AC-3 | Access Enforcement | Authorization checks, RBAC |
| AC-6 | Least Privilege | Role definitions, permission checks |
| AC-12 | Session Termination | Session timeout, logout |
| AU-2 | Audit Events | Logging security events |
| AU-3 | Content of Audit Records | Log record structure |
| AU-6 | Audit Review | Log analysis, monitoring |
| AU-12 | Audit Generation | Log generation code |
| IA-2 | Identification & Authentication | Login, authentication |
| IA-5 | Authenticator Management | Password handling |
| SC-8 | Transmission Confidentiality | HTTPS, TLS configuration |
| SC-13 | Cryptographic Protection | Encryption implementation |
| SC-28 | Protection of Information at Rest | Database encryption |
| SI-10 | Information Input Validation | Input validation |
| SI-11 | Error Handling | Error messages, exception handling |
| CM-2 | Baseline Configuration | Configuration files |
| CM-6 | Configuration Settings | Security settings |

## Security Checklist

### Before Deployment

- [ ] No secrets in code or version control
- [ ] All dependencies pinned and scanned
- [ ] Input validation implemented
- [ ] Output encoding for XSS prevention
- [ ] CSRF protection enabled
- [ ] Secure session configuration
- [ ] Logging excludes sensitive data

### Production Configuration

- [ ] Multi-AZ database for redundancy
- [ ] 2+ application instances
- [ ] Health check endpoint configured
- [ ] Egress rules properly configured
- [ ] Service credentials rotated

### Ongoing

- [ ] Regular dependency updates
- [ ] Security scanning in CI/CD
- [ ] Log monitoring configured
- [ ] Incident response plan documented

## Troubleshooting

### Egress Connection Blocked

**Symptom**: Application cannot reach external API or service

**Diagnosis**:
```bash
# Check space egress rules
cf space <SPACE_NAME> --security-group-rules

# View application logs for connection errors
cf logs <APP_NAME> --recent | grep -i "connection\|timeout\|refused"
```

**Solutions**:
1. Work with cloud.gov support to configure egress rules
2. Use a cloud.gov-managed service instead of external service
3. Verify the external service IP/hostname hasn't changed

### Service Credential Access Denied

**Symptom**: Application fails to authenticate to bound service

**Diagnosis**:
```bash
# Verify service is bound
cf services

# Check credentials in environment
cf env <APP_NAME> | grep -A 20 VCAP_SERVICES
```

**Solutions**:
1. Rebind the service: `cf unbind-service <APP> <SERVICE> && cf bind-service <APP> <SERVICE>`
2. Restage the application: `cf restage <APP_NAME>`
3. Rotate service credentials if compromised

### Environment Variable Secrets Exposed in Logs

**Symptom**: Sensitive data appearing in `cf logs` output

**Diagnosis**:
```bash
# Review recent logs for sensitive patterns
cf logs <APP_NAME> --recent | grep -i "password\|token\|key\|secret"
```

**Solutions**:
1. Update application code to mask sensitive values in logs
2. Use structured logging with sensitive field filtering
3. Rotate any exposed credentials immediately
4. Review log drain destinations for exposure

### Security Group Misconfiguration

**Symptom**: Application cannot communicate with other apps or services

**Diagnosis**:
```bash
# List security groups applied to space
cf security-groups

# Check running security groups
cf running-security-groups

# Check staging security groups
cf staging-security-groups
```

**Solutions**:
1. Contact cloud.gov support for security group modifications
2. Verify application is in the correct space
3. Check if service requires network policy configuration

### SSL/TLS Certificate Errors

**Symptom**: HTTPS connections failing with certificate errors

**Diagnosis**:
```bash
# Test connection to external service
cf ssh <APP_NAME> -c "curl -v https://external-service.example.com"
```

**Solutions**:
1. Verify external service certificate is valid and not expired
2. Check if certificate authority is trusted
3. For internal services, use service mesh or proper certificate chain

### Session Hijacking Concerns

**Symptom**: Users reporting unexpected session behavior

**Diagnosis**:
1. Review session configuration for secure flags
2. Check if sessions are stored externally (Redis) vs in-memory
3. Verify HTTPS-only access

**Solutions**:
1. Enable secure and httpOnly cookie flags
2. Implement session rotation on authentication
3. Use external session store for multi-instance apps
4. Configure appropriate session timeout

### Dependency Vulnerability Detected

**Symptom**: Security scanner reports vulnerable dependency

**Diagnosis**:
```bash
# Python
pip-audit

# Node.js
npm audit

# Ruby
bundle audit
```

**Solutions**:
1. Update vulnerable dependency to patched version
2. If no patch available, evaluate alternative libraries
3. Implement compensating controls if update not possible
4. Document risk acceptance if vulnerability is accepted

### Failed Login Attempts / Brute Force

**Symptom**: High volume of failed authentication attempts

**Diagnosis**:
```bash
# Review logs for authentication failures
cf logs <APP_NAME> --recent | grep -i "auth\|login\|401\|403"
```

**Solutions**:
1. Implement rate limiting on authentication endpoints
2. Add account lockout after failed attempts
3. Enable multi-factor authentication
4. Consider WAF or DDoS protection services

## References

- [cloud.gov Compliance Overview](https://docs.cloud.gov/platform/overview/compliance-overview/)
- [cloud.gov Egress Control](https://docs.cloud.gov/platform/management/space-egress/)
- [FedRAMP Package Request](https://www.fedramp.gov/resources/documents/Agency_Package_Request_Form.pdf)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [NIST SP 800-53](https://csrc.nist.gov/publications/detail/sp/800-53/rev-5/final)
