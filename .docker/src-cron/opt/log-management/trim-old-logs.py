import boto3
from datetime import datetime, timedelta
import json
import os
import re
from botocore.config import Config

# Parse VCAP_SERVICES for S3 credentials
vcap_services = os.environ.get("VCAP_SERVICES")
if not vcap_services:
    raise RuntimeError("VCAP_SERVICES is not set. This script must run in a Cloud Foundry environment.")

vcap = json.loads(vcap_services)
s3_service = next(s for s in vcap.get("s3", []) if s["name"] == "log-storage")
creds = s3_service["credentials"]

# Extract credentials
access_key = creds["access_key_id"]
secret_key = creds["secret_access_key"]
region = creds["region"]
bucket = creds["bucket"]
endpoint = f"https://{creds['endpoint']}"
prefix = "fluent-bit-logs/"
retention_months = 18
cutoff_date = datetime.now() - timedelta(days=retention_months * 30)

# Set up the S3 client
print("Connecting to S3...")
s3 = boto3.client(
    "s3",
    aws_access_key_id=access_key,
    aws_secret_access_key=secret_key,
    region_name=region,
    endpoint_url=endpoint,
    config=Config(signature_version='s3v4')
)
print("Connected")

# Collect objects to delete
print("Getting pages")
objects_to_delete = []
paginator = s3.get_paginator("list_objects_v2")
pages = paginator.paginate(Bucket=bucket, Prefix=prefix)

for page in pages:
    for obj in page.get("Contents", []):
        key = obj["Key"]
        match = re.search(r"fluent-bit-logs/(\d{4})/(\d{2})/(\d{2})", key)
        if match:
            year, month, day = map(int, match.groups())
            obj_date = datetime(year, month, day)
            if obj_date < cutoff_date:
                objects_to_delete.append({"Key": key})
                print("Will delete: " + key)

# Delete in batches
print(f"Found {len(objects_to_delete)} objects to delete.")
for i in range(0, len(objects_to_delete), 1000):
    batch = objects_to_delete[i:i + 1000]
    # TODO: delete this after reviewing logs of a dry run.
    # s3.delete_objects(Bucket=bucket, Delete={"Objects": batch})
    print(f"Deleted {len(batch)} objects...")

# print("Log pruning complete.")
