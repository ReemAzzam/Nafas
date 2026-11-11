<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException("Uniqueness migration does not support the {$driver} database driver.");
        }

        $this->assertNoDuplicates(
            'wellness_plans',
            ['user_id'],
            'active wellness plans',
            fn ($query) => $query->where('is_active', true)
        );
        $this->assertNoDuplicates('activity_completions', ['user_id', 'plan_activity_id'], 'plan completions');
        $this->assertNoDuplicates('activity_completions', ['user_id', 'mood_log_id'], 'mood completions');

        if (! Schema::hasColumn('wellness_plans', 'active_user_id')) {
            Schema::table('wellness_plans', function (Blueprint $table) use ($driver): void {
                $column = $table->unsignedBigInteger('active_user_id');
                $expression = $driver === 'mysql'
                    ? 'IF(`is_active` = 1, `user_id`, NULL)'
                    : 'CASE WHEN is_active = 1 THEN user_id ELSE NULL END';

                if ($driver === 'sqlite') {
                    $column->virtualAs($expression);
                } else {
                    $column->storedAs($expression);
                }
            });
        }

        if (! $this->hasIndex('wellness_plans', 'wellness_plans_active_user_id_unique')) {
            Schema::table('wellness_plans', function (Blueprint $table): void {
                $table->unique('active_user_id', 'wellness_plans_active_user_id_unique');
            });
        }

        if (! $this->hasIndex('activity_completions', 'activity_completions_user_plan_activity_unique')) {
            Schema::table('activity_completions', function (Blueprint $table): void {
                $table->unique(
                    ['user_id', 'plan_activity_id'],
                    'activity_completions_user_plan_activity_unique'
                );
            });
        }

        if (! $this->hasIndex('activity_completions', 'activity_completions_user_mood_log_unique')) {
            Schema::table('activity_completions', function (Blueprint $table): void {
                $table->unique(
                    ['user_id', 'mood_log_id'],
                    'activity_completions_user_mood_log_unique'
                );
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty.
    }

    private function assertNoDuplicates(
        string $table,
        array $columns,
        string $description,
        ?Closure $constraint = null
    ): void {
        $query = DB::table($table);

        if ($constraint !== null) {
            $constraint($query);
        }

        foreach ($columns as $column) {
            $query->whereNotNull($column);
        }

        if ($query->select($columns)->groupBy($columns)->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException("Cannot add unique index: duplicate {$description} exist.");
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $index): bool => $index['name'] === $indexName
        );
    }
};
