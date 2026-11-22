<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        // The slug comes from the browser, so it is never trusted on its own:
        // the membership check is part of the query.
        $organisation = Organisation::query()
            ->where('slug', $request->route('organisation'))
            ->where('status', 'active')
            ->whereHas('users', fn ($query) => $query->whereKey($request->user()?->getAuthIdentifier()))
            ->first();

        // 404, not 403: do not reveal which organisations exist.
        abort_if($organisation === null, 404);

        return $this->tenants->run($organisation, fn (): Response => $next($request));
    }
}
