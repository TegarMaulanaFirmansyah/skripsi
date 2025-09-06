# Deployment Guide

This guide covers how to deploy the Sentiment Analysis application to various platforms.

## 🚀 Deployment Options

### 1. Shared Hosting (cPanel, etc.)

#### Prerequisites
- PHP 8.1+ with extensions: mbstring, pdo_mysql, fileinfo, openssl, tokenizer, xml
- MySQL 5.7+ or MariaDB 10.2+
- Composer
- Git

#### Steps
1. **Upload Files**
   ```bash
   # Clone repository to your hosting
   git clone https://github.com/username/skripsi-sentiment-analysis.git
   cd skripsi-sentiment-analysis
   ```

2. **Install Dependencies**
   ```bash
   composer install --optimize-autoloader --no-dev
   ```

3. **Environment Setup**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   php artisan key:generate
   ```

4. **Database Setup**
   ```bash
   php artisan migrate
   php artisan storage:link
   ```

5. **File Permissions**
   ```bash
   chmod -R 755 storage bootstrap/cache
   chmod -R 755 public
   ```

6. **Web Server Configuration**
   - Point document root to `public/` directory
   - Enable URL rewriting (mod_rewrite for Apache)

### 2. VPS/Cloud Server (Ubuntu/CentOS)

#### Prerequisites
- Ubuntu 20.04+ or CentOS 8+
- Nginx or Apache
- PHP 8.1+ with FPM
- MySQL 8.0+
- SSL Certificate (Let's Encrypt recommended)

#### Installation Steps

1. **System Update**
   ```bash
   sudo apt update && sudo apt upgrade -y
   ```

2. **Install PHP and Extensions**
   ```bash
   sudo apt install php8.1-fpm php8.1-mysql php8.1-mbstring php8.1-xml php8.1-curl php8.1-zip php8.1-gd php8.1-cli
   ```

3. **Install Composer**
   ```bash
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   ```

4. **Install Node.js and NPM**
   ```bash
   curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
   sudo apt-get install -y nodejs
   ```

5. **Install MySQL**
   ```bash
   sudo apt install mysql-server
   sudo mysql_secure_installation
   ```

6. **Deploy Application**
   ```bash
   cd /var/www
   sudo git clone https://github.com/username/skripsi-sentiment-analysis.git
   cd skripsi-sentiment-analysis
   sudo composer install --optimize-autoloader --no-dev
   sudo npm install && sudo npm run build
   ```

7. **Configure Environment**
   ```bash
   sudo cp .env.example .env
   sudo nano .env
   # Configure database, app URL, etc.
   sudo php artisan key:generate
   sudo php artisan migrate
   sudo php artisan storage:link
   ```

8. **Set Permissions**
   ```bash
   sudo chown -R www-data:www-data /var/www/skripsi-sentiment-analysis
   sudo chmod -R 755 /var/www/skripsi-sentiment-analysis
   sudo chmod -R 775 /var/www/skripsi-sentiment-analysis/storage
   sudo chmod -R 775 /var/www/skripsi-sentiment-analysis/bootstrap/cache
   ```

#### Nginx Configuration

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    root /var/www/skripsi-sentiment-analysis/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/skripsi-sentiment-analysis/public

    <Directory /var/www/skripsi-sentiment-analysis/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

### 3. Docker Deployment

#### Dockerfile
```dockerfile
FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy existing application directory contents
COPY . /var/www

# Copy existing application directory permissions
COPY --chown=www-data:www-data . /var/www

# Install dependencies
RUN composer install --optimize-autoloader --no-dev
RUN npm install && npm run build

# Change current user to www
USER www-data

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
```

#### docker-compose.yml
```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    image: skripsi-sentiment-analysis
    container_name: sentiment-app
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - ./:/var/www
      - ./docker/php/local.ini:/usr/local/etc/php/conf.d/local.ini
    networks:
      - sentiment-network

  nginx:
    image: nginx:alpine
    container_name: sentiment-nginx
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - ./:/var/www
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    networks:
      - sentiment-network

  db:
    image: mysql:8.0
    container_name: sentiment-db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: sentiment_analysis
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_USER: sentiment_user
      MYSQL_PASSWORD: sentiment_password
    volumes:
      - dbdata:/var/lib/mysql
    networks:
      - sentiment-network

volumes:
  dbdata:
    driver: local

networks:
  sentiment-network:
    driver: bridge
```

### 4. Heroku Deployment

#### Prerequisites
- Heroku CLI
- Git

#### Steps
1. **Create Heroku App**
   ```bash
   heroku create your-app-name
   ```

2. **Add Buildpacks**
   ```bash
   heroku buildpacks:add heroku/php
   heroku buildpacks:add heroku/nodejs
   ```

3. **Configure Environment**
   ```bash
   heroku config:set APP_KEY=$(php artisan key:generate --show)
   heroku config:set APP_ENV=production
   heroku config:set APP_DEBUG=false
   heroku config:set DB_CONNECTION=mysql
   # Add other environment variables
   ```

4. **Add MySQL Database**
   ```bash
   heroku addons:create cleardb:ignite
   ```

5. **Deploy**
   ```bash
   git push heroku main
   heroku run php artisan migrate
   heroku run php artisan storage:link
   ```

## 🔧 Production Optimizations

### 1. Performance
```bash
# Optimize Composer autoloader
composer install --optimize-autoloader --no-dev

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache
```

### 2. Security
- Set `APP_DEBUG=false` in production
- Use strong database passwords
- Enable HTTPS with SSL certificates
- Configure proper file permissions
- Use environment variables for sensitive data

### 3. Monitoring
- Set up log monitoring
- Configure error tracking (Sentry, Bugsnag)
- Monitor server resources
- Set up automated backups

## 📊 Environment Variables

### Required Variables
```env
APP_NAME="Sentiment Analysis"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

SESSION_DRIVER=file
SESSION_LIFETIME=120
```

### Optional Variables
```env
# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls

# Cache Configuration
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## 🚨 Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   sudo chown -R www-data:www-data /var/www/your-app
   sudo chmod -R 755 /var/www/your-app
   sudo chmod -R 775 /var/www/your-app/storage
   ```

2. **Database Connection Issues**
   - Check database credentials in `.env`
   - Ensure database server is running
   - Verify network connectivity

3. **File Upload Issues**
   - Check `upload_max_filesize` in PHP config
   - Verify `post_max_size` settings
   - Ensure storage directory is writable

4. **Session Issues**
   - Check session directory permissions
   - Verify session configuration
   - Clear session files if needed

### Log Files
- Application logs: `storage/logs/laravel.log`
- Web server logs: `/var/log/nginx/` or `/var/log/apache2/`
- PHP logs: `/var/log/php8.1-fpm.log`

## 📈 Scaling Considerations

### For High Traffic
- Use Redis for session storage
- Implement database connection pooling
- Use CDN for static assets
- Consider load balancing
- Implement caching strategies

### For Large Datasets
- Optimize database queries
- Use database indexing
- Implement data pagination
- Consider data archiving
- Use background job processing

## 🔄 Backup Strategy

### Database Backup
```bash
# Daily backup script
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

### File Backup
```bash
# Backup application files
tar -czf app_backup_$(date +%Y%m%d).tar.gz /var/www/your-app
```

### Automated Backup
Set up cron jobs for automated backups:
```bash
# Add to crontab
0 2 * * * /path/to/backup-script.sh
```

This deployment guide should help you successfully deploy the Sentiment Analysis application to various platforms. Choose the deployment method that best fits your needs and infrastructure.
