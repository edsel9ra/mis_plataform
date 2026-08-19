<?php

namespace App\Providers;

use App\Models\Cohort;
use App\Models\Company;
use App\Models\FamilyGroup;
use App\Models\MentorshipRelationship;
use App\Models\Session;
use App\Models\User;
use App\Policies\CohortPolicy;
use App\Policies\RelationshipPolicy;
use App\Policies\SessionPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
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

        Gate::policy(Cohort::class, CohortPolicy::class);
        Gate::policy(MentorshipRelationship::class, RelationshipPolicy::class);
        Gate::policy(Session::class, SessionPolicy::class);
    }
}
