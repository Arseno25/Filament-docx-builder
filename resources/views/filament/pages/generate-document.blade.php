<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="button" color="gray" wire:click="submit('test')">
                Test Generate
            </x-filament::button>

            <x-filament::button type="button" wire:click="submit('final')">
                Generate Final
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
