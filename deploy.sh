#!/usr/bin/env bash
set -euo pipefail

if ! command -v jq >/dev/null; then
  echo "Must have JQ installed"
  exit 1
fi

if [ -z "${ROUTE_SERVICE_APP_NAME+set}" ]; then
  echo "Must set ROUTE_SERVICE_APP_NAME environment variable"
  exit 1
fi

if [ -z "${ROUTE_SERVICE_NAME+set}" ]; then
  echo "Must set ROUTE_SERVICE_NAME environment variable"
  exit 1
fi

if [ -z "${PROTECTED_APP_NAME+set}" ]; then
  echo "Must set PROTECTED_APP_NAME environment variable"
  exit 1
fi

APPS_DOMAIN=$(cf curl "/v3/domains" | jq -r '[.resources[] | select(.name|endswith("app.cloud.gov"))][0].name')

echo "apps_domain"
echo $APPS_DOMAIN

cf push "${ROUTE_SERVICE_APP_NAME}" --no-start --var app-name="${ROUTE_SERVICE_APP_NAME}"
cf set-env "${ROUTE_SERVICE_APP_NAME}" ALLOWED_IPS "$(printf "%s" "${NGINX_ALLOW_STATEMENTS}")"
cf start "${ROUTE_SERVICE_APP_NAME}"

ROUTE_SERVICE_DOMAIN="$(cf curl "/v3/apps/$(cf app "${ROUTE_SERVICE_APP_NAME}" --guid)/routes" | jq -r --arg APPS_DOMAIN "${APPS_DOMAIN}" '[.resources[] | select(.url | endswith($APPS_DOMAIN))][0].url')"

echo "route_service_domain"
echo $ROUTE_SERVICE_DOMAIN

if cf curl "/v3/service_instances?type=user-provided&names=${ROUTE_SERVICE_NAME}" | jq -e '.pagination.total_results == 0' > /dev/null; then
  cf create-user-provided-service \
    "${ROUTE_SERVICE_NAME}" \
    -r "https://${ROUTE_SERVICE_DOMAIN}";
else
  cf update-user-provided-service \
    "${ROUTE_SERVICE_NAME}" \
    -r "https://${ROUTE_SERVICE_DOMAIN}";
fi

PROTECTED_APP_GUID="$(cf app ${PROTECTED_APP_NAME} --guid)"
echo $PROTECTED_APP_GUID

cf curl "/v3/apps/$PROTECTED_APP_GUID/routes"

PROTECTED_APP_HOSTNAME="$(cf curl "/v3/apps/$PROTECTED_APP_GUID/routes" | jq -r --arg APPS_DOMAIN "${APPS_DOMAIN}" '[.resources[] | select(.url | endswith($APPS_DOMAIN))][0].host')"

echo "protected_app_hostname"
echo $PROTECTED_APP_HOSTNAME

cf bind-route-service "${APPS_DOMAIN}" "${ROUTE_SERVICE_NAME}" --hostname "${PROTECTED_APP_HOSTNAME}";
