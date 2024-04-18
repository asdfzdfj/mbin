# Bare Metal/VM Installation

> [!NOTE]
> Mbin is still in development.

Below is a step-by-step guide of the process for creating your own Mbin instance from the moment a new VPS/VM is created or directly on bare-metal.  
This is a preliminary outline that will help you launch an instance for your own needs.

This guide is aimed for Debian / Ubuntu distribution servers, but it could run on any modern Linux distro. This guide will however uses the `apt` commands.

## Minimum hardware requirements

**CPU:** 2 cores (>2.5 GHz)  
**RAM:** 4GB (more is recommended for large instances)  
**Storage:** 20GB (more is recommended, especially if you have a lot of remote/local magazines and/or have a lot of (local) users)

## Firewall

If you have a firewall installed (or you're behind a NAT), be sure to open port `443` for the web server. Mbin should run behind a reverse proxy like Nginx.

## Installing Requirements

### System Prerequisites

Bring your system up-to-date:

```bash
sudo apt-get update && sudo apt-get upgrade -y
```

Install requirements:

```bash
sudo apt-get install lsb-release ca-certificates curl wget unzip gnupg apt-transport-https software-properties-common python3-launchpadlib git redis-server postgresql postgresql-contrib nginx acl -y
```

### PHP and Composer

On **Ubuntu 22.04 LTS** or older, prepare latest PHP package repositoy (8.2) by using a Ubuntu PPA (this step is optional for Ubuntu 23.10 or later) via:

```bash
sudo add-apt-repository ppa:ondrej/php -y
```

On **Debian 12** or later, you can install the latest PHP package repository (this step is optional for Debian 13 or later) via:

```bash
sudo tee /etc/apt/sources.list.d/php.list <<EOF
deb https://packages.sury.org/php/ $(lsb_release -sc) main
EOF
```

Install PHP 8.2 with some important PHP extensions:

```bash
sudo apt-get update
sudo apt-get install php8.2 php8.2-common php8.2-fpm php8.2-cli php8.2-amqp php8.2-pgsql php8.2-gd php8.2-curl php8.2-xml php8.2-redis php8.2-mbstring php8.2-zip php8.2-bz2 php8.2-intl php8.2-bcmath -y
```

Install Composer:

```bash
sudo curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

### NodeJS (frontend tools)

1. Prepare and download repository keyring:

```bash
sudo mkdir -p /etc/apt/keyrings
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
```

2. Setup apt repository:

```bash
NODE_MAJOR=20
sudo tee /etc/apt/sources.list.d/nodesource.list <<EOF
deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main
EOF
```

3. Update and install NodeJS:

```bash
sudo apt-get update
sudo apt-get install nodejs -y
```

### RabbitMQ

Based on [RabbitMQ Install](https://www.rabbitmq.com/install-debian.html#apt-quick-start-cloudsmith)

1. Download keyrings for repository:

```bash
## Team RabbitMQ's main signing key
curl -1sLf "https://keys.openpgp.org/vks/v1/by-fingerprint/0A9AF2115F4687BD29803A206B73A36E6026DFCA" | sudo gpg --dearmor | sudo tee /usr/share/keyrings/com.rabbitmq.team.gpg > /dev/null
## Community mirror of Cloudsmith: modern Erlang repository
curl -1sLf https://ppa1.novemberain.com/gpg.E495BB49CC4BBE5B.key | sudo gpg --dearmor | sudo tee /usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg > /dev/null
## Community mirror of Cloudsmith: RabbitMQ repository
curl -1sLf https://ppa1.novemberain.com/gpg.9F4587F226208342.key | sudo gpg --dearmor | sudo tee /usr/share/keyrings/rabbitmq.9F4587F226208342.gpg > /dev/null
```

2. Add apt repositories for Erlang and RabbitMQ, maintained by Team RabbitMQ:

```bash
sudo tee /etc/apt/sources.list.d/rabbitmq.list <<EOF
## Provides modern Erlang/OTP releases
##
deb [signed-by=/usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-erlang/deb/ubuntu jammy main
deb-src [signed-by=/usr/share/keyrings/rabbitmq.E495BB49CC4BBE5B.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-erlang/deb/ubuntu jammy main

## Provides RabbitMQ
##
deb [signed-by=/usr/share/keyrings/rabbitmq.9F4587F226208342.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-server/deb/ubuntu jammy main
deb-src [signed-by=/usr/share/keyrings/rabbitmq.9F4587F226208342.gpg] https://ppa1.novemberain.com/rabbitmq/rabbitmq-server/deb/ubuntu jammy main
EOF

## Update package indices
sudo apt-get update -y
```

3. Install Erlang packages

```sh
sudo apt-get install -y erlang-base \
    erlang-asn1 erlang-crypto erlang-eldap erlang-ftp erlang-inets \
    erlang-mnesia erlang-os-mon erlang-parsetools erlang-public-key \
    erlang-runtime-tools erlang-snmp erlang-ssl \
    erlang-syntax-tools erlang-tftp erlang-tools erlang-xmerl
```

4. Install rabbitmq-server and its dependencies

```sh
sudo apt-get install rabbitmq-server -y --fix-missing
```

### Supervisor

```bash
sudo apt-get install supervisor
```

## First setup steps

### Create new user

```bash
sudo adduser mbin
sudo usermod -aG sudo mbin
sudo usermod -aG www-data mbin
sudo su - mbin
```

### Create folder

```bash
sudo mkdir -p /var/www/mbin
sudo chown mbin:www-data /var/www/mbin
```

### Clone git repository

```bash
cd /var/www/mbin
git clone https://github.com/MbinOrg/mbin.git .
```

### Create & configure media directory

```bash
mkdir public/media
sudo chmod -R 775 public/media
sudo chown -R mbin:www-data public/media
```

### Configure `var` directory

Create & set permissions to the `var` directory (used for cache and log files):

```bash
cd /var/www/mbin
mkdir var

# See also: https://symfony.com/doc/current/setup/file_permissions.html
# if the following commands don't work, try adding `-n` option to `setfacl`
HTTPDUSER=$(ps axo user,comm | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1)

# Set permissions for future files and folders
sudo setfacl -dR -m u:"$HTTPDUSER":rwX -m u:$(whoami):rwX var

# Set permissions on the existing files and folders
sudo setfacl -R -m u:"$HTTPDUSER":rwX -m u:$(whoami):rwX var
```

### Generate Secrets

> [!NOTE]
> This will generate several valid tokens for the Mbin setup, you will need quite a few.

```bash
for counter in {1..2}; do node -e "console.log(require('crypto').randomBytes(16).toString('hex'))"; done
for counter in {1..3}; do node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"; done
```

### The dot env file

Make a copy of the `.env.example` the and edit the `.env` configure file:

```bash
cp .env.example .env
nano .env
```

Make sure you have substituted all the passwords and configured the basic services in `.env` file.

> [!NOTE]
> The snippet below are to variables inside the .env file. Using the keys generated in the section ["Generating Secrets"](#generate-secrets) fill in the values. You should fully review this file to ensure everything is configured correctly.

```sh
APP_SECRET="{!SECRET!!KEY-16_1-!}"
REDIS_PASSWORD="{!SECRET!!KEY!-32_1-!}"
POSTGRES_PASSWORD="{!SECRET!!KEY!-32_2-!}"
RABBITMQ_PASSWORD="{!SECRET!!KEY!-16_2-!}"
MERCURE_JWT_SECRET="{!SECRET!!KEY!-32_3-!}"
```

Other important `.env` configs:

```sh
# Configure your media URL correctly:
KBIN_STORAGE_URL=https://domain.tld/media

# Ubuntu 22.04 installs PostgreSQL v14 by default, Debian 12 PostgreSQL v15 is the default
POSTGRES_VERSION=14

# Configure email, eg. using SMTP
MAILER_DSN=smtp://127.0.0.1 # When you have a local SMTP server listening
# But if already have Postfix configured, just use sendmail:
MAILER_DSN=sendmail://default
# Or Gmail (%40 = @-sign) use:
MAILER_DSN=gmail+smtp://user%40domain.com:pass@smtp.gmail.com
# Or remote SMTP with TLS on port 587:
MAILER_DSN=smtp://username:password@smtpserver.tld:587?encryption=tls&auth_mode=log
# Or remote SMTP with SSL on port 465:
MAILER_DSN=smtp://username:password@smtpserver.tld:465?encryption=ssl&auth_mode=log
```

### OAuth2 keys for API credential grants

1. Create an RSA key pair using OpenSSL:

```bash
mkdir ./config/oauth2/
# If you protect the key with a passphrase, make sure to remember it!
# You will need it later
openssl genrsa -des3 -out ./config/oauth2/private.pem 4096
openssl rsa -in ./config/oauth2/private.pem --outform PEM -pubout -out ./config/oauth2/public.pem
```

2. Generate a random hex string for the OAuth2 encryption key:

```bash
openssl rand -hex 16
```

3. Add the public and private key paths to `.env`:

```ini
OAUTH_PRIVATE_KEY=%kernel.project_dir%/config/oauth2/private.pem
OAUTH_PUBLIC_KEY=%kernel.project_dir%/config/oauth2/public.pem
OAUTH_PASSPHRASE=<Your (optional) passphrase from above here>
OAUTH_ENCRYPTION_KEY=<Hex string generated in previous step>
```

## Prepare the sources

### Composer

Choose either production or development (not both).

#### Composer Production

```bash
composer install --no-dev
composer dump-env prod
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
composer clear-cache
```

#### Composer Development

If you run production already then _skip the steps below_.

> [!CAUTION]
> When running in development mode your instance will make _sensitive information_ available,
> such as database credentials, via the debug toolbar and/or stack traces.
> **DO NOT** expose your development instance to the Internet or you will have a bad time.

```bash
composer install
composer dump-env dev
APP_ENV=dev APP_DEBUG=1 php bin/console cache:clear
composer clear-cache
```

### Building Frontends

Install all NPM dependencies and build the frontend assets

```bash
npm install
npm run build
```

## Service Configuration

> [!NOTE]
> Don't forget to dump composer environment if you make changes to `.env` file
> ```sh
> composer dump-env prod
> ```
> or for development environment
> ```sh
> composer dump-env dev
> ```

### PHP and PHP-FPM

Edit some PHP settings within your `php.ini` file:

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

```ini
; Both max file size and post body size are personal preferences
upload_max_filesize = 8M
post_max_size = 8M
; Remember the memory limit is per child process
memory_limit = 256M
; maximum memory allocated to store the results
realpath_cache_size = 4096K
; save the results for 10 minutes (600 seconds)
realpath_cache_ttl = 600
```

Be sure to restart (or reload) the PHP-FPM service after you applied any changing to the `php.ini` file:

```bash
sudo systemctl restart php8.2-fpm.service
```

Refer to [PHP Performance Adjustments](../99-tuning/php.md) guide for more PHP performance settings.

### Caching (Redis)

> [!TIP]
> Refer to [this page](../03-optional-features/redis_alternative.md) if you want to use a different Redis compatible service, such as KeyDB

Set up Redis for caching backend.

Edit `redis.conf` file:

```bash
sudo nano /etc/redis/redis.conf
```

```conf
# Search on for: requirepass foobared
# Remove the #, change foobared to the new {!SECRET!!KEY!-32_1-!} password, generated earlier
requirepass "{!SECRET!!KEY!-32_1-!}"

# Search for: supervised no
# Change no to systemd, considering Ubuntu/Debian is using systemd
supervised systemd
```

Save and exit the file.

Restart Redis:

```bash
sudo systemctl restart redis.service
```

Within your `.env` file set your Redis password:

```ini
REDIS_PASSWORD={!SECRET!!KEY!-32_1-!}
REDIS_DNS=redis://${REDIS_PASSWORD}@$127.0.0.1:6379

# Or if you want to use socket file:
#REDIS_DNS=redis://${REDIS_PASSWORD}/var/run/redis/redis-server.sock
# Or KeyDB socket file:
#REDIS_DNS=redis://${REDIS_PASSWORD}/var/run/keydb/keydb.sock
```

### Database (PostgreSQL)

Create new `kbin` database user (or `mbin` user if you know what you are doing), using the password, `{!SECRET!!KEY!-32_2-!}`, you generated earlier:

```bash
sudo -u postgres createuser --createdb --createrole --pwprompt kbin
```

Create tables and database structure:

```bash
cd /var/www/mbin
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

> [!TIP]
> You should not run the default PostgreSQL configuration in production,
> Refer to [PostgreSQL tuning](../99-tuning/postgresql.md) for details.

### RabbitMQ

Add a new `kbin` user with the correct permissions to RabbitMQ:

```bash
sudo rabbitmqctl add_user 'kbin' '{!SECRET!!KEY!-16_2-!}'
sudo rabbitmqctl set_permissions -p '/' 'kbin' '.' '.' '.*'
```

Remove the `guest` account:

```bash
sudo rabbitmqctl delete_user 'guest'
```

## Symfony Messenger

Mbin make uses of "Messenger" for background workers to process activities.

### Messenger queue transport

Edit `.env` file, then set Messenger to use RabbitMQ for transport

```bash
cd /var/www/mbin
nano .env
```

```sh
# Use RabbitMQ (recommended for production):
RABBITMQ_PASSWORD="{!SECRET!!KEY!-16_2-!}"
MESSENGER_TRANSPORT_DSN=amqp://kbin:${RABBITMQ_PASSWORD}@127.0.0.1:5672/%2f/messages

# or Redis/KeyDB:
#MESSENGER_TRANSPORT_DSN=redis://${REDIS_PASSWORD}@127.0.0.1:6379/messages
# or PostgreSQL Database (Doctrine):
#MESSENGER_TRANSPORT_DSN=doctrine://default
```

### Setup Supervisor

Supervisor is used to run the Messenger background workers.

Configure the messenger jobs:

```bash
sudo nano /etc/supervisor/conf.d/messenger-worker.conf
```

With the following content:

```ini
[program:messenger]
command=php /var/www/mbin/bin/console messenger:consume scheduler_default old async outbox deliver inbox resolve receive failed --time-limit=3600
user=www-data
numprocs=6
startsecs=0
autostart=true
autorestart=true
startretries=10
process_name=%(program_name)s_%(process_num)02d
```

Note: you can increase the number of running messenger jobs if your queue is building up (i.e. more messages are coming in than your messengers can handle)

Save and close the file. Restart supervisor jobs:

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start all
```

If you wish to restart your supervisor jobs in the future, use:

```bash
sudo supervisorctl restart all
```

## Web server (NGINX)

NGINX is used for serving the site, including static assets and act as PHP application entry point.

[Set up TLS Certificates](../02-configuration/lets_encrypt.md) for use with the web server,
then [Perform General NGINX Configuration](../02-configuration/nginx.md#general-nginx-configs) before proceeding.

Then edit the NGINX site config file, with the following content:

```bash
sudo nano /etc/nginx/sites-available/mbin.conf
```

```nginx
# Redirect HTTP to HTTPS
server {
    server_name domain.tld;
    listen 80;

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name domain.tld;

    root /var/www/mbin/public;

    index index.php;

    charset utf-8;

    # TLS
    ssl_certificate /etc/letsencrypt/live/domain.tld/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/domain.tld/privkey.pem;

    # Don't leak powered-by
    fastcgi_hide_header X-Powered-By;

    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "same-origin" always;
    add_header X-Download-Options "noopen" always;
    add_header X-Permitted-Cross-Domain-Policies "none" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    client_max_body_size 20M; # Max size of a file that a user can upload

    # Logs
    error_log /var/log/nginx/mbin_error.log;
    access_log /var/log/nginx/mbin_access.log;

    location / {
        # try to serve file directly, fallback to app.php
        try_files $uri /index.php$is_args$args;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location /.well-known/mercure {
        proxy_pass http://127.0.0.1:3000$request_uri;
        # Increase this time-out if you want clients have a Mercure connection open for longer (eg. 24h)
        proxy_read_timeout 2h;
        proxy_http_version 1.1;
        proxy_set_header Connection "";

        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location ~ ^/index\.php(/|$) {
        default_type application/x-httpd-php;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;

        # Prevents URIs that include the front controller. This will 404:
        # http://domain.tld/index.php/some-path
        # Remove the internal directive to allow URIs like this
        internal;
    }

    # bypass thumbs cache image files
    location ~ ^/media/cache/resolve {
      expires 1M;
      access_log off;
      add_header Cache-Control "public";
      try_files $uri $uri/ /index.php?$query_string;
    }

    # assets, documents, archives, media
    location ~* \.(?:css(\.map)?|js(\.map)?|jpe?g|png|tgz|gz|rar|bz2|doc|pdf|ptt|tar|gif|ico|cur|heic|webp|tiff?|mp3|m4a|aac|ogg|midi?|wav|mp4|mov|webm|mpe?g|avi|ogv|flv|wmv)$ {
        expires    30d;
        add_header Access-Control-Allow-Origin "*";
        add_header Cache-Control "public, no-transform";
        access_log off;
    }

    # svg, fonts
    location ~* \.(?:svgz?|ttf|ttc|otf|eot|woff2?)$ {
        expires    30d;
        add_header Access-Control-Allow-Origin "*";
        add_header Cache-Control "public, no-transform";
        access_log off;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # return 404 for all other php files not matching the front controller
    # this prevents access to other php files you don't want to be accessible.
    location ~ \.php$ {
        return 404;
    }
}
```

> [!IMPORTANT]
> If you also want to configure your `www.domain.tld` subdomain; Refer to the [related section](../02-configuration/nginx.md#configure-www-subdomain-to-alias-to-main-domain) in the NGINX guide

Enable the NGINX site, using a symlink:

```bash
sudo ln -s /etc/nginx/sites-available/mbin.conf /etc/nginx/sites-enabled/
```

Restart (or reload) NGINX:

```bash
sudo systemctl restart nginx
```

## Optional Features

Here are some additional features you might want to enable, such as:

- [Use S3 for media storage](../03-optional-features/s3_storage.md)
- [Enable Live updates with Mercure](../03-optional-features/mercure.md)

See [here](../03-optional-features/README.md) for the full list of options.

## Mbin First Setup

After starting NGINX, the site should now be accessible at the endpoint of your choosing,  
Continue over at [Mbin First Setup](../04-running-mbin/first_setup.md) to complete setup of your Mbin instance.
