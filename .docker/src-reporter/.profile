#!/bin/sh

# Despite the temptation to use #!/bin/bash, we want to keep this file as as
# POSIX sh-compatible as possible. This is to facilitate testing the 
# .profile under Alpine, which doesn't have /bin/bash, but does have ash 
# (which is itself a flavor of busybox).
ENABLE_ASH_BASH_COMPAT=1

set -e

https_proxy="https://$PROXY_USERNAME:$PROXY_PASSWORD@$(echo "$VCAP_APPLICATION" |  jq .application_uris[0] | sed 's/"//g'):$PORT"
export https_proxy
echo
echo
echo "The proxy connection URL is:"
echo "  $https_proxy"

