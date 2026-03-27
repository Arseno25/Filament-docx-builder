<?php

namespace Arseno25\DocxBuilder\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

class TestPerson extends Model
{
    protected $table = 'test_people';

    protected $fillable = [
        'name',
    ];
}
