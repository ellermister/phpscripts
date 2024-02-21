FROM php:8.2.16-zts-alpine3.19

RUN curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - | sh -s \
      pcntl

WORKDIR /app

ADD https://github.com/ellermister/phpscripts/raw/master/oracle/keep.php /app/keep.php


CMD ["php", "keep.php"]

# Run example:
# docker build -t ellermister/keep-oracle-jj-life .
# docker run -d --restart=always --name keep-oracle-jj-life ellermister/keep-oracle-jj-life
#
