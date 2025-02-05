"""
Flask API Proxy Module.

A universal API proxy that securely forwards requests to an external API,
injecting credentials while preventing client exposure.
"""

import logging
import os

import requests
from flask import Flask, Response, jsonify, request

app = Flask(__name__)

# Configure logging
logging.basicConfig(
    level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s"
)
logger = logging.getLogger(__name__)

# Load API configuration
API_ENDPOINT = os.getenv("API_ENDPOINT")  # Base API URL (e.g., https://api.example.com)
API_KEY = os.getenv("API_KEY")  # API Key for authentication

@app.route("/proxy", methods=["GET", "POST", "PUT", "DELETE"])
def proxy_request():
    """Universal API Proxy that securely forwards requests with API key injection."""

    if not API_ENDPOINT or not API_KEY:
        logger.error("Missing API configuration (API_ENDPOINT or API_KEY)")
        return jsonify({"error": "Missing API configuration"}), 500

    method = request.method
    params = request.args.to_dict()
    headers = {"Content-Type": "application/json"}

    # Inject API key into query parameters
    params["api_key"] = API_KEY

    # Handle request body for POST/PUT
    data = request.get_json() if method in ["POST", "PUT"] else None

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
