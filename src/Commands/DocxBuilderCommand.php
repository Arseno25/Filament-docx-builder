<?php

namespace Arseno25\DocxBuilder\Commands;

use Illuminate\Console\Command;

class DocxBuilderCommand extends Command
{
    public $signature = 'docx-builder';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
