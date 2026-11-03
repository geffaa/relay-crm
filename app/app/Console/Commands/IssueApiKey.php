<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Company;
use Illuminate\Console\Command;

class IssueApiKey extends Command
{
    protected $signature = 'relay:api-key {company : company slug} {name : label, e.g. "Simpul"}';

    protected $description = 'Create an API key for the public integration API (the token is shown once)';

    public function handle(): int
    {
        $company = Company::where('slug', $this->argument('company'))->first();
        if (! $company) {
            $this->error('Company not found.');

            return self::FAILURE;
        }
        [, $token] = ApiKey::issue($company, $this->argument('name'));
        $this->line($token);

        return self::SUCCESS;
    }
}
