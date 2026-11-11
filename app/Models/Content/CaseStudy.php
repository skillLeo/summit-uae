<?php

namespace App\Models\Content;

/** A case study (case-01–case-06, sections of P04; brief §8.11): preview fields, the body as blocks. */
final class CaseStudy extends ContentModel
{
    public const DATASET = 'case_studies';

    protected $table = 'case_studies';
}
