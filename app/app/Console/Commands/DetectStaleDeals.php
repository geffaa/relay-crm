<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Deal;
use App\Services\DealService;
use Illuminate\Console\Command;

class DetectStaleDeals extends Command
{
    protected $signature = 'relay:detect-stale-deals';

    protected $description = 'Create follow-up tasks for deals sitting in a stage past its stale_after_days limit';

    public function handle(DealService $service): int
    {
        $count = 0;

        Company::query()->each(function (Company $company) use ($service, &$count) {
            Deal::forCompany($company)
                ->stale()
                ->with(['stage', 'owner'])
                ->get()
                ->each(function (Deal $deal) use ($service, &$count) {
                    $service->createStaleTask($deal);
                    $count++;
                });
        });

        $this->info("Checked pipelines; {$count} stale deal task(s) ensured.");

        return self::SUCCESS;
    }
}
