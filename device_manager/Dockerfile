FROM php:8.2-apache

# Cập nhật hệ thống và cài đặt thư viện cần thiết cho PostgreSQL
RUN apt-get update && apt-get install -y --no-install-recommends --fix-missing \
    libpq-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    python3 \
    python3-pip \
    python3-venv \
    && docker-php-ext-install pdo pdo_pgsql zip gd

# Cài đặt các thư viện Python cần thiết cho Trình cào TKB (crawler.py)
RUN pip3 install --no-cache-dir --break-system-packages \
    requests \
    psycopg2-binary \
    sqlalchemy

# Bật module rewrite của Apache
RUN a2enmod rewrite

# Cấu hình bảo mật thư mục và cho phép ghi đè trong Apache
RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# Tăng giới hạn tải tệp của PHP lên 20MB
RUN echo "upload_max_filesize = 20M\npost_max_size = 25M\nmemory_limit = 256M" > /usr/local/etc/php/conf.d/uploads.ini

# Đặt thư mục làm việc mặc định
WORKDIR /var/www/html

# Expose cổng 80
EXPOSE 80
