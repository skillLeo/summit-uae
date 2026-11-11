<?php

namespace App\Models\Content;

/** An associated specialist site (X01–X06; the brief's associated_sites, §8.11): name, domain, URL, order, the linked practice area. */
final class SpecialistSite extends ContentModel
{
    public const DATASET = 'specialist_sites';

    protected $table = 'specialist_sites';

    protected static function extraCasts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
