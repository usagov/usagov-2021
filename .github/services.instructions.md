---
applyTo: "**/*.py,**/*.js,**/*.ts,**/*.rb,**/*.java,**/*.go,**/manifest*.yml"
---

# Cloud.gov Services Instructions

This document provides guidance for provisioning and using cloud.gov managed services including databases, S3 storage, Redis, and more.

## Overview

Cloud.gov provides managed services through a marketplace. Services are:
- Created with `cf create-service`
- Bound to applications to provide credentials
- Accessed via the `VCAP_SERVICES` environment variable

## Listing Available Services

```bash
# View all available services
cf marketplace

# View plans for a specific service
cf marketplace -s aws-rds
cf marketplace -s s3
```

## Service Lifecycle

### Create a Service Instance

```bash
cf create-service <SERVICE> <PLAN> <INSTANCE_NAME>
```

### Bind to an Application

```bash
cf bind-service <APP_NAME> <INSTANCE_NAME>
cf restage <APP_NAME>
```

### Or Bind via manifest.yml

```yaml
applications:
  - name: my-app
    services:
      - my-database
      - my-s3-bucket
```

### Unbind and Delete

```bash
cf unbind-service <APP_NAME> <INSTANCE_NAME>
cf delete-service <INSTANCE_NAME>
```

## Relational Databases (AWS RDS)

### Available Plans

| Plan | Description |
|------|-------------|
| `micro-psql` | Single-AZ PostgreSQL, 1 core, 1 GiB memory |
| `small-psql` | Single-AZ PostgreSQL, 1 core, 2 GiB memory |
| `medium-psql` | Single-AZ PostgreSQL, 1 core, 4 GiB memory |
| `*-psql-redundant` | Multi-AZ PostgreSQL (production recommended) |
| `small-mysql` | Single-AZ MySQL, 1 core, 2 GiB memory |
| `*-mysql-redundant` | Multi-AZ MySQL (production recommended) |

**Sandbox spaces**: Only `micro-psql` and `small-mysql` available.

### Create PostgreSQL Database

```bash
# Development
cf create-service aws-rds micro-psql my-database

# Production (Multi-AZ)
cf create-service aws-rds small-psql-redundant my-database

# With custom storage size
cf create-service aws-rds small-psql my-database -c '{"storage": 50}'

# With specific version
cf create-service aws-rds micro-psql my-database -c '{"version": "15"}'
```

### Create MySQL Database

```bash
cf create-service aws-rds small-mysql my-database

# Enable functions/triggers
cf create-service aws-rds small-mysql my-database -c '{"enable_functions": true}'
```

### Database Connection (Python Example)

```python
import os
import json

# Parse VCAP_SERVICES
vcap_services = json.loads(os.environ.get('VCAP_SERVICES', '{}'))

# Get database credentials
db_credentials = vcap_services.get('aws-rds', [{}])[0].get('credentials', {})

DATABASE_URL = db_credentials.get('uri')
# Or use individual components:
DB_HOST = db_credentials.get('host')
DB_PORT = db_credentials.get('port')
DB_NAME = db_credentials.get('db_name')
DB_USER = db_credentials.get('username')
DB_PASSWORD = db_credentials.get('password')
```

### Database Connection (Node.js Example)

```javascript
const cfenv = require('cfenv');
const appEnv = cfenv.getAppEnv();

// Get database URL
const dbUrl = appEnv.getServiceURL('my-database');

// Or parse manually
const vcapServices = JSON.parse(process.env.VCAP_SERVICES || '{}');
const dbCredentials = vcapServices['aws-rds']?.[0]?.credentials || {};

const dbConfig = {
  host: dbCredentials.host,
  port: dbCredentials.port,
  database: dbCredentials.db_name,
  user: dbCredentials.username,
  password: dbCredentials.password,
  ssl: { rejectUnauthorized: false }
};
```

### Rotate Database Credentials

```bash
# Rotate credentials
cf update-service my-database -c '{"rotate_credentials": true}'

# Wait, then rebind
cf unbind-service my-app my-database
# Wait 1 minute
cf bind-service my-app my-database
cf restage my-app --strategy rolling
```

### Connect to Database Locally

```bash
# Using cf-service-connect plugin
cf connect-to-service my-app my-database

# This opens psql/mysql shell directly
```

## S3 Object Storage

### Available Plans

| Plan | Description |
|------|-------------|
| `basic` | Private bucket |
| `basic-public` | Public read bucket |
| `basic-sandbox` | Private bucket (deleted with service) |
| `basic-public-sandbox` | Public bucket (deleted with service) |

### Create S3 Bucket

```bash
# Private bucket
cf create-service s3 basic my-s3-bucket

# Public bucket
cf create-service s3 basic-public my-public-bucket
```

### S3 Connection (Python Example)

```python
import os
import json
import boto3

vcap_services = json.loads(os.environ.get('VCAP_SERVICES', '{}'))
s3_credentials = vcap_services.get('s3', [{}])[0].get('credentials', {})

s3_client = boto3.client(
    's3',
    aws_access_key_id=s3_credentials.get('access_key_id'),
    aws_secret_access_key=s3_credentials.get('secret_access_key'),
    region_name=s3_credentials.get('region')
)

bucket_name = s3_credentials.get('bucket')

# Upload file
s3_client.upload_file('local_file.txt', bucket_name, 'remote_file.txt')

# Download file
s3_client.download_file(bucket_name, 'remote_file.txt', 'local_file.txt')

# List objects
response = s3_client.list_objects_v2(Bucket=bucket_name)
```

### S3 Connection (Node.js Example)

```javascript
const { S3Client, PutObjectCommand, GetObjectCommand } = require('@aws-sdk/client-s3');

const vcapServices = JSON.parse(process.env.VCAP_SERVICES || '{}');
const s3Creds = vcapServices['s3']?.[0]?.credentials || {};

const s3Client = new S3Client({
  region: s3Creds.region,
  credentials: {
    accessKeyId: s3Creds.access_key_id,
    secretAccessKey: s3Creds.secret_access_key
  }
});

const bucketName = s3Creds.bucket;

// Upload
await s3Client.send(new PutObjectCommand({
  Bucket: bucketName,
  Key: 'my-file.txt',
  Body: 'Hello, world!'
}));
```

### Create Service Key for External Access

```bash
# Create key
cf create-service-key my-s3-bucket external-access

# View credentials
cf service-key my-s3-bucket external-access

# Delete key when done
cf delete-service-key my-s3-bucket external-access
```

## Redis (ElastiCache)

### Create Redis Instance

```bash
cf create-service aws-elasticache-redis redis-dev my-redis
```

### Redis Connection (Python Example)

```python
import os
import json
import redis

vcap_services = json.loads(os.environ.get('VCAP_SERVICES', '{}'))
redis_credentials = vcap_services.get('aws-elasticache-redis', [{}])[0].get('credentials', {})

redis_client = redis.from_url(redis_credentials.get('uri'))

# Use Redis
redis_client.set('key', 'value')
value = redis_client.get('key')
```

### Redis Connection (Node.js Example)

```javascript
const Redis = require('ioredis');

const vcapServices = JSON.parse(process.env.VCAP_SERVICES || '{}');
const redisCreds = vcapServices['aws-elasticache-redis']?.[0]?.credentials || {};

const redis = new Redis(redisCreds.uri);

await redis.set('key', 'value');
const value = await redis.get('key');
```

## User-Provided Services

For external services not in the marketplace:

### Create User-Provided Service

```bash
# Interactive
cf create-user-provided-service my-external-api -p "api_key, api_url"

# With JSON
cf cups my-external-api -p '{"api_key":"secret123","api_url":"https://api.example.com"}'
```

### Bind to Application

```bash
cf bind-service my-app my-external-api
cf restage my-app
```

### Access in Application

```python
vcap_services = json.loads(os.environ.get('VCAP_SERVICES', '{}'))
external_api = vcap_services.get('user-provided', [{}])[0].get('credentials', {})

api_key = external_api.get('api_key')
api_url = external_api.get('api_url')
```

## Service Keys

Create credentials for external access (CI/CD, local development):

```bash
# Create service key
cf create-service-key my-database deploy-key

# View credentials
cf service-key my-database deploy-key

# Delete service key
cf delete-service-key my-database deploy-key
```

## Sharing Services Between Spaces

```bash
# Share service with another space
cf share-service my-database -s other-space

# Share with space in different org
cf share-service my-database -s other-space -o other-org

# Unshare
cf unshare-service my-database -s other-space
```

## Viewing Service Information

```bash
# List services in current space
cf services

# View service details
cf service my-database

# View bound app credentials
cf env my-app
```

## VCAP_SERVICES Structure

The `VCAP_SERVICES` environment variable contains all bound service credentials:

```json
{
  "aws-rds": [
    {
      "name": "my-database",
      "label": "aws-rds",
      "plan": "micro-psql",
      "credentials": {
        "host": "...",
        "port": 5432,
        "db_name": "...",
        "username": "...",
        "password": "...",
        "uri": "postgres://..."
      }
    }
  ],
  "s3": [
    {
      "name": "my-s3-bucket",
      "credentials": {
        "access_key_id": "...",
        "secret_access_key": "...",
        "bucket": "...",
        "region": "us-gov-west-1"
      }
    }
  ]
}
```

## Best Practices

### Security

- **Never hardcode credentials** - Always read from `VCAP_SERVICES`
- **Rotate credentials regularly** - Use `rotate_credentials` for databases
- **Use service keys sparingly** - Prefer bound credentials when possible
- **Delete unused service keys** - Reduce credential exposure

### Reliability

- **Use redundant plans for production** - Multi-AZ for databases
- **Document service dependencies** - Keep manifest.yml updated
- **Plan for service outages** - Implement retry logic and circuit breakers

### Cost Optimization

- **Right-size services** - Start small, scale as needed
- **Use sandbox plans for development** - They're deleted automatically
- **Clean up unused services** - `cf services` to audit

## Troubleshooting

### Cannot Connect to Service

1. Check egress rules: `cf space <SPACE_NAME> --security-group-rules`
2. Update egress if needed: See [Space Egress](https://docs.cloud.gov/platform/management/space-egress/)

### Service Creation Stuck

```bash
# Check service status
cf service my-database

# View service events
cf events my-database
```

### Credentials Not Available

```bash
# Verify binding
cf services

# Rebind if needed
cf unbind-service my-app my-database
cf bind-service my-app my-database
cf restage my-app
```

## References

- [cloud.gov Managed Services](https://docs.cloud.gov/platform/deployment/managed-services/)
- [RDS Documentation](https://docs.cloud.gov/platform/services/relational-database/)
- [S3 Documentation](https://docs.cloud.gov/platform/services/s3/)
- [Cloud Foundry Services](https://docs.cloudfoundry.org/devguide/services/)
