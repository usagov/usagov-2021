#!/bin/bash

file=$1
if [ x"$file" != x -a -f $file ]; then
  source ~/cfevents/space-guids
  cat $file |
	sed "s/($dev)/dev/"  |
	sed "s/($dr)/dr/"  |
	sed "s/($stage)/stage/"  |
	sed "s/($prod)/prod/"  |
	sed "s/($tools)/tools/"  |
	sed "s/($shared_egress)/shared-egress/"
else
  echo Cannot read from \"$file\"
fi

