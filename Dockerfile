# 1️⃣ Usamos la imagen oficial de PHP 8.4 con Apache
FROM php:8.4-apache

# 2️⃣ Instalamos extensiones necesarias para Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mysqli

# 3️⃣ Habilitamos el módulo de Apache para Laravel
RUN a2enmod rewrite

# 4️⃣ Instalamos Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

#  Copiamos el archivo de configuración de Apache
COPY ./000-default.conf /etc/apache2/sites-available/000-default.conf

# 5️⃣ Configuramos el directorio de trabajo
WORKDIR /var/www/html

# 6️⃣ Copiamos los archivos del proyecto al contenedor
COPY . .

# 7️⃣ Instalamos las dependencias de Laravel
RUN composer install --no-dev --optimize-autoloader

# 8️⃣ Damos permisos a storage y bootstrap/cache
RUN chmod -R 777 storage bootstrap/cache

# 9️⃣ Exponemos el puerto 80 para Apache
EXPOSE 80

# 🔟 Comando de inicio del contenedor
CMD ["apache2-foreground"]
