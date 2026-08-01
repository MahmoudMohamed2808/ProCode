<flux:modal name="delete-project-modal" variant="small">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete project') }}</flux:heading>
            <flux:text size="sm">{{ __('Are you sure you want to delete this project? All of its tasks will also be deleted. This action cannot be undone.') }}</flux:text>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="deleteProject">{{ __('Delete') }}</flux:button>
        </div>
    </div>
</flux:modal>
