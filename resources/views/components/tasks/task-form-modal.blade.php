@props([
    'projects' => [],
    'lockProject' => false,
])

<flux:modal name="task-modal" wire:model.self="showTaskModal" variant="floating" position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ $editingTaskId ? __('Edit task') : __('New task') }}</flux:heading>
        <flux:text size="sm">{{ $editingTaskId ? __('Update the task details below.') : __('Add a new task to one of your projects.') }}</flux:text>
    </div>

    <div class="space-y-4">
        <flux:input wire:model="taskTitle" :label="__('Title')" required />

        <flux:textarea wire:model="taskDescription" :label="__('Description')" rows="3" />

        <div class="grid gap-4 sm:grid-cols-2">
            @if (! $lockProject)
                <flux:select wire:model="taskProjectId" :label="__('Project')" required>
                    @foreach ($projects as $project)
                        <flux:select.option :value="$project->id">{{ $project->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="taskDueDate" :label="__('Due date')" type="date" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="taskStatus" :label="__('Status')" required>
                @foreach (\App\Enums\TaskStatus::cases() as $case)
                    <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="taskPriority" :label="__('Priority')" required>
                @foreach (\App\Enums\TaskPriority::cases() as $case)
                    <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2">
        <flux:button variant="ghost" wire:click="$set('showTaskModal', false)">{{ __('Cancel') }}</flux:button>
        <flux:button variant="primary" wire:click="{{ $editingTaskId ? 'updateTask' : 'storeTask' }}">
            {{ $editingTaskId ? __('Save changes') : __('Create task') }}
        </flux:button>
    </div>
</flux:modal>
