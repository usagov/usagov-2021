# ~/.profile: executed by Bourne-compatible login shells.

### If we detect PROXYROUTE env var, then set HTTP/S Proxy vars:
if [ -n "$PROXYROUTE" ]; then
   export HTTPS_PROXY=$PROXYROUTE
   export HTTP_PROXY=$PROXYROUTE
   export https_proxy=$PROXYROUTE
   export http_proxy=$PROXYROUTE
fi