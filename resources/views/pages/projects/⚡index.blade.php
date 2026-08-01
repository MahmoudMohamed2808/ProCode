<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Projects')] class extends Component {
    public bool $showProjectModal = false;
    public ?int $editingProjectId = null;
    public ?int $deletingProjectId = null;

    public string $projectName = '';
    public ?string $projectDescription = null;
    public string $projectColor = '#6366f1';

    #[Computed]
    public function projects(): Collection
    {
        return Project::query()
            ->where('user_id', auth()->id())
            ->withCount('tasks')
            ->withCount(['tasks as completed_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done->value)])
            ->orderBy('name')
            ->get();
    }

    /**
     * Open the project modal for creating a new project.
     */
    public function openCreateProjectModal(): void
    {
        $this->resetProjectForm();
        $this->showProjectModal = true;
    }

    /**
     * Open the project modal for editing an existing project.
     */
    public function openEditProjectModal(int $projectId): void
    {
        $project = Project::query()
            ->where('user_id', auth()->id())
            ->findOrFail($projectId);

        $this->editingProjectId = $project->id;
        $this->projectName = $project->name;
        $this->projectDescription = $project->description;
        $this->projectColor = $project->color;
        $this->showProjectModal = true;
    }

    /**
     * Store a new project.
     */
    public function storeProject(): void
    {
        $validated = $this->validateProject();

        Project::create([
            'user_id' => auth()->id(),
            'name' => $validated['projectName'],
            'description' => $validated['projectDescription'],
            'color' => $validated['projectColor'],
        ]);

        $this->resetProjectForm();
        $this->showProjectModal = false;

        Flux::toast(variant: 'success', text: __('Project created.'));
    }

    /**
     * Update the project being edited.
     */
    public function updateProject(): void
    {
        $validated = $this->validateProject();

        $project = Project::query()
            ->where('user_id', auth()->id())
            ->findOrFail($this->editingProjectId);

        $project->update([
            'name' => $validated['projectName'],
            'description' => $validated['projectDescription'],
            'color' => $validated['projectColor'],
        ]);

        $this->resetProjectForm();
        $this->showProjectModal = false;

        Flux::toast(variant: 'success', text: __('Project updated.'));
    }

    /**
     * Delete a project after confirmation.
     */
    public function deleteProject(): void
    {
        $project = Project::query()
            ->where('user_id', auth()->id())
            ->findOrFail($this->deletingProjectId);

        $project->delete();

        $this->deletingProjectId = null;

        Flux::modal('delete-project-modal')->close();

        Flux::toast(variant: 'success', text: __('Project deleted.'));
    }

    /**
     * Validate the project form fields.
     *
     * @return array<string, mixed>
     */
    private function validateProject(): array
    {
        return $this->validate([
            'projectName' => ['required', 'string', 'max:255'],
            'projectDescription' => ['nullable', 'string'],
            'projectColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
    }

    /**
     * Reset the project form to its default state.
     */
    private function resetProjectForm(): void
    {
        $this->editingProjectId = null;
        $this->projectName = '';
        $this->projectDescription = null;
        $this->projectColor = '#6366f1';
        $this->resetValidation();
    }
}; ?>

<div class="w-full">
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Projects') }}</flux:heading>
            <flux:subheading>{{ __('Organize your work into projects and track their progress.') }}</flux:subheading>
        </div>

        <flux:button wire:click="openCreateProjectModal" variant="primary" icon:trailing="plus">
            {{ __('New project') }}
        </flux:button>
    </div>

    @if ($this->projects->isEmpty())
        <flux:card class="flex flex-col items-center justify-center gap-2 py-16 text-center">
            <flux:icon.folder-plus class="size-8 text-zinc-400" />
            <flux:heading size="lg">{{ __('No projects yet') }}</flux:heading>
            <flux:text class="max-w-sm">{{ __('Create your first project to start organizing your tasks.') }}</flux:text>
            <flux:button wire:click="openCreateProjectModal" variant="primary" class="mt-2">
                {{ __('New project') }}
            </flux:button>
        </flux:card>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->projects as $project)
                @php
                    $percent = $project->tasks_count > 0
                        ? (int) round($project->completed_tasks_count / $project->tasks_count * 100)
                        : 0;
                @endphp

                <flux:card class="group flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <span class="size-3 shrink-0 rounded-full" style="background-color: {{ $project->color }}"></span>
                            <flux:heading size="lg" class="truncate">
                                <flux:link :href="route('projects.show', $project)" wire:navigate class="truncate">
                                    {{ $project->name }}
                                </flux:link>
                            </flux:heading>
                        </div>

                        <div class="flex items-center gap-1">
                            <flux:button wire:click="openEditProjectModal({{ $project->id }})" icon="pencil-square" size="sm" variant="ghost" inset="left" />
                            <flux:modal.trigger name="delete-project-modal">
                                <flux:button wire:click="$set('deletingProjectId', {{ $project->id }})" icon="trash" size="sm" variant="ghost" inset="right" />
                            </flux:modal.trigger>
                        </div>
                    </div>

                    @if ($project->description)
                        <flux:text class="line-clamp-2 min-h-10">{{ $project->description }}</flux:text>
                    @endif

                    <div class="mt-auto space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <flux:text size="sm">{{ $project->completed_tasks_count }} {{ __('of') }} {{ $project->tasks_count }} {{ __('done') }}</flux:text>
                            <flux:text size="sm" class="font-medium">{{ $percent }}%</flux:text>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full transition-all" style="width: {{ $percent }}%; background-color: {{ $project->color }}"></div>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    @include('components.projects.project-form-modal')

    @include('components.projects.delete-project-modal')
</div>
