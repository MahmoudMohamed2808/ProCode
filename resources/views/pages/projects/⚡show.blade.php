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
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Project')] class extends Component {
    use WithPagination;

    public Project $project;

    public bool $showTaskModal = false;
    public ?int $editingTaskId = null;
    public ?int $deletingTaskId = null;

    public string $taskTitle = '';
    public ?string $taskDescription = null;
    public ?int $taskProjectId = null;
    public string $taskStatus = '';
    public string $taskPriority = '';
    public ?string $taskDueDate = null;

    /**
     * Mount the component.
     */
    public function mount(Project $project): void
    {
        abort_unless($project->user_id === auth()->id(), 404);

        $this->project = $project;
        $this->taskProjectId = $project->id;
        $this->taskStatus = TaskStatus::Todo->value;
        $this->taskPriority = TaskPriority::Medium->value;
    }

    #[Computed]
    public function tasks(): LengthAwarePaginator
    {
        return $this->project->tasks()
            ->with('project')
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => $this->project->tasks()->count(),
            'done' => $this->project->tasks()->where('status', TaskStatus::Done->value)->count(),
            'in_progress' => $this->project->tasks()->where('status', TaskStatus::InProgress->value)->count(),
            'todo' => $this->project->tasks()->where('status', TaskStatus::Todo->value)->count(),
        ];
    }

    /**
     * Open the task modal for creating a new task.
     */
    public function openCreateTaskModal(): void
    {
        $this->resetTaskForm();

        $this->taskProjectId = $this->project->id;
        $this->showTaskModal = true;
    }

    /**
     * Open the task modal for editing an existing task.
     */
    public function openEditTaskModal(int $taskId): void
    {
        $task = $this->project->tasks()->findOrFail($taskId);

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
     * Store a new task in this project.
     */
    public function storeTask(): void
    {
        $validated = $this->validateTask();

        $this->project->tasks()->create([
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

        $task = $this->project->tasks()->findOrFail($this->editingTaskId);

        $task->update([
            'title' => $validated['taskTitle'],
            'description' => $validated['taskDescription'],
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
        $task = $this->project->tasks()->findOrFail($taskId);

        $task->isCompleted() ? $task->markAsNotCompleted() : $task->markAsCompleted();
    }

    /**
     * Delete a task after confirmation.
     */
    public function deleteTask(): void
    {
        $task = $this->project->tasks()->findOrFail($this->deletingTaskId);

        $task->delete();

        $this->deletingTaskId = null;

        Flux::modal('delete-task-modal')->close();

        Flux::toast(variant: 'success', text: __('Task deleted.'));
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
        $this->taskStatus = TaskStatus::Todo->value;
        $this->taskPriority = TaskPriority::Medium->value;
        $this->taskDueDate = null;
        $this->resetValidation();
    }
}; ?>

<div class="w-full">
    <flux:breadcrumbs class="mb-4">
        <flux:breadcrumbs.item :href="route('projects.index')" wire:navigate icon="folder">{{ __('Projects') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $this->project->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="flex items-start gap-3">
            <span class="mt-2 size-3 shrink-0 rounded-full" style="background-color: {{ $this->project->color }}"></span>
            <div>
                <flux:heading size="xl" level="1">{{ $this->project->name }}</flux:heading>
                @if ($this->project->description)
                    <flux:subheading class="max-w-2xl">{{ $this->project->description }}</flux:subheading>
                @endif
            </div>
        </div>

        <flux:button wire:click="openCreateTaskModal" variant="primary" icon:trailing="plus">
            {{ __('New task') }}
        </flux:button>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</flux:heading>
            <flux:heading size="2xl" class="mt-1">{{ $this->stats['total'] }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('In progress') }}</flux:heading>
            <flux:heading size="2xl" class="mt-1">{{ $this->stats['in_progress'] }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Done') }}</flux:heading>
            <flux:heading size="2xl" class="mt-1">{{ $this->stats['done'] }}</flux:heading>
        </flux:card>

        <flux:card>
            <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('To do') }}</flux:heading>
            <flux:heading size="2xl" class="mt-1">{{ $this->stats['todo'] }}</flux:heading>
        </flux:card>
    </div>

    <flux:card class="space-y-6">
        <flux:heading size="lg">{{ __('Tasks') }}</flux:heading>

        <flux:separator />

        @if ($this->tasks->isEmpty())
            <div class="flex flex-col items-center justify-center gap-2 py-12 text-center">
                <flux:icon.inbox class="size-8 text-zinc-400" />
                <flux:heading size="lg">{{ __('No tasks in this project') }}</flux:heading>
                <flux:text class="max-w-sm">{{ __('Create the first task to get started.') }}</flux:text>
                <flux:button wire:click="openCreateTaskModal" variant="primary" class="mt-2">
                    {{ __('New task') }}
                </flux:button>
            </div>
        @else
            <flux:table :paginate="$this->tasks">
                <flux:table.columns>
                    <flux:table.column class="w-12"></flux:table.column>
                    <flux:table.column>{{ __('Task') }}</flux:table.column>
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

    @include('components.tasks.task-form-modal', ['lockProject' => true])

    @include('components.tasks.delete-task-modal')
</div>
