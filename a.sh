#!/bin/bash

export IP_ALLOWED=$(cat << EOT
1.1.160.164
        50.81.160.164
52.222.122.97/32
       100.36.151.190
       52.222.122.97/32
       52.222.123.172/32
       159.142.0.0/16
EOT
);

export IPS_ALLOWED="";
if [ ! -z "$IP_ALLOWED" ]; then
  read -r -a array <<< $IP_ALLOWED;
  for element in "${array[@]}"; do
      if [ ! -z "$element" ]; then
        echo "entrypoint: ALLOWING $element";
        export IPS_ALLOWED=$'\n\tallow '$element';'$IPS_ALLOWED;
      fi;
  done;
fi;

echo "IPS_ALLOWED=${IPS_ALLOWED}";
