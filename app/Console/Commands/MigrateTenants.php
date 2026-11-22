<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use App\Tenancy\TenantManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('tenants:migrate {--tenant= : Only this organisation slug}')]
#[Description('Run the tenant migrations in every tenant database')]
class MigrateTenants extends Command
{
    public function handle(TenantManager $tenants): int
    {
        $failed = false;

        $organisations = Organisation::query()
            ->when($this->option('tenant'), fn ($query, $slug) => $query->where('slug', $slug))
            ->lazyById(100);

        foreach ($organisations as $organisation) {
            try {
                $tenants->run($organisation, fn () => $this->callSilently('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]));
                $organisation->update(['status' => 'active']);
                $this->info("OK      {$organisation->slug}");
            } catch (Throwable $e) { // one broken tenant must not stop the others
                report($e);
                $organisation->update(['status' => 'failed']);
                $failed = true;
                $this->error("FAILED  {$organisation->slug}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
