FROM php:8.2-fpm-alpine

# Nginx install karo
RUN apk add --no-cache nginx

# MySQLi extension install karo
RUN docker-php-ext-install mysqli

# Nginx config copy karo
COPY nginx.conf /etc/nginx/nginx.conf

# Saari files copy karo
COPY . /var/www/html/

# Nginx ke liye folder banao
RUN mkdir -p /run/nginx

# Port 10000 expose karo (Render ka default)
EXPOSE 10000

# PHP-FPM aur Nginx dono start karo
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]