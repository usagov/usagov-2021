#!/bin/bash

echo "Inserting PROXYROUTE value into nginx config templates ... "
for FILE in /etc/nginx/*/*.conf.tmpl /etc/nginx/*.conf.tmpl; do
    if [ -f "$FILE" ]; then
        OUTFILE=${FILE%.tmpl}
	echo " generating $OUTFILE"
        envsubst "\$PROXYROUTE" < "$FILE" > "$OUTFILE"
    fi
done
