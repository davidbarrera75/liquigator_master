
# Production stage
FROM php:7.2-apache

# See https://github.com/mlocati/docker-php-extension-installer for documentation
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/



RUN apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y git

# Install needed php extensions
RUN install-php-extensions \
    bcmath \
    calendar \
    gd \
    opcache \
    pdo_mysql \
    zip



RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add composer to server
# COPY --from=build /usr/bin/composer /usr/local/bin/composer

# Setup Apache mods
RUN a2enmod rewrite expires headers

# Override Apache port to use 8888
RUN sed -i "s/^Listen.*/Listen 8888/g" "/etc/apache2/ports.conf"
RUN sed -i "s/VirtualHost \*\:80/VirtualHost \*\:8888/g" /etc/apache2/sites-available/*.conf
# Setup Apache to use public folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
# Apache Security modifications
ENV SECURITY_CONF_FILE /etc/apache2/conf-enabled/security.conf
RUN sed -i "s/^ServerTokens.*$/ServerTokens Prod/g" "${SECURITY_CONF_FILE}"
RUN sed -i "s/^ServerSignature.*$/ServerSignature Off/g" "${SECURITY_CONF_FILE}"
RUN sed -i "s/^\#Header/Header/g" "${SECURITY_CONF_FILE}"


RUN cd /usr/local/etc/php/conf.d/ && \
    echo 'memory_limit = -1' >> /usr/local/etc/php/conf.d/docker-php-ram-limit.ini

# Copy data
WORKDIR /var/www/html
COPY . .
# Copy PHP dependencies
# COPY --from=build /app/vendor/ ./vendor/
# Fix ownership
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 8888
USER root
