<?php

namespace App\Providers;

use App\Models\Cohort;
use App\Models\Company;
use App\Models\FamilyGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::morphMap([
            'user' => User::class,
            'family_group' => FamilyGroup::class,
            'cohort' => Cohort::class,
            'company' => Company::class,
        ]);
    }
}
