# See "Adding custom Caddy modules" here:
# https://hub.docker.com/_/caddy

FROM caddy:2.7-builder AS builder

ARG GOARCH=amd64
RUN xcaddy build \
    --with github.com/caddyserver/forwardproxy@caddy2

FROM caddy:2.7-alpine

RUN apk update
RUN apk upgrade
# Unclear whether we actually need this...
RUN apk add nss-tools

COPY --from=builder /usr/bin/caddy /usr/bin/caddy
COPY Caddyfile /etc/caddy/Caddyfile
COPY .profile /srv/.profile

# This shouldn't be necessary once we have docker-compose properly calling our
# .profile on startup; we do this here so that caddy will start up with our
# Caddyfile, which refers to them.
RUN touch /srv/allow.acl /srv/deny.acl

EXPOSE 8080
