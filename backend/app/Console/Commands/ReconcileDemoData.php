<?php

namespace App\Console\Commands;

use App\Services\DemoDataReconciliationService;
use Illuminate\Console\Command;

class ReconcileDemoData extends Command
{
    protected $signature = 'nafas:reconcile-demo {--apply : Apply the reconciliation changes}';

    protected $description = 'Reconcile legacy Nafas demo records with the current demo catalog';

    public function handle(DemoDataReconciliationService $reconciliation): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is allowed only in local or testing environments.');

            return self::FAILURE;
        }

        try {
            $report = $reconciliation->reconcile((bool) $this->option('apply'));
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($this->option('apply') ? 'Reconciliation plan (apply mode)' : 'Reconciliation plan (dry-run)');
        $this->table(
            ['Table', 'Rows to update', 'Rows to add'],
            collect($report['counts'])
                ->sortKeys()
                ->map(fn (array $counts, string $table) => [
                    $table,
                    $counts['updated'] ?? 0,
                    $counts['added'] ?? 0,
                ])
                ->values()
                ->all()
        );
        $this->line('Proposed deletions: 0 (no data will be deleted).');

        if ($report['ambiguous'] !== []) {
            $this->warn('Ambiguous or out-of-scope records left untouched: '.count($report['ambiguous']));
            $this->table(
                ['Record', 'ID', 'Email', 'Reason'],
                array_map(
                    fn (array $row) => [
                        $row['record'],
                        $row['id'],
                        $row['email'] ?? '',
                        $row['reason'],
                    ],
                    $report['ambiguous']
                )
            );
        } else {
            $this->info('Ambiguous records: 0');
        }

        if ($this->option('apply')) {
            $this->info('Reconciliation applied successfully.');
        } else {
            $this->comment('Dry-run only. No database changes were made. Use --apply to execute this plan.');
        }

        return self::SUCCESS;
    }
}
