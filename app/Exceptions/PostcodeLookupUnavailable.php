<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The postcode provider could not answer: timeout, 5xx error, network down.
 *
 * This IS our problem (or the provider's), so unlike PostcodeNotFound it is
 * reported. It is "temporary", which tells callers to retry later rather than
 * blame the user.
 */
final class PostcodeLookupUnavailable extends RuntimeException
{
    /**
     * Keeps the original error as $previous, so the log shows the real cause
     * (e.g. "cURL error 28: Operation timed out") under our clearer message.
     */
    public static function because(Throwable $previous): self
    {
        return new self('The postcode lookup service is unavailable.', previous: $previous);
    }

    /**
     * Extra data added to the log entry when this is reported.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['provider_error' => $this->getPrevious()?->getMessage()];
    }

    /**
     * If not caught, answer 503 Service Unavailable (a temporary problem) rather
     * than a generic 500.
     */
    public function render(Request $request): JsonResponse|false
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Postcode lookup is temporarily unavailable. Please try again shortly.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // false: let Laravel render its normal error page for browsers.
        return false;
    }
}
