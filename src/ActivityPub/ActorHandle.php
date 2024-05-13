<?php

declare(strict_types=1);

namespace App\ActivityPub;

class ActorHandle
{
    public const HANDLE_PATTERN = '/^(?P<prefix>[@!])?(?P<name>[\w\-\.]+)(?:@(?P<host>[\w\.\-]+)(?P<port>:\d+)?)?$/';

    public function __construct(
        public ?string $prefix = null,
        public ?string $name = null,
        public ?string $host = null,
        public ?int $port = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->formatWithPrefix($this->prefix);
    }

    public static function parse(string $handle): ?static
    {
        if (preg_match(static::HANDLE_PATTERN, $handle, $match)) {
            $new = new static(
                $match['prefix'] ?? null,
                $match['name'],
                $match['host'] ?? null
            );
            $new->setPort($match['port'] ?? null);

            return $new->isValid() ? $new : null;
        }

        return null;
    }

    public function isValid(): bool
    {
        if (null !== $this->port) {
            return !empty($this->name) && !empty($this->host);
        } elseif ($this->host) {
            return !empty($this->name);
        } elseif (!$this->host) {
            return !empty($this->name) && !empty($this->prefix);
        } else {
            return false;
        }
    }

    /** @return string port as string in the format ':9000' or empty string if it's null */
    public function getPortString(): string
    {
        return !empty($this->port) ? ':'.$this->port : '';
    }

    /** @param int|string|null $port port as either plain int or string formatted like ':9000' */
    public function setPort(int|string|null $port)
    {
        $this->port = \is_string($port)
            ? \intval(ltrim($port, ':'))
            : $port;

        return $this;
    }

    /** @return string the handle's domain and optionally port in the format `host[:port]` */
    public function getDomain(): string
    {
        return $this->host.$this->getPortString();
    }

    /** @param ?string $domain the domain in the format `host[:port]` to set both handle's host and port */
    public function setDomain(?string $domain)
    {
        $url = parse_url($domain ?? '');

        if (!empty($url['port'])) {
            $this->host = $url['host'] ?? null;
            $this->port = $url['port'] ?? null;
        } else {
            $this->host = $domain;
            $this->port = null;
        }

        return $this;
    }

    public function formatWithPrefix(?string $prefix): string
    {
        $user = "{$prefix}{$this->name}";
        $remote = $this->host ? "@{$this->getDomain()}" : '';

        return $user.$remote;
    }

    /** @return string handle in the form `name@domain` */
    public function plainHandle(): string
    {
        return $this->formatWithPrefix('');
    }

    /** @return string handle in the form `@name@domain` */
    public function atHandle(): string
    {
        return $this->formatWithPrefix('@');
    }

    /** @return string handle in the form `!name@domain` */
    public function bangHandle(): string
    {
        return $this->formatWithPrefix('!');
    }
}
