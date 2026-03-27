<?php

namespace Arseno25\DocxBuilder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Arseno25\DocxBuilder\DocxBuilder
 */
class DocxBuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Arseno25\DocxBuilder\DocxBuilder::class;
    }
}
