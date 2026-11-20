FROM php:8.2-apache

RUN apt-get update && apt-get install -y 

python3 

curl 

ffmpeg 

--no-install-recommends && rm -rf /var/lib/apt/lists/*

RUN curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp 

&& chmod a+rx /usr/local/bin/yt-dlp

RUN a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html 

&& chmod -R 777 /var/www/html

EXPOSE 80
