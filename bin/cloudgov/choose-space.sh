#!/bin/sh

CLOUDDIR=$(dirname "$0")
source $CLOUDDIR/select-options.sh


case `select_opt "Curr" "Dev" "UAT" "Prod"` in
    0) echo "selected Curr";;
    1) echo "selected Dev";;
    2) echo "selected UAT";;
    3) echo "selected PROD";;
esac