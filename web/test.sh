#!/bin/sh

a=0

while [ $a -lt 11 ]
do
    a=`expr $a + 1`

    curl_output=$(curl -s localhost)
    if [[ "$curl_output" == *"carouselHeaders"* ]]
    then
        echo "[$(date +'%Y-%m-%d %H:%M:%S.%3N')] Attempt #"$a": Page Loaded!"
    else
        echo "[$(date +'%Y-%m-%d %H:%M:%S.%3N')] Attempt #"$a": Error 429"
    fi
done
