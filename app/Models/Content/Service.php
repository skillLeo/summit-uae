<?php

namespace App\Models\Content;

final class Service extends ContentModel
{
    protected $table = 'services';

    protected const TRANSLATABLE = ['title'];

    public const PLAIN = ['slug', 'position'];
}
