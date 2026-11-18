# Isto não é um Vinho — site estático + PHP (envio de emails) para Cloud Run
FROM php:8.3-apache

# Cloud Run indica a porta em $PORT (8080 por omissão)
ENV PORT=8080
# Modo manutenção (o deploy define 1 ou 0); o PassEnv deixa o .htaccess ler a variável
ENV MANUTENCAO=0
RUN printf 'PassEnv MANUTENCAO\n' > /etc/apache2/conf-available/passenv.conf && a2enconf passenv
RUN sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/security-extra.conf \
 && a2enconf servername security-extra \
 && a2enmod headers expires rewrite \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && echo "expose_php = Off" > "$PHP_INI_DIR/conf.d/zz-site.ini"

COPY --chown=www-data:www-data . /var/www/html/

# Versão dos ficheiros (cache busting): troca __VERSION__ nos HTML pelo commit do deploy
ARG VERSION=dev
RUN find /var/www/html -name '*.html' -exec sed -i "s/__VERSION__/${VERSION}/g" {} +

EXPOSE 8080
