<?php

namespace App\Models\Content;

final class CaseStudy extends ContentModel
{
    protected $table = 'case_studies';

    protected const TRANSLATABLE = ['title'];

    public const PLAIN = ['anchor', 'position'];
}
