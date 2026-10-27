<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AppServiceProvider now calls Relation::enforceMorphMap(), so *_type columns
 * store short aliases ("user") instead of class names ("App\Models\User").
 *
 * Rows written BEFORE that still hold class names. Laravel would now look for
 * notifiable_type = 'user' and miss them: old notifications vanish and every
 * existing API token gets a 401. This data migration rewrites them once.
 */
return new class extends Migration
{
    /**
     * table => [type column, [class name => alias]]
     *
     * @var array<string, array{0: string, 1: array<string, string>}>
     */
    private array $columns = [
        'notifications' => ['notifiable_type', [
            'App\Models\User' => 'user',
            'App\Models\Agency' => 'agency',
        ]],
        'personal_access_tokens' => ['tokenable_type', [
            'App\Models\User' => 'user',
        ]],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => [$column, $aliases]) {
            foreach ($aliases as $class => $alias) {
                // One UPDATE per class: fast, even on a big table.
                DB::table($table)->where($column, $class)->update([$column => $alias]);
            }
        }
    }

    /**
     * The exact reverse, so a rollback leaves the data as it was.
     */
    public function down(): void
    {
        foreach ($this->columns as $table => [$column, $aliases]) {
            foreach ($aliases as $class => $alias) {
                DB::table($table)->where($column, $alias)->update([$column => $class]);
            }
        }
    }
};
