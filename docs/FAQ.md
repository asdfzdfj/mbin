# FAQ

See below our Frequently Asked Questions (FAQ). The questions (and corresponding answers) below are in random order.

## General Overview

### What is Mbin?

Mbin is an _open-source federated link aggregation, content rating and discussion_ software that is built on top of _ActivityPub_.

### What is ActivityPub (AP)?

ActivityPub is a open standard protocol that empowers the creation of decentralized social networks, allowing different servers to interact and share content while giving users control over their data.  
It fosters a more user-centric and distributed approach to social networking, promoting interoperability across platforms and safeguarding user privacy and choice.

This protocol is vital for building a more open, inclusive, and user-empowered digital social landscape.

### Where can I find more info about AP?

There exists an official [ActivityPub specification](https://www.w3.org/TR/activitypub/), as well as [several AP extensions](https://codeberg.org/fediverse/fep/) on this specification.

There is also a **very good** [forum post on activitypub.rocks](https://socialhub.activitypub.rocks/t/guide-for-new-activitypub-implementers/479), containing a lot of links and resources to various documentation and information pages.

### How to setup my own Mbin instance?

Have a look at our [installation guides](./02-admin/README.md). A bare metal/VM setup is **recommended** at this time, however we do provide a Docker setup as well.

### Should I run development mode?

**NO!** Try to avoid running development mode when you are hosting your own _public_ instance. Running in development mode can cause sensitive data to be leaked, such as secret keys or passwords (e.g. via development console). Development mode will also log a lot of messages to disk (incl. stacktraces) which may have negative performance impact.

That said, if you are _experiencing serious issues_ with your instance which you cannot resolve by looking at the log file (`prod-{YYYY-MM-DD}.log`) or server logs, you can try running in development mode to debug the problem or issue you are having. Enabling development mode **during development** is also very useful.

### I have an issue!

See the [Common Problems](#common-problems-and-troubleshooting) section below for a known list of issues and solutions.

You can [join our Matrix community](https://matrix.to/#/#mbin:melroy.org) and ask for help, and/or make an [issue ticket](https://github.com/MbinOrg/mbin/issues) in GitHub if that adds value (always check for duplicates).

See also our [contributing page](https://github.com/MbinOrg/mbin/blob/main/CONTRIBUTING.md).

### How can I contribute?

New contributors are _always welcome_ to join us. The most valuable contributions come from helping with bug fixes and features through Pull Requests,
As well as helping out with [translations](https://hosted.weblate.org/engage/mbin/) and documentation.

Read more on our [contributing page](https://github.com/MbinOrg/mbin/blob/main/CONTRIBUTING.md).

Do _not_ forget to [join our Matrix community](https://matrix.to/#/#mbin:melroy.org).

## Involved Softwares

### What is Matrix?

Matrix is an open-standard, decentralized, and federated communication protocol. You can the [download clients for various platforms here](https://matrix.org/ecosystem/clients/).

As a part of our software development and discussions, Matrix is our primary platform.

### What is Mercure?

Mercure is a _real-time communication protocol_ and server that facilitates _server-sent events_ for web applications. It enables _real-time updates_ by allowing clients to subscribe and receiving updates pushed by the server.

Mbin optionally uses Mercure, on very large instances you might want to consider disabling Mercure whenever it _degrades_ your server performance.

### What is Redis?

Redis is an _in-memory data store_, which can help for caching purposes or other requirements. We **recommend** to setup Redis when running Mbin, but Redis is optional.

### What is RabbitMQ?

RabbitMQ is an open-source _message broker_ software that facilitates the exchange of messages between different subsystems, using queues to store and manage messages.

Mbin uses RabbitMQ as the message queue transport for [Symfony Messenger](https://symfony.com/doc/current/messenger.html) to perform various tasks, including exchange ActivityPub messages with other services.

We highly **recommend** to setup RabbitMQ on your Mbin instance. Failed messages are no longer stored in RabbitMQ, but in PostgreSQL instead (table: `public.messenger_messages`).

## Service Monitoring

### How do I know Redis is working?

Execute: `sudo redis-cli ping` expect a PONG back. If it requires authentication, add the following flags: `--askpass` to the `redis-cli` command.

Ensure you do not see any connection errors in your `var/log/prod.log` file.

### How do I know Mercure is working?

[Ensure that Mercure is enabled in the admin setting](./02-admin/03-optional-features/mercure.md#enable-mercure).

When you visit your own Mbin instance domain, you can validate whether a connection was successfully established between your browser (client) and Mercure (server), by going to the browser developer toolbar and visit the "Network" tab.

The browser should successfully connect to the `https://<yourdomain>/.well-known/mercure` URL (thus without any errors). Since it's streaming data, don't expect any response from Mercure.

### How do I know RabbitMQ is working?

Execute: `sudo rabbitmqctl status`, that should provide details about your RabbitMQ instance. The output should also contain information about which plugins are installed, various usages and on which ports it is listening on (eg. `5672` for AMQP protocol).

Ensure you do not see any connection errors in your `var/log/prod-{YYYY-MM-DD}.log` file.

It's also recommended to [enable RabbitMQ management plugin](./02-admin/06-recommendations/rabbitmq_monitor.md#rabbitmq-management-plugin).

See screenshot below of RabbitMQ management interface for a typical load of small Mbin instance.
Note that having 4k or even 10k for "Queued message" is normal after recent Mbin changes, see [this section](#messenger-queue-is-building-up-even-though-my-messengers-are-idling) and below for details:

![Typical load on very small instances](images/rabbit_small_load_typical.png)

<!-- contemplating moving this into a separate page if it gets long enough -->
## Common Problems and Troubleshooting

### Where can I find my log files?

You can find the Mbin logging in the `var/log/` directory from the root folder of the Mbin installation. When running production the file is called `prod-{YYYY-MM-DD}.log`, when running development the log file is called `dev-{YYYY-MM-DD}.log`.

See the following pages for more details:
- [Bare metal logging](./02-admin/05-troubleshooting/bare_metal.md)
- [Docker logging](./02-admin/05-troubleshooting/docker.md)

### I changed my .env configuration but the error still appears/new config doesn't seem to be applied?

After you edited your `.env` configuration file on a bare metal/VM setup, you always need to execute the `composer dump-env` command (in Docker you just restart the containers).

Running the `post-upgrade` script will also execute `composer dump-env` for you:

```bash
./bin/post-upgrade
```

> [!IMPORTANT]
> If you want to switch between `prod` to `dev` (or vice versa), you need explicitly execute: `composer dump-env dev` or `composer dump-env prod` respectively.

Followed by restarting the services that are depending on the (new) configuration:

```bash
# Clear PHP Opcache by restarting the PHP FPM service
sudo systemctl restart php8.2-fpm.service

# Restarting the PHP messenger jobs and Mercure service (also reread the latest configuration)
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl restart all
```

### Running `php bin/console mbin:ap:keys:update` does not appear to set keys

If you're seeing this error in logs:

> ```text
> getInstancePrivateKey(): Return value must be of type string, null returned
> ```

At time of writing, `getInstancePrivateKey()` [calls out to the Redis cache](https://github.com/MbinOrg/mbin/blob/main/src/Service/ActivityPub/ApHttpClient.php#L348)
first, so any updates to the keys requires a `DEL instance_private_key instance_public_key` (or `FLUSHDB` to be certain, as documented here: [bare metal](02-admin/04-running-mbin/upgrades.md#clear-cache) and [docker](02-admin/04-running-mbin/upgrades.md#clear-cache-1))

### How to retrieve missing/update remote user data?

[See here](./02-admin/04-running-mbin/admin_tasks.md#retrieve-missingupdate-remote-user-data).

### Messenger Queue is building up even though my messengers are idling

We recently changed the messenger config to retry failed messages 3 times, instead of sending them straight to the `failed` queue.
RabbitMQ will now have new queues being added for the different delays (so a message does not get retried 5 times per second):

![Queue overview](images/rabbit_queue_tab_cut.png)

The global overview from RabbitMQ shows the ready messages for all queues combined. Messages in the retry queues count as ready messages the whole time they are in there,
so for a correct ready count you have to go to the queue specific overview.

**Overview**  
![Queued messages](images/rabbit_queue_overview.png) 

**Queue Tab**  
![Queue overview](images/rabbit_queue_tab.png)

**"Message" Queue Overview**  
![Message Queue Overview](images/rabbit_messages_overview.png) 

### How to clean-up all failed messages?

If you wish to **delete all messages** (`dead` and `failed`) at once, execute the following PostgreSQL query (assuming you're connected to the correct PostgreSQL database):

```sql
DELETE FROM messenger_messages;
```

If you want to delete only the messages that are no longer being worked (`dead`) on you can execute this query:

```sql
DELETE FROM messenger_messages WHERE queue_name = 'dead';
```

### RabbitMQ shows a really high publishing rate

First thing you should do to debug the issue is looking at the "Queues and Streams" tab to find out what queues have the high publishing rate.
If the queue/s in question are `inbox` and `resolve` it is most likely a circulating `ChainActivityMessage`.
To verify that assumption:
1. stop all messengers
    - if you're on bare metal, as root: `supervisorctl stop messenger:*`
    - if you're on docker, inside the `docker` folder : `docker compose down messenger*`
2. look again at the publishing rate. If it has gone down, then it definitely is a circulating message

To fix the problem:
1. start the messengers if they are not already started
2. go to the `resolve` queue
3. open the "Get Message" panel
4. change the `Ack Mode` to `Automatic Ack`
5. As long as your publishing rate is still high, press the `Get Message` button. It might take a few tries before you got all of them and you might get a "Queue is empty" message a few times

#### Discarding queued messages

If you believe you have a queued message that is infinitely looping / stuck, you can discard it by setting the `Get messages` `Ack mode` in RabbitMQ to `Reject requeue false` with a `Messages` setting of `1` and clicking `Get message(s)`.

> [!WARNING]
> This will permanently discard the payload

![Rabbit discard payload](images/rabbit_reject_requeue_false.png)

## Performance hints

- [Resolve cache images in background](https://symfony.com/bundles/LiipImagineBundle/current/optimizations/resolve-cache-images-in-background.html#symfony-messenger)

## References

- [https://symfony.com/doc/current/setup.html](https://symfony.com/doc/current/setup.html)
- [https://symfony.com/doc/current/deployment.html](https://symfony.com/doc/current/deployment.html)
- [https://symfony.com/doc/current/setup/web_server_configuration.html](https://symfony.com/doc/current/setup/web_server_configuration.html)
- [https://symfony.com/doc/current/messenger.html#deploying-to-production](https://symfony.com/doc/current/messenger.html#deploying-to-production)
- [https://codingstories.net/how-to/how-to-install-and-use-mercure/](https://codingstories.net/how-to/how-to-install-and-use-mercure/)
