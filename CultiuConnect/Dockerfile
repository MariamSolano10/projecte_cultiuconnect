FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get install -y apache2 php libapache2-mod-php php-mysql php-mbstring && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Activar mod_rewrite (important per .htaccess)
RUN a2enmod rewrite

# Suprimir avís ServerName
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

# Permetre .htaccess
RUN echo "<Directory /var/www/html>\n\
    AllowOverride All\n\
</Directory>" > /etc/apache2/conf-available/override.conf \
    && a2enconf override

# Permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]