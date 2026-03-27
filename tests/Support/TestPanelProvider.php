<?php

namespace Arseno25\DocxBuilder\Tests\Support;

use Arseno25\DocxBuilder\DocxBuilderPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->authGuard('web')
            ->plugin(DocxBuilderPlugin::make());
    }
}
