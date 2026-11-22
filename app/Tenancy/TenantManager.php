<?php

namespace App\Tenancy;

use App\Models\Organisation;
use Closure;
use Illuminate\Support\Facades\DB;

final class TenantManager
{
    private ?Organisation $current = null;

    public function current(): ?Organisation
    {
        return $this->current;
    }

    public function switchTo(?Organisation $organisation): void
    {
        $this->current = $organisation;

        config(['database.connections.tenant.database' => $organisation?->database]);

        // Close the open connection, or queries would keep using the old database.
        DB::purge('tenant');
    }

    /**
     * Run the callback as this organisation, then go back to the previous one.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Organisation $organisation, Closure $callback): mixed
    {
        $previous = $this->current;
        $this->switchTo($organisation);

        try {
            return $callback();
        } finally {
            $this->switchTo($previous); // runs even after an exception ; "finally closes the locker even when the clerk faints."
        }
    }
}
