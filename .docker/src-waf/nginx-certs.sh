#!/usr/bin/env bash

# print usage
DOMAIN=$1
if [ -z "$1" ]; then

    echo "USAGE: $0 domain.lan"
    echo ""
    echo "This will generate a non-secure self-signed wildcard certificate for given domain."
    echo "This should only be used in a development environment."
    exit
fi

# Add wildcard
WILDCARD="*.$DOMAIN"

# Set our CSR variables
SUBJ="
C=US
ST=NY
O=Local Developement
localityName=Developement
commonName=$WILDCARD
organizationalUnitName=Developement
emailAddress=
"

# Generate our Private Key, CSR and Certificate
openssl genrsa -out "nginx-self-signed.key" 2048
openssl req -new -subj "$(echo -n "$SUBJ" | tr "\n" "/")" -key "nginx-selfsigned.key" -out "nginx-selfsigned.csr"
openssl x509 -req -days 3650 -in "nginx-selfsigned.csr" -signkey "nginx-selfsigned.key" -out "nginx-selfsigned.crt"

openssl dhparam -out dhparam.pem 2048

rm "nginx-selfsigned.csr"
mv ./dhparam.pem ./etc/ssl/certs/dhparam.pem
mv ./nginx-selfsigned.crt ./etc/ssl/certs/nginx-selfsigned.crt
mv ./nginx-selfsigned.key ./etc/ssl/private/nginx-selfsigned.key

echo ""
echo "Next manual steps:"
echo "- Use nginx-selfsigned.crt and nginx-selfsigned.key to configure Apache/nginx"
echo "- Import nginx-selfsigned.crt into Chrome settings: chrome://settings/certificates > tab 'Authorities'"
