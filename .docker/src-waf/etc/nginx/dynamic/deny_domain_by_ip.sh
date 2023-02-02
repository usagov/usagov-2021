#!/bin/sh
# Get IP addresses for domains in domains-deny.list. 
# If the results differ from deny-by-domain.conf, update that file.
# If there were changes AND the flag --no_reload was not passed, reload nginx.
# (--no-reload is only wanted during setup, before nginx has started.)

BASEDIR=$(dirname $0)

echo "# Restricted by domain (via cron job):" > ${BASEDIR}/deny-by-domain_new.conf
while read -r line 
do 
  ddns_record="$line" 
  if [[ ! -z $ddns_record ]]; then
     resolved_ip=`getent ahosts $line | awk '{ print $1 ; exit }'`
     if [[ ! -z $resolved_ip ]]; then
         echo "   deny $resolved_ip; # from $ddns_record" >> ${BASEDIR}/deny-by-domain_new.conf
     fi
  fi
done < ${BASEDIR}/domains-deny.list

# Update deny-by-domain.conf only if there are changes. 
CHANGES=$(diff ${BASEDIR}/deny-by-domain.conf ${BASEDIR}/deny-by-domain_new.conf)
if [[ ! -z "$CHANGES" ]]; then
    cat ${BASEDIR}/deny-by-domain_new.conf > ${BASEDIR}/deny-by-domain.conf
    if [ "$1" != "--no-reload" ]; then
	/usr/sbin/nginx -s reload	
    fi
fi
rm ${BASEDIR}/deny-by-domain_new.conf


