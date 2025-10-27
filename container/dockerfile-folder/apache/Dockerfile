FROM php:8.3-apache

#拡張モジュールをインストール
RUN docker-php-ext-install mysqli 
RUN apt-get update
RUN apt-get install -y vim
RUN docker-php-ext-install pdo_mysql