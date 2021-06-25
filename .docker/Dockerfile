FROM alpine:3.12
LABEL MAINTAINER=dan@mobomo.com

ARG S6_VERSION
ENV S6_VERSION ${S6_VERSION:-v2.2.0.3}

ARG GITBRANCH
ENV GITBRANCH ${GITBRANCH:-none}

ARG GITCOMMIT
ENV GITCOMMIT ${GITCOMMIT:-none}

ARG GITTAG
ENV GITTAG ${GITTAG:-none}

ADD https://github.com/just-containers/s6-overlay/releases/download/${S6_VERSION}/s6-overlay-amd64.tar.gz /tmp/s6overlay.tar.gz

# Depenancies
RUN apk upgrade --update --no-cache \
    && apk add --no-cache --virtual .persistent-deps \
    ca-certificates \
    curl \
    git \
    tar \
    xz \
    vim \
    jq \
    mysql-client \
    nginx \
    php7 \
    php7-fpm \
    php7-bcmath \
    php7-bz2 \
    php7-calendar \
    php7-curl \
    php7-ctype \
    php7-dom \
    php7-fileinfo \
    php7-ftp \
    php7-gd \
    php7-intl \
    php7-iconv \
    php7-imagick \
    php7-imap \
    php7-intl \
    php7-json \
    php7-ldap \
    php7-mbstring \
    php7-mysqlnd \
    php7-openssl \
    php7-opcache \
    php7-pcntl \
    php7-pdo \
    php7-pdo_mysql \
    php7-pdo_sqlite \
    php7-pear \
    php7-phar \
    php7-posix \
    php7-recode \
    php7-session \
    php7-simplexml \
    php7-shmop \
    php7-soap \
    php7-sockets \
    php7-sodium \
    php7-sqlite3 \
    php7-sysvmsg \
    php7-sysvsem \
    php7-sysvshm \
    php7-tokenizer \
    php7-tidy \
    php7-xsl \
    php7-xml \
    php7-xmlreader \
    php7-xmlwriter \
    php7-zip \
    php7-zlib \
    && mkdir -p /var/www/web/sites/default/files \
    # s6 supervisor setup
    && tar xzf /tmp/s6overlay.tar.gz -C / \
    && rm /tmp/s6overlay.tar.gz \
    # webserver setup
    && mkdir -p /var/www/ \
    && chown -R nginx:nginx /var/www \
    && mkdir -p /var/log/nginx \
    && chown -R nginx:nginx /var/log/nginx \
    && ln -s /dev/stderr /var/log/nginx/error.log \
    && ln -s /dev/stdout /var/log/nginx/access.log \
    && mkdir -p /run/nginx/ \
    && touch /run/nginx/nginx.pid \
    # php setup
    && ln -s /etc/php7/php-fpm.d /etc/php7/pool.d \
    && ln -s /usr/sbin/php-fpm7 /usr/sbin/php-fpm \
    # drupal setup
    && cd /tmp \
    && curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer \
    && chmod a+x /usr/local/bin/composer \
    # cleanup
    && rm -rf /var/cache/apk/*

COPY --chown=nginx:nginx .docker/etc /etc
COPY --chown=nginx:nginx bootstrap.sh /var/www/bootstrap.sh
COPY --chown=nginx:nginx composer.json /var/www/composer.json
COPY --chown=nginx:nginx composer.lock /var/www/composer.lock
COPY --chown=nginx:nginx web /var/www/web
COPY --chown=nginx:nginx config /var/www/config
COPY --chown=nginx:nginx vendor /var/www/vendor
COPY --chown=nginx:nginx patches /var/www/patches

RUN rm -f /var/www/web/sites/default/settings.local.php \
    && chown nginx:nginx /var/www \
    && find /var/www -group 0 -user 0 -print0 | xargs -P 0 -0 --no-run-if-empty chown --no-dereference nginx:nginx \
    && echo "    built:" $(date) >> /etc/motd \
    && echo "    branch: " $GITBRANCH >> /etc/motd \
    && echo "    gittag: " $GITTAG >> /etc/motd \
    && echo "    commit: " $GITCOMMIT >> /etc/motd \
    && echo >> /etc/motd \
    cp /etc/motd /var/www/build.info

ENV PATH=/var/www/vendor/bin:$PATH

EXPOSE 80

WORKDIR /var/www

# Init s6
CMD [ "/init" ]
