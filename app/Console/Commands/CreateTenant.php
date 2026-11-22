<?php

namespace App\Console\Commands;

use App\Models\Organisation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('tenants:create {name}')]
#[Description('Create an organisation and its own database')]
class CreateTenant extends Command
{
    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = is_string($name) ? Str::limit(Str::slug($name), 40, '') : '';

        if ($slug === '') {
            $this->error('The name needs letters or numbers.');

            return self::FAILURE;
        }

        // firstOrCreate + IF NOT EXISTS: safe to run again after a failure.
        $organisation = Organisation::firstOrCreate(['slug' => $slug], [
            'name' => $name,
            'database' => 'homefinder_tenant_'.str_replace('-', '_', $slug),
        ]);

        // MySQL commits any open transaction on CREATE DATABASE,
        // so don't use the central connection.
        DB::connection('tenant')->statement("CREATE DATABASE IF NOT EXISTS `{$organisation->database}`");

        return $this->call('tenants:migrate', ['--tenant' => $slug]);
    }
}
