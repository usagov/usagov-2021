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

# Build and some of image configuration
RUN apk upgrade --update --no-cache \
    && apk add --no-cache --virtual .persistent-deps \
    openssh \
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
    php7-pecl-apcu \
    php7-pcntl \
    php7-pdo \
    php7-pdo_mysql \
    php7-pdo_sqlite \
    php7-pear \
    php7-phar \
    php7-posix \
    php7-recode \
    php7-redis \
    php7-simplexml \
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
    && mkdir -p /var/www/web/sites/default/files
    # s6 supervisor setup
RUN tar xzf /tmp/s6overlay.tar.gz -C / \
    && rm /tmp/s6overlay.tar.gz
    # sshd setup
RUN ssh-keygen -A \
    && chown root:root /var/empty/ \
    && echo "root:Docker!" | chpasswd \
    && echo "export PS1=\"\n\e[33m\$ENVIRONMENT\e[39m \\\w\\\n\\\\$ \"" > /etc/profile.d/prompt.sh \
    && echo "cd /var/www" >> /etc/profile.d/prompt.sh \
    && echo "export PATH=\"/var/www/vendor/bin:\$PATH\""  >> /etc/profile.d/prompt.sh
    # webserver setup
RUN mkdir -p /var/www/ \
    && chown -R nginx:nginx /var/www \
    && mkdir -p /var/log/nginx \
    && chown -R nginx:nginx /var/log/nginx \
    && ln -s /dev/stderr /var/log/nginx/error.log \
    && ln -s /dev/stdout /var/log/nginx/access.log \
    && mkdir -p /run/nginx/ \
    && touch /run/nginx/nginx.pid
    # php setup
RUN ln -s /etc/php7/php-fpm.d /etc/php7/pool.d \
    && ln -s /usr/sbin/php-fpm7 /usr/sbin/php-fpm \
    # # newrelic setup
    # && export NEWRELIC_LATEST=$(curl -sS https://download.newrelic.com/php_agent/release/ | sed -n 's/.*>\(.*linux-musl\).tar.gz<.*/\1/p') \
    # && curl -L https://download.newrelic.com/php_agent/release/{$NEWRELIC_LATEST}.tar.gz 18 | tar -C /tmp -zx \
    # && NR_INSTALL_USE_CP_NOT_LN=1 NR_INSTALL_SILENT=1 /tmp/newrelic-php5-*/newrelic-install install \
    # && rm -rf /tmp/newrelic-php5-* \
    # && echo "extension = \"newrelic.so\"" > /etc/php7/conf.d/newrelic.ini \
    # drupal setup
    && cd /tmp \
    && curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer \
    && chmod a+x /usr/local/bin/composer \
    && composer self-update --1 \
    && composer global require drush/drush:10.* drupal/console:~1.0 --prefer-dist --optimize-autoloader \
    && composer global update --no-plugins --no-scripts \
    && ln -s /root/.composer/vendor/bin/drush /usr/local/bin/drush \
    && chmod a+x /usr/local/bin/drush /root/.composer/vendor/bin/drush \
    # cleanup
    && rm -rf /var/cache/apk/*

COPY .docker/etc /etc
COPY bootstrap.sh /var/www/bootstrap.sh
COPY composer.json /var/www/composer.json
COPY composer.lock /var/www/composer.lock
COPY config /var/www/config
COPY web /var/www/web
#COPY vendor /var/www/vendor
#COPY patches /var/www/patches
COPY sqlite.db /var/www/sqlite.db

RUN chown nginx:nginx /var/www/sqlite.db \
    && chown nginx:nginx /var/www

# This contianer is not fully functional 
# it is assuming a volume will be mounted at runtime to /var/www/web/sites/default/files
# which will contain all the files necessary for the container to be fully functional

RUN echo "    built:" $(date) >> /etc/motd \
    && echo "    branch: " $GITBRANCH >> /etc/motd \
    && echo "    gittag: " $GITTAG >> /etc/motd \
    && echo "    commit: " $GITCOMMIT >> /etc/motd \
    && echo >> /etc/motd \
    cp /etc/motd /var/www/build.info

ENV PATH=/var/www/vendor/bin:$PATH

EXPOSE 80 443 2222

WORKDIR /var/www

# Init s6
CMD [ "/init" ]
