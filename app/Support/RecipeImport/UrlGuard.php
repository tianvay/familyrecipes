<?php

namespace App\Support\RecipeImport;

use Closure;
use InvalidArgumentException;

/**
 * Lässt nur öffentliche http(s)-Adressen zu. Verhindert, dass über den Import
 * Geräte im Heimnetz (FRITZ!Box, Datenbank-Server …) abgefragt werden.
 */
class UrlGuard
{
    /**
     * @param  Closure(string): list<string>  $resolver  Hostname → IP-Adressen
     */
    public function __construct(private ?Closure $resolver = null)
    {
        $this->resolver ??= fn (string $host): array => gethostbynamel($host) ?: [];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertPublic(string $url): void
    {
        $parts = parse_url($url);

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            throw new InvalidArgumentException('Nur http- und https-Adressen sind erlaubt.');
        }

        $host = trim($parts['host'], '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver)($host);

        if ($addresses === []) {
            throw new InvalidArgumentException('Die Adresse konnte nicht gefunden werden.');
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Adressen im Heimnetz können nicht importiert werden.');
            }
        }
    }
}
