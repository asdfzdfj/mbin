# Administrative Commands

> [!TIP]
> If you are running docker, then you have to be in the `docker` folder and prefix the following commands with
> `docker compose exec php`.

## Manual user activation

Activate a user account (bypassing email verification), please change the `username` below:

```bash
php bin/console mbin:user:verify <username> -a
```

## Retrieve missing/update remote user data

If you want to update all the remote users on your instance, you can execute the following command (which will also re-download the avatars):

```bash
php bin/console mbin:ap:actor:update
```

> [!IMPORTANT]
> This might result in a temporary performance impact if you are running a very large instance, due to the huge amount of remote users.
