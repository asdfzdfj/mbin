# Development Server

Requirements:

- PHP v8.2
- NodeJS
- Redis
- PostgreSQL
- Mercure _(Optional)_

---

Initial setup:

- Increase execution time in PHP config file: `/etc/php/8.2/fpm/php.ini`:

```ini
max_execution_time = 120
```

- Restart the PHP-FPM service: `sudo systemctl restart php8.2-fpm.service`
- Connect to PostgreSQL using the postgres user:

```bash
sudo -u postgres psql
```

- Create new mbin database user:

```bash
sudo -u postgres createuser --createdb --createrole --pwprompt mbin
```

- Correctly configured `.env` file (`cp .env.example .env`), these are only the changes you need to pay attention to:

```sh
# Set domain to 127.0.0.1:8000
SERVER_NAME=127.0.0.1:8000
KBIN_DOMAIN=127.0.0.1:8000
KBIN_STORAGE_URL=http://127.0.0.1:8000/media

# Redis (without password)
REDIS_DNS=redis://127.0.0.1:6379

# Set App configs
APP_ENV=dev
APP_SECRET=427f5e2940e5b2472c1b44b2d06e0525

# Configure PostgreSQL
POSTGRES_DB=mbin
POSTGRES_USER=mbin
POSTGRES_PASSWORD=<password>

# Set messenger to Doctrine (= PostgresQL DB)
MESSENGER_TRANSPORT_DSN=doctrine://default
```

- If you are using `127.0.0.1` to connect to the PostgreSQL server, edit the following file: `/etc/postgresql/<VERSION>/main/pg_hba.conf` and add:

```conf
local   mbin            mbin                                    md5
```

- Restart the PostgreSQL server: `sudo systemctl restart postgresql`
- Create database: `php bin/console doctrine:database:create`
- Create tables and database structure: `php bin/console doctrine:migrations:migrate`
- Build frontend assets: `npm install && npm run dev`

Starting the server:

1. Install Symfony CLI

```sh
wget https://get.symfony.com/cli/installer -O symfony-installer.sh
bash symfony-installer.sh
```

2. Check the requirements: `symfony check:requirements`
3. Install dependencies: `composer install`
4. Dump `.env` into `.env.local.php` via: `composer dump-env dev`
5. Increase log level verbosity in `config/packages/monolog.yaml`
   from `level: info` to `level: debug` in the `when@dev.monolog.handlers.*` section. _(Optional)_
6. Clear cache: `APP_ENV=dev APP_DEBUG=1 php bin/console cache:clear -n`
7. Start Mbin: `symfony server:start`
8. Go to: [http://127.0.0.1:8000](http://127.0.0.1:8000/)

This will give you a minimal working frontend with PostgreSQL setup. Keep in mind: this will _not_ start federating, for that you also need to setup Mercure to test the full Mbin setup.

> [!TIP]
> Optionally, you could also setup RabbitMQ, but the Doctrine messenger configuration will be sufficient for local development.

More info:
- [Contributing guide](./README.md)
- [Admin guide](../02-admin/README.md)
- [Symfony Local Web Server](https://symfony.com/doc/current/setup/symfony_server.html)
