<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The postcode is well-formed but does not exist, e.g. "ZZ9 9ZZ".
 *
 * This is the USER's mistake, not a bug, so:
 *   - ShouldntReport: never written to the error log / error tracker
 *     (thousands of typos would bury real problems)
 *   - render(): if it is ever thrown and not caught during a request, it
 *     becomes a normal validation error instead of a 500 error page
 */
final class PostcodeNotFound extends RuntimeException implements ShouldntReport
{
    /**
     * A "named constructor": reads well at the throw site and builds the
     * message in one place.  throw PostcodeNotFound::for('ZZ9 9ZZ');
     */
    public static function for(string $postcode): self
    {
        $exception = new self("Postcode {$postcode} was not found.");
        $exception->postcode = $postcode;

        return $exception;
    }

    public string $postcode = '';

    /**
     * Laravel calls render() when this exception reaches the exception handler.
     * JSON clients (the API) get a 422 like any validation error; browsers go
     * back to the form with the message under the postcode field.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        $message = "We couldn't find the postcode {$this->postcode}. Please check it.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'errors' => ['postcode' => [$message]],
            ], 422);
        }

        return back()->withInput()->withErrors(['postcode' => $message]);
    }
}
