# Mbin first setup

> [!TIP]
> If you are running docker, then you have to be in the `docker` folder and prefix the following commands with
> `docker compose exec php`.

## Intitialize instance keys

Run the following command to generate a new key pair, which is requied for proper federation:

```bash
php bin/console mbin:ap:keys:update
```

## Create admin account

Create new admin user (without email verification), please change the `username`, `email` and `password` below:

```bash
php bin/console mbin:user:create <username> <email@example.com> <password>
php bin/console mbin:user:admin <username>
```

## Create "random" magazine

With an admin account, log in and create a magazine named "random" to which unclassified content from the fediverse will flow.

> [!IMPORTANT]
> Creating a `random` magazine is a requirement to getting microblog posts that don't fall under an existing magazine.

## Enable/disable Mercure

If you are not going to use Mercure, you have to disable it in the Admin panel -> Settings -> "Mercure enabled" option.

Make sure you have substituted all the passwords and configured the basic services.

## Setup Complete

That's it! at this point your instance should now be generally usable, and ready to federate with other instances.
