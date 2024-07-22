#!/usr/bin/env bash

#echo=echo
UNSET=$1

startorg=gsa-tts-usagov
keyname=cfevents

SERVICE_KEY=$(cf service-key ${keyname}-service-account ${keyname}-service-key | tail -n +3)
echo $SERVICE_KEY

SERVICE_USER=$( echo $SERVICE_KEY | jq -r '.credentials.username')
echo $SERVICE_USER

if [ x"$UNSET" = "xunset" ]; then
    $echo cf unset-space-role $SERVICE_USER $startorg dev SpaceDeveloper
    $echo cf unset-space-role $SERVICE_USER $startorg stage SpaceDeveloper
    $echo cf unset-space-role $SERVICE_USER $startorg prod SpaceDeveloper
    $echo cf unset-space-role $SERVICE_USER $startorg tools SpaceDeveloper
    $echo cf unset-space-role $SERVICE_USER $startorg shared-egress SpaceDeveloper
    $echo cf unset-space-role $SERVICE_USER $startorg dr SpaceDeveloper
else
    $echo cf set-space-role $SERVICE_USER $startorg dev SpaceDeveloper
    $echo cf set-space-role $SERVICE_USER $startorg stage SpaceDeveloper
    $echo cf set-space-role $SERVICE_USER $startorg prod SpaceDeveloper
    $echo cf set-space-role $SERVICE_USER $startorg tools SpaceDeveloper
    $echo cf set-space-role $SERVICE_USER $startorg shared-egress SpaceDeveloper
    $echo cf set-space-role $SERVICE_USER $startorg dr SpaceDeveloper
fi
