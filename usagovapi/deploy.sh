#!/bin/bash
set -e  # Exit on any error

# Retrieve the current targeted org and space
ORG=$(cf target | awk '/org:/ {print $2}')
SPACE=$(cf target | awk '/space:/ {print $2}')

# We will use $ORG and $SPACE to get unique host names. Sandbox space names have dots, so remove those.
SPACE_NODOT=${SPACE//\./-}

# Display current target information
echo "🔍 You are deploying to:"
echo "   🏢 Org:   $ORG"
echo "   📌 Space: $SPACE"
echo "⚠️  Please verify this is the correct target before proceeding."

# User confirmation prompt
read -p "❓ Proceed with deployment? (Y/N): " -n 1 -r
echo  # Move to a new line after user input
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Deployment canceled."
    exit 1
fi

echo "✅ Proceeding with deployment..."

# Ensure required environment variables are set
if [[ -z "$API_ENDPOINT" || -z "$API_KEY" ]]; then
  echo "❌ ERROR: API_ENDPOINT and API_KEY must be set!"
  exit 1
fi

# Replace placeholders in API Proxy manifest and generate a new YML file
sed -e "s|\${API_ENDPOINT}|$API_ENDPOINT|g" \
    -e "s|\${API_KEY}|$API_KEY|g" \
    -e "s|\${ORG}|$ORG|g" \
    -e "s|\${SPACE_NODOT}|$SPACE_NODOT|g" \
    api_proxy_manifest.yml.src > api_proxy_manifest.yml

# Replace placeholders in test client manifest and generate a new YML file
sed -e "s|\${ORG}|$ORG|g" \
    -e "s|\${SPACE_NODOT}|$SPACE_NODOT|g" \
    test_client_manifest.yml.src > test_client_manifest.yml

echo "✅ Configuration prepared"

# Function to check and create missing routes
declare -a new_routes=()

function ensure_route {
  local domain="$1"
  local hostname="$2"

  if cf routes | grep -q "${hostname}.${domain}"; then
    return 0  # Route already exists, no need to output anything
  else
    cf create-route "${domain}" --hostname "${hostname}"
    new_routes+=("${hostname}.${domain}")
  fi
}

# Ensure routes exist before deployment
echo "🔄 Checking routes..."
ensure_route "apps.internal" "${ORG}-${SPACE_NODOT}-api-proxy"
ensure_route "app.cloud.gov" "${ORG}-${SPACE_NODOT}-api-proxy"
ensure_route "apps.internal" "${ORG}-${SPACE_NODOT}-test-client"
ensure_route "app.cloud.gov" "${ORG}-${SPACE_NODOT}-test-client"

# If any routes were created, display them in a single message
if [[ ${#new_routes[@]} -gt 0 ]]; then
  echo "🌐 Created routes: ${new_routes[*]}"
else
  echo "✅ All required routes already exist."
fi

# Deploy API Proxy
echo "🚀 Deploying API Proxy..."
if cf push -f api_proxy_manifest.yml ; then
  echo "✅ API Proxy deployed successfully."
else
  echo "❌ API Proxy deployment failed."
  exit 1
fi

# Deploy Test Client
echo "🚀 Deploying Test Client..."
if cf push -f test_client_manifest.yml ; then
  echo "✅ Test Client deployed successfully."
else
  echo "❌ Test Client deployment failed."
  exit 1
fi

# Add network policies
echo "🔒 Configuring network policies..."
if cf add-network-policy api-proxy test-client --protocol tcp --port 61443 > /dev/null 2>&1 \
   && cf add-network-policy test-client api-proxy --protocol tcp --port 61443 > /dev/null 2>&1; then
  echo "✅ Network policies added."
else
  echo "❌ Failed to configure network policies."
  exit 1
fi

# Clean up generated manifest
rm -f api_proxy_manifest.yml
rm -f test_client__manifest.yml
echo "✅ Cleanup complete."

echo "🎉 Deployment completed successfully!"
