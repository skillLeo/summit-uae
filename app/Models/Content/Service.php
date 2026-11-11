<?php

namespace App\Models\Content;

/** A practice area (S01–S11; the brief's practice_areas, §8.11): every field the DOCX gives, its page body as blocks. */
final class Service extends ContentModel
{
    public const DATASET = 'services';

    protected $table = 'services';
}
