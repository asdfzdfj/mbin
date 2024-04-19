# PHP Performance Adjustments

## Enable PHP OPCache

Enable OPCache for improved performances with PHP

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

```ini
opcache.enable=1
opcache.enable_cli=1
; Memory consumption (in MBs), personal preference
opcache.memory_consumption=512
; Internal string buffer (in MBs), personal preference
opcache.interned_strings_buffer=128
opcache.max_accelerated_files=100000
; Enable PHP JIT
opcache.jit_buffer_size=500M
```

## Increase PHP-FPM child process

Edit your PHP-FPM `www.conf` file to increase the amount of PHP child processes

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

With the content (these are personal preferences, adjust to your needs):

```ini
pm = dynamic
pm.max_children = 60
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 10
```

Be sure to restart (or reload) the PHP-FPM service after you applied any changes to the `php.ini` file or the `www.conf` file:

```bash
sudo systemctl restart php8.2-fpm.service
```

## External References

- [Symfony Performance docs](https://symfony.com/doc/current/performance.html)
