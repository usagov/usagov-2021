#!/bin/sh

a=0

while [ $a -lt 11 ]
do
    a=`expr $a + 1`

    curl_output=$(curl -s localhost)
    if [[ "$curl_output" == *"carouselHeaders"* ]]
    then
        echo "Attempt #"$a": Page Loaded!"
    else
        echo "Attempt #"$a": Error 429"
    fi
done
