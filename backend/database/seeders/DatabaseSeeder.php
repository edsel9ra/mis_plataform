<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            PlanSeeder::class,
            SkillTagSeeder::class,
            EvaluationCatalogSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call([
                DemoLearningPathSeeder::class,
                DemoMentorshipSeeder::class,
            ]);
        }
    }
}
