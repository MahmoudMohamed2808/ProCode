<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
});

test('authenticated users can visit the projects page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee('Projects');
});

test('users only see their own projects', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Project::factory()->for($user)->create(['name' => 'My project']);
    Project::factory()->for($otherUser)->create(['name' => 'Someone elses project']);

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee('My project')
        ->assertDontSee('Someone elses project');
});

test('users can create a project', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::projects.index')
        ->call('openCreateProjectModal')
        ->set('projectName', 'Website redesign')
        ->call('storeProject')
        ->assertHasNoErrors()
        ->assertSet('showProjectModal', false);

    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'name' => 'Website redesign',
    ]);
});

test('project creation requires a name', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::projects.index')
        ->call('openCreateProjectModal')
        ->set('projectName', '')
        ->call('storeProject')
        ->assertHasErrors(['projectName' => 'required']);
});

test('users can update a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Old name']);

    Livewire::actingAs($user)
        ->test('pages::projects.index')
        ->call('openEditProjectModal', $project->id)
        ->set('projectName', 'New name')
        ->set('projectColor', '#10b981')
        ->call('updateProject')
        ->assertHasNoErrors();

    $project->refresh();

    expect($project->name)->toBe('New name');
    expect($project->color)->toBe('#10b981');
});

test('users can delete a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $task = Task::factory()->for($project)->create();

    Livewire::actingAs($user)
        ->test('pages::projects.index')
        ->set('deletingProjectId', $project->id)
        ->call('deleteProject')
        ->assertSet('deletingProjectId', null);

    expect(Project::find($project->id))->toBeNull();
    expect(Task::find($task->id))->toBeNull();
});

test('users cannot edit projects from other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();

    Livewire::actingAs($user)
        ->test('pages::projects.index')
        ->call('openEditProjectModal', $otherProject->id)
        ->assertStatus(404);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('project detail page shows only the owned project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'My project']);

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('My project');
});

test('project detail page returns 404 for other users projects', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create();

    $this->actingAs($user)
        ->get(route('projects.show', $otherProject))
        ->assertNotFound();
});

test('project detail page only shows tasks from that project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();

    Task::factory()->for($project)->create(['title' => 'Task in project']);
    Task::factory()->for($otherProject)->create(['title' => 'Task in other project']);

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Task in project')
        ->assertDontSee('Task in other project');
});

test('users can create a task scoped to the project from the detail page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::projects.show', ['project' => $project])
        ->call('openCreateTaskModal')
        ->set('taskTitle', 'Scoped task')
        ->call('storeTask')
        ->assertHasNoErrors()
        ->assertSet('showTaskModal', false);

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'title' => 'Scoped task',
    ]);
});

test('project detail page shows correct stats', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    Task::factory()->count(2)->completed()->for($project)->create();
    Task::factory()->for($project)->create(['status' => TaskStatus::InProgress]);
    Task::factory()->count(3)->for($project)->create();

    $component = Livewire::actingAs($user)
        ->test('pages::projects.show', ['project' => $project]);

    expect($component->get('project')->id)->toBe($project->id);
    expect($component->get('stats'))->toBe([
        'total' => 6,
        'done' => 2,
        'in_progress' => 1,
        'todo' => 3,
    ]);
});

test('task progress is calculated on the projects index', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    Task::factory()->count(2)->completed()->for($project)->create();
    Task::factory()->count(2)->for($project)->create();

    $component = Livewire::actingAs($user)->test('pages::projects.index');

    $projects = $component->get('projects');

    expect($projects->first()->completed_tasks_count)->toBe(2);
    expect($projects->first()->tasks_count)->toBe(4);
});
