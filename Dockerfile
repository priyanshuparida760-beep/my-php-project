FROM php:8.1-cli
COPY . /app
WORKDIR /app
CMD php -S 0.0.0.0:$PORT bot.php
