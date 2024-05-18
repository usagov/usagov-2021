#!/bin/bash

echo "Inserting environment variables into nginx config templates ... "
for FILE in /etc/nginx/*/*.conf.tmpl /etc/nginx/*.conf.tmpl; do
    if [ -f "$FILE" ]; then
        OUTFILE=${FILE%.tmpl}
	echo " generating $OUTFILE"
        envsubst "$ENV_VARIABLES" < "$FILE" > "$OUTFILE"
    fi
done
