# RabbitMQ Monitoring

## RabbitMQ management plugin

Enable the `rabbitmq_management` plugin by executing:

```sh
sudo rabbitmq-plugins enable rabbitmq_management
```

Create a new admin user in RabbitMQ, replace `<user>` and `<password>` with a username & password you like to use:

```sh
sudo rabbitmqctl add_user <user> <password>
```

Give this new user administrator permissions, again don't forget to change `<user>` to your username in the lines below
(`-p /` is the virtual host path of RabbitMQ, which is `/` by default):

```sh
sudo rabbitmqctl set_user_tags <user> administrator
sudo rabbitmqctl set_permissions -p / <user> ".*" ".*" ".*"
```

Now you can open the RabbitMQ management page: (insecure connection!) `http://<server-ip>:15672` with the username and the password provided earlier.

## RabbitMQ Prometheus exporter

If you are running the prometheus exporter plugin you do not have queue specific metrics by default.
There is another endpoint with the default config that you can scrape, that will return queue metrics for our default virtual host `/`: `/metrics/detailed?vhost=%2F&family=queue_metrics`

Example scrape config:

```yaml
scrape_configs:
  - job_name: "mbin-rabbit_queues"
    static_configs:
      - targets: ["example.org"]
    metrics_path: "/metrics/detailed"
    params:
      vhost: ["/"]
      family:
        [
          "queue_coarse_metrics",
          "queue_consumer_count",
          "channel_queue_metrics",
        ]
```

## Read more on RabbitMQ docs

- [Management plugin](https://www.rabbitmq.com/management.html).
- [Prometheus monitoring](https://rabbitmq.com/prometheus.html)
