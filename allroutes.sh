#!/bin/sh

spaces=$(cf spaces | grep -v Getting | grep -v -E '^name$' | grep -v -E '^$')

for space in $spaces; do
    cf target -s $space &> /dev/null
    cf routes | grep -v Getting | grep -v 'No routes' | grep -v app-protocol | grep -v -E '^$'
done
