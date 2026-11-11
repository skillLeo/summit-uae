<?php

namespace App\Models\Content;

final class SpecialistSite extends ContentModel
{
    protected $table = 'specialist_sites';

    protected const TRANSLATABLE = ['label'];

    public const PLAIN = ['position', 'domain', 'url'];
}
