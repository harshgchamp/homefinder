<?php

namespace App\Services\Postcodes;

use App\Contracts\PostcodeLookup;
use App\Data\PostcodeLocation;
use App\Exceptions\PostcodeLookupUnavailable;
use App\Exceptions\PostcodeNotFound;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * The real provider: https://postcodes.io (free, UK only, no API key).
 *
 * Its constructor takes plain values (a URL and a number). The container cannot
 * guess those from type hints, so AppServiceProvider tells it where to get them
 * with a contextual binding: ->needs('$baseUrl')->giveConfig('services.postcodes.base_url').
 */
final class PostcodesIoLookup implements PostcodeLookup
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function lookup(string $postcode): PostcodeLocation
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                // Never let a slow third party hang our request for long.
                ->timeout($this->timeout)
                // retry(3, ...) = at most 3 ATTEMPTS in total (the first try + 2
                // retries), 200ms apart, and only for connection problems and
                // server errors. A 404 is a real answer, so it is not retried.
                ->retry(3, 200, fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()), throw: false)
                ->get('postcodes/'.rawurlencode($postcode));
        } catch (ConnectionException $e) {
            // Translate the HTTP client's exception into OUR exception, so callers
            // depend on the contract, not on how this provider happens to fail.
            throw PostcodeLookupUnavailable::because($e);
        }

        if ($response->notFound()) {
            throw PostcodeNotFound::for($postcode);
        }

        if ($response->failed()) {
            throw PostcodeLookupUnavailable::because(
                new \RuntimeException("postcodes.io answered HTTP {$response->status()}"),
            );
        }

        /** @var array{postcode: string, latitude: float|null, longitude: float|null, admin_district?: string|null} $result */
        $result = $response->json('result');

        // A few real postcodes (e.g. some PO boxes) have no coordinates.
        if ($result['latitude'] === null || $result['longitude'] === null) {
            throw PostcodeNotFound::for($postcode);
        }

        return new PostcodeLocation(
            postcode: $result['postcode'],
            latitude: (float) $result['latitude'],
            longitude: (float) $result['longitude'],
            district: $result['admin_district'] ?? null,
        );
    }
}
