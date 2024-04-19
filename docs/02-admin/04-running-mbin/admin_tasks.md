# Mbin Administrative Tasks

> [!TIP]
> If you are running docker, then you have to be in the `docker` folder and prefix the following commands with
> `docker compose exec php`.

## Manual user activation

Activate a user account (bypassing email verification), please change the `username` below:

```bash
php bin/console mbin:user:verify <username> -a
```
