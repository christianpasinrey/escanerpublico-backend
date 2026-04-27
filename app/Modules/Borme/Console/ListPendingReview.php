<?php

namespace Modules\Borme\Console;

use Illuminate\Console\Command;
use Modules\Borme\Models\BormeEntry;

class ListPendingReview extends Command
{
    protected $signature = 'borme:list-pending
        {--limit=50 : Max rows to show}';

    protected $description = 'List BORME entries flagged as pending_review (resolver couldn\'t confidently match an existing company). Shows enough context to decide manually.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $rows = BormeEntry::query()
            ->where('resolution_status', 'pending_review')
            ->with('company:id,name,registry_letter,registry_sheet')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No entries pending review.');

            return self::SUCCESS;
        }

        $this->table(
            ['entry_id', 'date', 'name_raw', 'registry', 'matched_company'],
            $rows->map(fn ($r) => [
                $r->id,
                optional($r->registry_date)->toDateString() ?? '-',
                substr($r->company_name_raw, 0, 50),
                $r->registry_letter && $r->registry_sheet ? "{$r->registry_letter} {$r->registry_sheet}" : '-',
                $r->company ? "#{$r->company->id} {$r->company->name}" : '(none)',
            ])->all()
        );

        $this->line("Total pending: {$rows->count()} (showing up to {$limit}).");

        return self::SUCCESS;
    }
}
