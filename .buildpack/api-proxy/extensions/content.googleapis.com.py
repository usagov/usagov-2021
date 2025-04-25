# Plugin to adjust key param for Google's API

import os

if os.getenv("VERBOSE") == "1":
    print("✅ Loaded Google Civic plugin")

# Google expects 'key' instead of 'api_key'
params["key"] = API_KEY
params.pop("api_key", None)  # Remove the default

headers["Content-Type"] = 'application/json'
headers["Accept"] = 'application/json'
