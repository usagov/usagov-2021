#!/bin/sh

BASEDIR=$(dirname $0)

echo "# Restricted by domain (via cron job):" > ${BASEDIR}/deny-by-domain.conf
while read -r line 
do 
  ddns_record="$line" 
  if [[ ! -z $ddns_record ]]; then
     resolved_ip=`getent ahosts $line | awk '{ print $1 ; exit }'`
     if [[ ! -z $resolved_ip ]]; then 
         echo "   deny $resolved_ip; # from $ddns_record" > ${BASEDIR}/deny-by-domain.conf
     fi
  fi
done < ${BASEDIR}/domains-deny.list
