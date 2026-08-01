<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('tasks.index'))->assertRedirect(route('login'));
});

test('authenticated users can visit the tasks page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tasks.index'))
        ->assertOk()
        ->assertSee('Tasks');
});

test('users only see their own tasks', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userProject = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($otherUser)->create();

    $userTask = Task::factory()->for($userProject)->create(['title' => 'My private task']);
    $otherTask = Task::factory()->for($otherProject)->create(['title' => 'Someone elses task']);

    $this->actingAs($user)
        ->get(route('tasks.index'))
        ->assertOk()
        ->assertSee('My private task')
        ->assertDontSee('Someone elses task');

    expect(Task::query()
        ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
        ->count())->toBe(1);

    expect($otherTask->exists)->toBeTrue();
});

test('users can create a task', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->call('openCreateTaskModal')
        ->set('taskTitle', 'Ship the feature')
        ->set('taskProjectId', $project->id)
        ->set('taskStatus', TaskStatus::Todo->value)
        ->set('taskPriority', TaskPriority::High->value)
        ->call('storeTask')
        ->assertHasNoErrors()
        ->assertSet('showTaskModal', false);

    $this->assertDatabaseHas('tasks', [
        'title' => 'Ship the feature',
        'project_id' => $project->id,
        'status' => TaskStatus::Todo->value,
        'priority' => TaskPriority::High->value,
    ]);
});

test('task creation requires a title and project', function () {
    $user = User::factory()->create();
    Project::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->call('openCreateTaskModal')
        ->set('taskTitle', '')
        ->set('taskProjectId', null)
        ->call('storeTask')
        ->assertHasErrors(['taskTitle' => 'required', 'taskProjectId' => 'required']);
});

test('users can update a task', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($project)->create(['title' => 'Old title']);

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->call('openEditTaskModal', $task->id)
        ->set('taskTitle', 'New title')
        ->set('taskStatus', TaskStatus::Done->value)
        ->call('updateTask')
        ->assertHasNoErrors();

    $task->refresh();

    expect($task->title)->toBe('New title');
    expect($task->status)->toBe(TaskStatus::Done);
    expect($task->isCompleted())->toBeTrue();
});

test('users can toggle task completion', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($project)->create();

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->call('toggleTask', $task->id);

    $task->refresh();

    expect($task->isCompleted())->toBeTrue();
    expect($task->completed_at)->not->toBeNull();

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->call('toggleTask', $task->id);

    $task->refresh();

    expect($task->isCompleted())->toBeFalse();
    expect($task->completed_at)->toBeNull();
});

test('users can delete a task', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($project)->create();

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->set('deletingTaskId', $task->id)
        ->call('deleteTask')
        ->assertSet('deletingTaskId', null);

    expect(Task::find($task->id))->toBeNull();
});

test('users cannot manage tasks from other users projects', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();
    $otherTask = Task::factory()->for($otherProject)->create(['title' => 'Secret task']);

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->call('openEditTaskModal', $otherTask->id)
        ->assertStatus(404);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('users can create a project', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->call('storeProject')
        ->assertHasErrors(['projectName' => 'required']);

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->set('projectName', 'Website redesign')
        ->call('storeProject')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'name' => 'Website redesign',
    ]);
});

test('tasks can be filtered by status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $doneTask = Task::factory()->completed()->for($project)->create(['title' => 'Finished task']);
    $todoTask = Task::factory()->for($project)->create(['title' => 'Upcoming task']);

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->set('status', TaskStatus::Done->value)
        ->assertSee('Finished task')
        ->assertDontSee('Upcoming task');

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->set('status', TaskStatus::Todo->value)
        ->assertSee('Upcoming task')
        ->assertDontSee('Finished task');
});

test('tasks can be filtered by priority', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $highTask = Task::factory()->for($project)->create([
        'title' => 'Urgent task',
        'priority' => TaskPriority::High,
    ]);
    $lowTask = Task::factory()->for($project)->create([
        'title' => 'Relaxed task',
        'priority' => TaskPriority::Low,
    ]);

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->set('priority', TaskPriority::High->value)
        ->assertSee('Urgent task')
        ->assertDontSee('Relaxed task');

    expect($highTask->exists)->toBeTrue();
    expect($lowTask->exists)->toBeTrue();
});

test('tasks can be searched by title', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $matchTask = Task::factory()->for($project)->create(['title' => 'Fix the login bug']);
    $otherTask = Task::factory()->for($project)->create(['title' => 'Write documentation']);

    Livewire::actingAs($user)
        ->test('pages::tasks.index')
        ->set('search', 'login bug')
        ->assertSee('Fix the login bug')
        ->assertDontSee('Write documentation');

    expect($matchTask->exists)->toBeTrue();
    expect($otherTask->exists)->toBeTrue();
});

test('deleting a project deletes its tasks', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    Task::factory()->count(3)->for($project)->create();

    $project->delete();

    expect(Task::query()->where('project_id', $project->id)->count())->toBe(0);
});

test('deleting a user deletes their projects', function () {
    $user = User::factory()->create();
    Project::factory()->count(2)->for($user)->create();

    $user->delete();

    expect(Project::query()->where('user_id', $user->id)->count())->toBe(0);
});
