# Build the caddy binary and copy it into the proxy subdirectory
caddy-v2-with-forwardproxy: Dockerfile proxy/Caddyfile
	docker compose build
	docker compose up -d
	docker compose cp caddy:/usr/bin/caddy proxy/caddy
	docker compose down

validate:
	echo "test.gov" > allow.acl
	echo "test.com" > deny.acl
	sed -i 's/tls cert.pem key.pem/# tls cert.pem key.pem/g' proxy/Caddyfile
	PORT=9999 PROXY_USERNAME=admin PROXY_PASSWORD=pass ./proxy/caddy validate --config proxy/Caddyfile
	sed -i 's/# tls cert.pem key.pem/tls cert.pem key.pem/g' proxy/Caddyfile
	rm allow.acl deny.acl
