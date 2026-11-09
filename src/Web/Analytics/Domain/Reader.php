<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * Who visited, as far as the blog knows it: a reader of the day, told apart
 * from the others without being known, and their device and country.
 *
 * The reader of the day is a keyed hash of their address and browser, keyed
 * with the salt of the day. Only the hash is kept, never the address, and
 * once the salt is gone no one can tell whose it was, nor match it with the
 * reader of another day. An IPv6 address counts by its /64 network, which
 * one device keeps while it changes the rest.
 */
final readonly class Reader
{
    /**
     * How an IPv4 address begins when written as IPv6: ::ffff:a.b.c.d.
     */
    private const string IPV4_AS_IPV6 = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    private const int IPV6_NETWORK_BYTES = 8;

    private function __construct(
        private string $hash,
        private Device $device,
        private ?string $country,
    ) {}

    public static function from(string $salt, string $address, UserAgent $userAgent, Country $country): self
    {
        return new self(
            hash_hmac('sha256', self::network($address)."\n".$userAgent->value(), $salt),
            $userAgent->device(),
            $country->code(),
        );
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function device(): Device
    {
        return $this->device;
    }

    /**
     * The ISO 3166 code of their country, or null when unknown.
     */
    public function country(): ?string
    {
        return $this->country;
    }

    /**
     * What of an address tells one reader from another: an IPv4 address, or
     * the /64 network of an IPv6 one, both as bytes. An IPv4 address written
     * as IPv6 is that IPv4 address. Anything else is taken as it comes.
     */
    private static function network(string $address): string
    {
        if (false === filter_var($address, \FILTER_VALIDATE_IP)) {
            return $address;
        }

        $packed = (string) inet_pton($address);

        if (str_starts_with($packed, self::IPV4_AS_IPV6)) {
            return substr($packed, \strlen(self::IPV4_AS_IPV6));
        }

        return substr($packed, 0, self::IPV6_NETWORK_BYTES);
    }
}
