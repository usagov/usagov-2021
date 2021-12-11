#!/bin/bash

export DOMAIN="usa.loc"
export SUBS="star"
export SERIAL=1

function gen_ca {
    # CERTS: mimics mysql_ssl_rsa_setup, but adds custom -subj
    SUBDOMCA=${1-*}
    if [ "$SUBDOMCA" == "star" ]; then SUBDOMCA='*'; fi;

    openssl req -newkey rsa:2048 -days 3650 -nodes \
        -keyout ./ca-key.pem \
        -out    ./ca-req.pem \
        -config <(
cat <<-EOF1
[req]
default_bits = 2048
prompt = no
default_md = sha256
req_extensions = req_ext
distinguished_name = dn

[ dn ]
C=US
ST=Virginia
L=Falls Church
O=DAN
OU=Docker Domain
emailAddress=nobody@localhost
CN = ${SUB}.${DOMAIN}

[ req_ext ]
subjectAltName = @alt_names

[ alt_names ]
DNS.1 = ${SUBDOMCA}.${DOMAIN}
EOF1
)

    openssl rsa \
        -in  ./ca-key.pem \
        -out ./ca-key.pem

    openssl x509 -sha256 -days 3650 -set_serial $SERIAL -req \
        -in      ./ca-req.pem \
        -signkey ./ca-key.pem \
        -out     ./ca.pem
    ((SERIAL++))
}

function gen_certs {
    SUBC=${1-star}
    if [ "$SUBC" == "*" ]; then SUBC='star'; fi;

    gen_cert "$SUBC" server
    gen_cert "$SUBC" client

    openssl verify \
        -CAfile ./ca.pem \
                ./${SUBC}-server-cert.pem \
                ./${SUBC}-client-cert.pem
}

function gen_cert {
    SUBDOM=${1-star}
    SUBNAM="$SUBDOM"
    if [ "$SUBDOM" == "star" ]; then SUBDOM='*'; fi;
    LOC=${2-server}

    echo "GEN_CERT SUBDOM=$SUBDOM SUBNAM=$SUBNAM LOC=$LOC"

    openssl req -newkey rsa:2048 -days 3650 -nodes \
        -keyout ./${SUBNAM}-${LOC}-key.pem \
        -out    ./${SUBNAM}-${LOC}-req.pem \
        -config <(
cat <<-EOF2
[req]
default_bits = 2048
prompt = no
default_md = sha256
req_extensions = req_ext
distinguished_name = dn

[ dn ]
C=US
ST=Virginia
L=Falls Church
O=DAN
OU=Docker Domain
emailAddress=nobody@localhost
CN = ${SUB}.${DOMAIN}

[ req_ext ]
subjectAltName = @alt_names

[ alt_names ]
DNS.1 = ${SUBDOM}.${DOMAIN}
EOF2
)

    openssl rsa \
        -in  ./${SUBNAM}-${LOC}-key.pem \
        -out ./${SUBNAM}-${LOC}-key.pem

    openssl x509 -sha256 -days 3650 -set_serial $SERIAL -req \
        -in    ./${SUBNAM}-${LOC}-req.pem \
        -CA    ./ca.pem \
        -CAkey ./ca-key.pem \
        -out   ./${SUBNAM}-${LOC}-cert.pem

    openssl req -new -sha256 \
        -key ./${SUBNAM}-${LOC}-key.pem \
        -out ./${SUBNAM}-${LOC}-csr.pem \
        -config <(
cat <<-EOF3
[req]
default_bits = 2048
prompt = no
default_md = sha256
req_extensions = req_ext
distinguished_name = dn

[ dn ]
C=US
ST=Virginia
L=Falls Church
O=DAN
OU=Docker Domain
emailAddress=nobody@localhost
CN = ${SUB}.${DOMAIN}

[ req_ext ]
subjectAltName = @alt_names

[ alt_names ]
DNS.1 = ${SUBDOM}.${DOMAIN}
EOF3
)

    ((SERIAL++))
    # sudo security add-trusted-cert -d -r trustRoot ./${SUBNAM}-${LOC}-cert.pem
}

gen_ca
for SUB in $SUBS; do
    gen_certs $SUB
done;
