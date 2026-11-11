<?php

namespace Tests\Unit;

use App\Content\Cms\Tree;
use App\Content\Import\ImportException;
use PHPUnit\Framework\TestCase;

/** One structure for every language with the texts inside (brief §8.3, App\Content\Cms\Tree). */
final class TreeTest extends TestCase
{
    private const SAMPLE = [
        'en' => ['heading' => 'Steps', 'items' => ['One', 'Two'], 'link' => ['label' => 'Read', 'target' => 'G01'], 'intro' => null,
            'language_options' => ['ar' => 'Arabic', 'en' => 'English', 'ru' => 'Russian'], 'count' => 2],
        'ar' => ['heading' => 'الخطوات', 'items' => ['واحد', 'اثنان'], 'link' => ['label' => 'اقرأ', 'target' => 'G01'], 'intro' => 'مقدمة',
            'language_options' => ['ar' => 'العربية', 'en' => 'الإنجليزية', 'ru' => 'الروسية'], 'count' => 2],
        'ru' => ['heading' => 'Шаги', 'items' => ['Один', 'Два'], 'link' => ['label' => 'Читать', 'target' => 'G01'], 'intro' => null,
            'language_options' => ['ar' => 'Арабский', 'en' => 'Английский', 'ru' => 'Русский'], 'count' => 2],
    ];

    public function test_transpose_then_resolve_gives_back_every_language_exactly(): void
    {
        $tree = Tree::transpose(self::SAMPLE);
        $this->assertSame(['en' => 'Steps', 'ar' => 'الخطوات', 'ru' => 'Шаги'], $tree['heading']);
        $this->assertSame('G01', $tree['link']['target'], 'a structural leaf identical in every language is stored once');
        $this->assertSame(2, $tree['count']);
        $this->assertIsArray($tree['language_options']['ar'], 'a group keyed by language codes keeps its leaves wrapped');
        foreach (self::SAMPLE as $locale => $expected) {
            $this->assertSame($expected, Tree::resolve($tree, $locale), $locale);
        }
    }

    public function test_a_language_without_a_text_leaves_it_out(): void
    {
        $tree = Tree::transpose(['en' => 'Only English', 'ru' => 'Только русский']);
        $this->assertSame(Tree::absent(), Tree::resolve($tree, 'ar'));
        $this->assertSame(['a' => 'x'], Tree::resolve(['a' => ['en' => 'x'], 'b' => ['ru' => 'y']], 'en'));
        $this->assertSame(['a' => null], Tree::resolve(['a' => ['en' => null, 'ar' => 'س']], 'en'), 'a null text stays a null');
    }

    public function test_a_different_structure_is_an_import_error(): void
    {
        $this->expectException(ImportException::class);
        Tree::transpose(['en' => ['items' => ['a', 'b']], 'ar' => ['items' => ['أ']]]);
    }

    public function test_keys_in_another_order_are_an_import_error(): void
    {
        $this->expectException(ImportException::class);
        Tree::transpose(['en' => ['a' => 'x', 'b' => 'y'], 'ar' => ['b' => 'ص', 'a' => 'س']]);
    }

    public function test_limits_are_115_percent_of_the_longest_text_in_any_language(): void
    {
        $limits = Tree::limits(Tree::transpose(self::SAMPLE));
        $this->assertSame((int) ceil(mb_strlen('الخطوات') * 1.15), $limits['heading'], 'the Arabic heading is the longest');
        $this->assertSame((int) ceil(mb_strlen('اثنان') * 1.15), $limits['items.*']);
        $this->assertArrayNotHasKey('link.target', $limits, 'no limit on structure');
    }
}
