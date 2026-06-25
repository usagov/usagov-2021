#!/usr/bin/env sh

set -eu

export HTTPS_PROXY="${PROXYROUTE:-}"
export HTTP_PROXY="${PROXYROUTE:-}"
export https_proxy="${PROXYROUTE:-}"
export http_proxy="${PROXYROUTE:-}"

APP_NAME=$(python -c 'import json, os; print(json.loads(os.environ.get("VCAP_APPLICATION", "{}") or "{}").get("name", "unknown"))' 2>/dev/null || echo "unknown")
SPACE=$(python -c 'import json, os; print(json.loads(os.environ.get("VCAP_APPLICATION", "{}") or "{}").get("space_name", "unknown"))' 2>/dev/null || echo "unknown")
OS_VERSION=$(grep PRETTY_NAME /etc/os-release 2>/dev/null | cut -d'"' -f2 || echo "unknown")
PYTHON_V=$(python --version 2>&1 | awk '{print $2}' || echo "unknown")

echo "SFTWR_AUDIT: app=${APP_NAME} space=${SPACE} os=\"${OS_VERSION}\" python=${PYTHON_V}"

exec gunicorn app.app:app
