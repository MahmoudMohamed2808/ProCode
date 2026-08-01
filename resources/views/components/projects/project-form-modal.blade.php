<flux:modal name="project-modal" wire:model.self="showProjectModal" variant="floating" position="right" class="space-y-6">
    <div>
        <flux:heading size="lg">{{ $editingProjectId ? __('Edit project') : __('New project') }}</flux:heading>
        <flux:text size="sm">{{ $editingProjectId ? __('Update the project details below.') : __('Create a project to organize your tasks.') }}</flux:text>
    </div>

    <div class="space-y-4">
        <flux:input wire:model="projectName" :label="__('Name')" required />

        <flux:textarea wire:model="projectDescription" :label="__('Description')" rows="3" />

        <flux:input wire:model="projectColor" :label="__('Color')" type="color" />
    </div>

    <div class="flex items-center justify-end gap-2">
        <flux:button variant="ghost" wire:click="$set('showProjectModal', false)">{{ __('Cancel') }}</flux:button>
        <flux:button variant="primary" wire:click="{{ $editingProjectId ? 'updateProject' : 'storeProject' }}">
            {{ $editingProjectId ? __('Save changes') : __('Create project') }}
        </flux:button>
    </div>
</flux:modal>
