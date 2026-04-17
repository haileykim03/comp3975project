#!/bin/bash

# Ensure SQLite file exists
if [ ! -f /home/site/wwwroot/database/database.sqlite ]; then
    mkdir -p /home/site/wwwroot/database
    touch /home/site/wwwroot/database/database.sqlite
    chmod 666 /home/site/wwwroot/database/database.sqlite
fi

# Ensure storage directories exist
mkdir -p /home/site/wwwroot/storage/framework/views
mkdir -p /home/site/wwwroot/storage/framework/cache
mkdir -p /home/site/wwwroot/storage/framework/sessions
mkdir -p /home/site/wwwroot/storage/logs
mkdir -p /home/site/wwwroot/bootstrap/cache

# Set permissions
chmod -R 775 /home/site/wwwroot/storage
chmod -R 775 /home/site/wwwroot/bootstrap/cache
chown -R www-data:www-data /home/site/wwwroot/storage
chown -R www-data:www-data /home/site/wwwroot/bootstrap/cache

# Update PHP-FPM configuration (most important for Azure)
echo "=== Updating PHP-FPM Configuration ==="

# PHP-FPM reads from pool configuration, not php.ini!
PHP_FPM_POOL="/usr/local/etc/php-fpm.d/www.conf"

if [ -f "$PHP_FPM_POOL" ]; then
    echo "Found PHP-FPM pool config: $PHP_FPM_POOL"
    
    # Add or update php_admin_value settings
    if grep -q "php_admin_value\[upload_max_filesize\]" "$PHP_FPM_POOL"; then
        echo "Updating existing php_admin_value settings..."
        sed -i.bak \
            -e 's/^php_admin_value\[upload_max_filesize\].*/php_admin_value[upload_max_filesize] = 15M/' \
            -e 's/^php_admin_value\[post_max_size\].*/php_admin_value[post_max_size] = 16M/' \
            "$PHP_FPM_POOL"
    else
        echo "Adding php_admin_value settings..."
        cat >> "$PHP_FPM_POOL" << 'EOF'

; Upload size limits for Lost & Found
php_admin_value[upload_max_filesize] = 15M
php_admin_value[post_max_size] = 16M
php_admin_value[memory_limit] = 256M
EOF
    fi
    echo "✓ Updated PHP-FPM pool configuration"
fi

# Also update php.ini if it exists (as backup)
PHP_INI="/usr/local/etc/php/conf.d/php.ini"
if [ ! -f "$PHP_INI" ]; then
    mkdir -p "$(dirname $PHP_INI)"
fi

echo "Creating/updating: $PHP_INI"
cat > "$PHP_INI" << 'EOF'
; Lost & Found upload settings
upload_max_filesize = 15M
post_max_size = 16M
memory_limit = 256M
max_execution_time = 300
EOF

echo "✓ Created $PHP_INI"

echo "=== PHP Configuration Complete ==="

# Verify after all changes
echo "Waiting for PHP-FPM to apply changes..."
sleep 2

echo "Verifying PHP settings:"
php -r 'echo "upload_max_filesize: " . ini_get("upload_max_filesize") . "\n"; echo "post_max_size: " . ini_get("post_max_size") . "\n"; echo "memory_limit: " . ini_get("memory_limit") . "\n";' 2>/dev/null || echo "Unable to verify"

echo "=== PHP Configuration Updated ==="

# Verify Cloudinary credentials are loaded
echo "=== Cloudinary Configuration Check ==="
echo "CLOUDINARY_CLOUD_NAME=${CLOUDINARY_CLOUD_NAME:-NOT SET}"
echo "CLOUDINARY_API_KEY=${CLOUDINARY_API_KEY:-NOT SET}"
echo "CLOUDINARY_API_SECRET=${CLOUDINARY_API_SECRET:-NOT SET}"
echo "====================================="

# Laravel setup
php /home/site/wwwroot/artisan migrate --force
php /home/site/wwwroot/artisan db:seed --force

php /home/site/wwwroot/artisan cache:clear
php /home/site/wwwroot/artisan config:clear
php /home/site/wwwroot/artisan config:cache
php /home/site/wwwroot/artisan route:clear
php /home/site/wwwroot/artisan view:clear
php /home/site/wwwroot/artisan view:cache

# Apply nginx config
cp /home/site/wwwroot/default /etc/nginx/sites-available/default
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Reload nginx
service nginx reload || service nginx start