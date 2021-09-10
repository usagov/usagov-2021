FROM alpine:3.14
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
    php8 \
    php8-fpm \
    php8-bcmath \
    php8-bz2 \
    php8-calendar \
    php8-curl \
    php8-ctype \
    php8-dom \
    php8-fileinfo \
    php8-ftp \
    php8-gd \
    php8-intl \
    php8-iconv \
    php8-imap \
    php8-intl \
    php8-json \
    php8-ldap \
    php8-mbstring \
    php8-mysqlnd \
    php8-openssl \
    php8-opcache \
    php8-pcntl \
    php8-pdo \
    php8-pdo_mysql \
    php8-pdo_sqlite \
    php8-pear \
    php8-pecl-imagick \
    php8-phar \
    php8-posix \
    php8-session \
    php8-simplexml \
    php8-shmop \
    php8-soap \
    php8-sockets \
    php8-sodium \
    php8-sqlite3 \
    php8-sysvmsg \
    php8-sysvsem \
    php8-sysvshm \
    php8-tokenizer \
    php8-tidy \
    php8-xsl \
    php8-xml \
    php8-xmlreader \
    php8-xmlwriter \
    php8-zip \
    php8-zlib \
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
    && ln -s /usr/bin/php8 /usr/bin/php \
    && ln -s /usr/sbin/php-fpm8 /usr/sbin/php-fpm \
    # drupal setup
    && cd /tmp \
    && curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/bin/composer \
    && chmod a+x /usr/bin/composer \
    # cleanup
    && rm -rf /var/cache/apk/*

ENV PATH=/var/www/vendor/bin:$PATH

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
    && find /var/www -not -user $(id -u nginx) -not -group $(id -g nginx) -print0 | xargs -P 0 -0 --no-run-if-empty chown --no-dereference nginx:nginx \
    && echo "    built:" $(date) >> /etc/motd \
    && echo "    branch: " $GITBRANCH >> /etc/motd \
    && echo "    gittag: " $GITTAG >> /etc/motd \
    && echo "    commit: " $GITCOMMIT >> /etc/motd \
    && echo >> /etc/motd \
    && cp /etc/motd /var/www/build.info \
    && echo "PATH=$PATH" > /etc/profile.d/drupal.sh


EXPOSE 80

WORKDIR /var/www

# Init s6
CMD [ "/init" ]
