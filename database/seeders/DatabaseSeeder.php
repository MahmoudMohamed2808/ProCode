<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $projects = Project::factory()
            ->count(3)
            ->for($user)
            ->create();

        $projects->each(function (Project $project, int $index) {
            Task::factory()
                ->count(5)
                ->state([
                    'status' => match ($index) {
                        0 => TaskStatus::Todo,
                        1 => TaskStatus::InProgress,
                        default => TaskStatus::Done,
                    },
                ])
                ->for($project)
                ->create();

            Task::factory()
                ->count(2)
                ->state(['priority' => TaskPriority::High])
                ->for($project)
                ->create();
        });
    }
}
