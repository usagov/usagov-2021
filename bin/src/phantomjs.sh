#!/bin/sh
if [ $(uname -m) == 'aarch64' ]; then
  apk add --no-cache curl fontconfig \
  && mkdir /tmp/phantomjs-prereq /tmp/phantomjs \
  && curl -sSLfk -o /tmp/phantomjs-prereq.tar.gz https://github.com/dustinblackman/phantomized/releases/download/2.1.1a/dockerized-phantomjs.tar.gz \
  && curl -sSLf -o /tmp/phantomjs.tar.bz2 https://bitbucket.org/ariya/phantomjs/downloads/phantomjs-2.1.1-linux-x86_64.tar.bz2 \
  && tar xzf /tmp/phantomjs-prereq.tar.gz -C /tmp/phantomjs-prereq \
  && tar xjf /tmp/phantomjs.tar.bz2 -C /tmp/phantomjs --strip-components=1 \
  && cp -nr /tmp/phantomjs-prereq/lib/x86_64-linux-gnu /lib \
  && cp -nr /tmp/phantomjs-prereq/lib64 /lib64 \
  && cp -nr /tmp/phantomjs-prereq/usr/lib/x86_64-linux-gnu /usr/lib \
  && cp /tmp/phantomjs/bin/phantomjs /usr/local/bin/phantomjs \
  && ln -s /usr/local/bin/phantomjs /usr/bin/phantomjs \
  && rm -rf /tmp/*
fi
