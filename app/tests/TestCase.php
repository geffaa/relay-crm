<?php

namespace Tests;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    protected Company $company;

    protected User $admin;

    protected User $manager;

    protected User $rep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // Company context for the request lifecycle under test.
        $this->company = Company::create(['name' => 'Test Co', 'slug' => 'test-co-'.uniqid()]);
        app()->instance('relay.company', $this->company);

        $this->admin = $this->makeUser('Admin One', 'admin');
        $this->manager = $this->makeUser('Manager One', 'manager');
        $this->rep = $this->makeUser('Rep One', 'rep');
    }

    protected function makeUser(string $name, string $role): User
    {
        $user = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@test.dev'.uniqid(),
            'password' => Hash::make('password'),
            'current_company_id' => $this->company->id,
        ]);
        $user->companies()->attach($this->company->id, ['role' => $role]);

        return $user;
    }

    /**
     * A second, unrelated company used for tenant isolation assertions.
     */
    protected ?Company $otherCompany = null;

    protected function otherCompany(): Company
    {
        return $this->otherCompany ??= Company::create(['name' => 'Other Co', 'slug' => 'other-co-'.uniqid()]);
    }
}
