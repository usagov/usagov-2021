#!/bin/bash

touch /tmp/bootstrap.log
echo "BOOT START" > /tmp/bootstrap.log

echo "nr-enable" >> /tmp/bootstrap.log
/home/vcap/app/nr-enable.sh 1

echo "nr-make-noise-forever" >> /tmp/bootstrap.log
nohup /home/vcap/app/nr-make-noise-forever.sh >/dev/null 2>&1 &

echo "verifying noise" >> /tmp/bootstrap.log
ps aux | grep nr-make-noise-forever >> /tmp/bootstrap.log

echo "BOOT FINISH" >> /tmp/bootstrap.log