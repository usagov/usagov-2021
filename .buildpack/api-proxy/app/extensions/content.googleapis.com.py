# Plugin to adjust key param for Google's API

print("✅ Loaded Google Civic plugin")

# Google expects 'key' instead of 'api_key'
params["key"] = API_KEY
params.pop("api_key", None)  # Remove the default

# Google expects a referer header
headers["referer"] = 'https://www.usa.gov/'
headers["Content-Type"] = 'application/json'
headers["Accept"] = 'application/json'
