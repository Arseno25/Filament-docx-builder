<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <p class="text-sm text-gray-600">
            This page stores Docx Builder settings in the database (when migrations are installed) and applies them at runtime.
        </p>

        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">
                Save settings
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
