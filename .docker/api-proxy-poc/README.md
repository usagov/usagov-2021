# USAGOV-2225

<a name="readme-top"></a>

## API Proxy example

This is a proof-of-concept of a very simple way of setting up an API proxy-server with basic PHP. The concept here would be that:

* We keep the secret/keys in environmental variables. They would be stored in Cloud.gov in the real implementation. They are in the docker-compose file here as as a one-off for our locals.
* We have a custom proxy script for each API. For example, the rep-api.php is used to proxy the Google-API that looks up representatives.

## How to test this Proof-of-concept

* Within this directory on our local, run `docker compose up`
* In your browser navigate to: `http://127.0.0.1:88/rep-api.php?address=6017+Cypress+Cover+Dr,+The+Colony,+TX`
* You will notice that the Google-API key is *not* exposed, yet returns the information we need.

