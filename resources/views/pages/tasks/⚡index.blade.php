<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Tasks')] class extends Component {
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public ?string $status = '';

    #[Url(history: true)]
    public ?string $priority = '';

    #[Url(history: true)]
    public ?int $project_id = null;

    public bool $showTaskModal = false;
    public bool $showProjectModal = false;
    public ?int $editingTaskId = null;
    public ?int $editingProjectId = null;
    public ?int $deletingTaskId = null;

    public string $taskTitle = '';
    public ?string $taskDescription = null;
    public ?int $taskProjectId = null;
    public string $taskStatus = '';
    public string $taskPriority = '';
    public ?string $taskDueDate = null;

    public string $projectName = '';
    public ?string $projectDescription = null;
    public string $projectColor = '#6366f1';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->taskStatus = TaskStatus::Todo->value;
        $this->taskPriority = TaskPriority::Medium->value;
    }

    #[Computed]
    public function projects(): \Illuminate\Database\Eloquent\Collection
    {
        return Project::query()
            ->where('user_id', auth()->id())
            ->withCount('tasks')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function tasks(): LengthAwarePaginator
    {
        return Task::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', auth()->id()))
            ->when($this->search, fn ($query) => $query->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->priority, fn ($query) => $query->where('priority', $this->priority))
            ->when($this->project_id, fn ($query) => $query->where('project_id', $this->project_id))
            ->with('project')
            ->latest()
            ->paginate(10);
    }

    /**
     * Open the task modal for creating a new task.
     */
    public function openCreateTaskModal(): void
    {
        $this->resetTaskForm();

        if ($this->projects->isEmpty()) {
            Flux::toast(variant: 'warning', text: __('Create a project first.'));

            return;
        }

        $this->taskProjectId ??= $this->projects->first()->id;
        $this->showTaskModal = true;
    }

    /**
     * Open the task modal for editing an existing task.
     */
    public function openEditTaskModal(int $taskId): void
    {
        $task = Task::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', auth()->id()))
            ->findOrFail($taskId);

        $this->editingTaskId = $task->id;
        $this->taskTitle = $task->title;
        $this->taskDescription = $task->description;
        $this->taskProjectId = $task->project_id;
        $this->taskStatus = $task->status->value;
        $this->taskPriority = $task->priority->value;
        $this->taskDueDate = $task->due_date?->format('Y-m-d');
        $this->showTaskModal = true;
    }

    /**
     * Store a new task.
     */
    public function storeTask(): void
    {
        $validated = $this->validateTask();

        Task::create([
            'project_id' => $validated['taskProjectId'],
            'title' => $validated['taskTitle'],
            'description' => $validated['taskDescription'],
            'status' => $validated['taskStatus'],
            'priority' => $validated['taskPriority'],
            'due_date' => $validated['taskDueDate'],
        ]);

        $this->resetTaskForm();
        $this->showTaskModal = false;

        Flux::toast(variant: 'success', text: __('Task created.'));
    }

    /**
     * Update the task being edited.
     */
    public function updateTask(): void
    {
        $validated = $this->validateTask();

        $task = Task::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', auth()->id()))
            ->findOrFail($this->editingTaskId);

        $task->update([
            'title' => $validated['taskTitle'],
            'description' => $validated['taskDescription'],
            'project_id' => $validated['taskProjectId'],
            'status' => $validated['taskStatus'],
            'priority' => $validated['taskPriority'],
            'due_date' => $validated['taskDueDate'],
            'completed_at' => $validated['taskStatus'] === TaskStatus::Done->value ? $task->completed_at ?? now() : null,
        ]);

        $this->resetTaskForm();
        $this->showTaskModal = false;

        Flux::toast(variant: 'success', text: __('Task updated.'));
    }

    /**
     * Toggle the completion state of a task.
     */
    public function toggleTask(int $taskId): void
    {
        $task = Task::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', auth()->id()))
            ->findOrFail($taskId);

        $task->isCompleted() ? $task->markAsNotCompleted() : $task->markAsCompleted();
    }

    /**
     * Delete a task after confirmation.
     */
    public function deleteTask(): void
    {
        $task = Task::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', auth()->id()))
            ->findOrFail($this->deletingTaskId);

        $task->delete();

        $this->deletingTaskId = null;

        Flux::modal('delete-task-modal')->close();

        Flux::toast(variant: 'success', text: __('Task deleted.'));
    }

    /**
     * Store a new project.
     */
    public function storeProject(): void
    {
        $validated = $this->validateProject();

        $project = Project::create([
            'user_id' => auth()->id(),
            'name' => $validated['projectName'],
            'description' => $validated['projectDescription'],
            'color' => $validated['projectColor'],
        ]);

        $this->resetProjectForm();
        $this->showProjectModal = false;

        $this->taskProjectId = $project->id;

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
     * Validate the task form fields.
     *
     * @return array<string, mixed>
     */
    private function validateTask(): array
    {
        return $this->validate([
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskDescription' => ['nullable', 'string'],
            'taskProjectId' => ['required', 'exists:projects,id'],
            'taskStatus' => ['required', Rule::enum(TaskStatus::class)],
            'taskPriority' => ['required', Rule::enum(TaskPriority::class)],
            'taskDueDate' => ['nullable', 'date'],
        ]);
    }

    /**
     * Reset the task form to its default state.
     */
    private function resetTaskForm(): void
    {
        $this->editingTaskId = null;
        $this->taskTitle = '';
        $this->taskDescription = null;
        $this->taskProjectId = null;
        $this->taskStatus = TaskStatus::Todo->value;
        $this->taskPriority = TaskPriority::Medium->value;
        $this->taskDueDate = null;
        $this->resetValidation();
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
    <flux:heading size="xl" level="1">{{ __('Tasks') }}</flux:heading>
    <flux:subheading class="mb-6">{{ __('Keep track of everything you need to get done.') }}</flux:subheading>

    <flux:card class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search tasks…')"
                class="md:max-w-xs"
            />

            <div class="flex flex-wrap items-center gap-2">
                <flux:select wire:model.live="status" :placeholder="__('All statuses')" class="md:max-w-44">
                    @foreach (TaskStatus::cases() as $case)
                        <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="priority" :placeholder="__('All priorities')" class="md:max-w-44">
                    @foreach (TaskPriority::cases() as $case)
                        <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="project_id" :placeholder="__('All projects')" class="md:max-w-48">
                    @foreach ($this->projects as $project)
                        <flux:select.option :value="$project->id">{{ $project->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex items-center gap-2">
                <flux:button variant="ghost" wire:click="$set('showProjectModal', true)" icon="folder-plus">
                    {{ __('New project') }}
                </flux:button>
                <flux:button wire:click="openCreateTaskModal" variant="primary" icon:trailing="plus">
                    {{ __('New task') }}
                </flux:button>
            </div>
        </div>

        <flux:separator />

        @if ($this->tasks->isEmpty())
            <div class="flex flex-col items-center justify-center gap-2 py-12 text-center">
                <flux:icon.inbox class="size-8 text-zinc-400" />
                <flux:heading size="lg">{{ __('No tasks found') }}</flux:heading>
                <flux:text class="max-w-sm">{{ __('Try adjusting your filters or create a new task to get started.') }}</flux:text>
                <flux:button wire:click="openCreateTaskModal" variant="primary" class="mt-2">
                    {{ __('New task') }}
                </flux:button>
            </div>
        @else
            <flux:table :paginate="$this->tasks">
                <flux:table.columns>
                    <flux:table.column class="w-12"></flux:table.column>
                    <flux:table.column>{{ __('Task') }}</flux:table.column>
                    <flux:table.column>{{ __('Project') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                    <flux:table.column>{{ __('Due date') }}</flux:table.column>
                    <flux:table.column class="w-24"></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->tasks as $task)
                        <flux:table.row :key="$task->id">
                            <flux:table.cell>
                                <flux:checkbox
                                    wire:change="toggleTask({{ $task->id }})"
                                    :checked="$task->isCompleted()"
                                    aria-label="{{ __('Mark as done') }}"
                                />
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <flux:heading size="sm" class="{{ $task->isCompleted() ? 'line-through text-zinc-400' : '' }}">
                                        {{ $task->title }}
                                    </flux:heading>
                                    @if ($task->description)
                                        <flux:text size="sm" class="line-clamp-1 max-w-md">{{ $task->description }}</flux:text>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <span class="size-2.5 rounded-full" style="background-color: {{ $task->project->color }}"></span>
                                    <flux:text size="sm">{{ $task->project->name }}</flux:text>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge :color="match ($task->status) {
                                    TaskStatus::Done => 'green',
                                    TaskStatus::InProgress => 'blue',
                                    default => 'zinc',
                                }" size="sm">{{ $task->status->label() }}</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:badge :color="match ($task->priority) {
                                    TaskPriority::High => 'red',
                                    TaskPriority::Medium => 'amber',
                                    default => 'zinc',
                                }" size="sm">{{ $task->priority->label() }}</flux:badge>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:text size="sm" class="{{ $task->due_date?->isPast() && ! $task->isCompleted() ? 'text-red-600 dark:text-red-400' : '' }}">
                                    {{ $task->due_date ? Carbon::parse($task->due_date)->format('M j, Y') : '—' }}
                                </flux:text>
                            </flux:table.cell>

                            <flux:table.cell class="text-end">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button wire:click="openEditTaskModal({{ $task->id }})" icon="pencil-square" size="sm" variant="ghost" inset="left" />
                                    <flux:modal.trigger name="delete-task-modal">
                                        <flux:button wire:click="$set('deletingTaskId', {{ $task->id }})" icon="trash" size="sm" variant="ghost" inset="right" />
                                    </flux:modal.trigger>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    @include('components.tasks.task-form-modal', ['projects' => $this->projects])

    @include('components.projects.project-form-modal')

    @include('components.tasks.delete-task-modal')
</div>
