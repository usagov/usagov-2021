"""
Flask API Proxy Module.

A universal API proxy that securely forwards requests to an external API,
injecting credentials while preventing client exposure.
"""

import logging
import os

import json
import requests
import requests_cache
from urllib.parse import unquote
from flask import Flask, Response, jsonify, request

from flask_limiter import Limiter
from flask_limiter.util import get_remote_address

app = Flask(__name__)
limiter = Limiter(
    get_remote_address,
    app=app,
    default_limits=["200 per day", "50 per hour"],
    storage_uri="memory://",
)

requests_cache.install_cache(
    'api-proxy-cache',
    backend='filesystem',
    use_cache_dir=False,
    cache_control=True,
    expire_after=86400,
    allowable_codes=[200, 400],
    match_headers=['Accept-Language'],
    stale_if_error=True,
)

# Configure logging
logging.basicConfig(
    level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s"
)
logger = logging.getLogger(__name__)

# Load API configuration
VCAP_SERVICES = os.getenv("VCAP_SERVICES")  # VCAP_SERVICES
VCAP_JSON = json.loads(VCAP_SERVICES) # Convert to JSON
KEY_STORAGE = VCAP_JSON["user-provided"][0]["credentials"] # Get credentials

@app.route("/proxy", methods=["GET", "POST", "PUT", "DELETE"])
def proxy_request():
    """Universal API Proxy that securely forwards requests with API key injection."""

    params = request.args.to_dict()

    if params["keyname"] in KEY_STORAGE.keys():
        API_KEY = KEY_STORAGE[params["keyname"]]["APIKEY"]
        API_ENDPOINT = unquote(KEY_STORAGE[params["keyname"]]["DOMAIN"] + params["endpoint"])

        method = request.method
        headers = {"Content-Type": "application/json"}

        # Inject API key into query parameters
        params["api_key"] = API_KEY

        # Handle request body for POST/PUT
        data = request.get_json() if method in ["POST", "PUT"] else None

        # Make the proxy extensible by loading files when the domain matches.
        logger.info("Check for extensions")
        filenames = next(os.walk("extensions"), (None, None, []))[2]  # [] if no file
        for filename in filenames:
            if filename == KEY_STORAGE[params["keyname"]]["DOMAIN"].split("://")[1] + ".py":
                exec(open("extensions/" + filename).read())

        # Remove proxy-specific parameters
        del params["endpoint"]
        del params["keyname"]

        logger.info(
            "Forwarding %s request to %s with params %s", method, API_ENDPOINT, params
        )

        try:
            response = requests.request(
                method, API_ENDPOINT, params=params, json=data, headers=headers, timeout=10
            )
            logger.info("API response status: %s", response.status_code)
            return jsonify(response.json()), response.status_code
        except requests.RequestException as e:
            logger.error("API request failed: %s", str(e))
            return jsonify({"error": "Failed to contact API", "details": str(e)}), 500
    else:
        logger.error("Key by that name not found in keystore, no keys available. Rejecting request.")
        return jsonify({"error": "Key by that name not found in keystore, no keys available. Rejecting request."}), 500

@app.route("/", methods=["CONNECT"])
def handle_connect():
    """Handles CONNECT requests to prevent misuse as a forward proxy."""
    logger.warning("Received a CONNECT request. This is not a forward proxy.")
    return Response(
        "CONNECT method is not supported. Use direct HTTPS requests.", status=405
    )

if __name__ == "__main__":
    port = int(os.getenv("PORT", 8080))
    logger.info("Starting Flask API Proxy on port %s", port)
    app.run(host="0.0.0.0", port=port)
