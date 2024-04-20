# Redis Compatible Alternatives

## A word of caution

Unless you know what you're doing, Do **NOT** run both Redis and your chosen alternative at the same time,
just pick one. After all these service would likely run on the same default port 6379 (IANA #815344).

Be sure to disable redis first:

```bash
sudo systemctl stop redis
sudo systemctl disable redis
```

Or even remove Redis altogether: `sudo apt purge redis-server`

## KeyDB

[KeyDB](https://github.com/Snapchat/KeyDB) is a fork of Redis. If you wish to use KeyDB instead, that is possible.

For Debian/Ubuntu you can install KeyDB package repository via:

```bash
echo "deb https://download.keydb.dev/open-source-dist $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/keydb.list
sudo wget -O /etc/apt/trusted.gpg.d/keydb.gpg https://download.keydb.dev/open-source-dist/keyring.gpg
sudo apt update
sudo apt install keydb
```

During the install you can choose between different installation methods, I advice to pick: "keydb", which comes with systemd files as well as the CLI tools (eg. `keydb-cli`).

Configuration file is located at: `/etc/keydb/keydb.conf`. See also [config documentation](https://docs.keydb.dev/docs/config-file) for more information.  

To set a password with KeyDB, add the following option to the bottom of the file:

```conf
# Replace {!SECRET!!KEY!-32_1-!} with the password generated earlier
requirepass "{!SECRET!!KEY!-32_1-!}"
```

you can also configure Unix socket files if you wish:

```conf
unixsocket /var/run/keydb/keydb.sock
unixsocketperm 777
```

Start & enable the service if it isn't already:

```bash
sudo systemctl start keydb-server
sudo systemctl enable keydb-server
```
