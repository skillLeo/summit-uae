<?php

namespace Tests\Feature\Cms\Admin;

use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\MediaFiles;
use App\Filament\Resources\DocumentSamples\Pages\EditDocumentSample;
use App\Filament\Resources\DocumentSamples\Pages\ListDocumentSamples;
use App\Filament\Resources\MediaLibrary\Pages\CreateMediaAsset;
use App\Filament\Resources\MediaLibrary\Pages\EditMediaAsset;
use App\Filament\Resources\MediaLibrary\Pages\ListMediaAssets;
use App\Filament\Resources\Team\Pages\EditTeamMember;
use App\Filament\Resources\Team\Pages\ListTeamMembers;
use App\Models\Content\DocumentSample;
use App\Models\Content\TeamMember;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Phase 4 media screens (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.8, §8.9): the Media Library (upload, the WebP
 * and AVIF conversions, EXIF removed, 10 MB limit, PDF downloads, alt text per language, focal point), the idempotent
 * summit:media-import, the Team screen (and an uploaded photo on the Design 2 About page, while the page stays the same
 * without one) and the Document Samples screen.
 */
final class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        Livewire::flushState();
        parent::tearDown();
    }

    /** A JPEG with an EXIF APP1 segment (Make = "SummitTestCam"). */
    private function jpegWithExif(int $width = 1000, int $height = 1250): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 200, 180, 150));
        imagefilledellipse($image, (int) ($width / 2), (int) ($height / 3), (int) ($width / 3), (int) ($width / 3), imagecolorallocate($image, 40, 40, 40));
        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = (string) ob_get_clean();

        $make = "SummitTestCam\0";
        $tiff = 'II'.pack('v', 42).pack('V', 8)            // little-endian TIFF header, IFD at 8
            .pack('v', 1)                                     // one entry
            .pack('vvVV', 0x010F, 2, strlen($make), 26)       // Make, ASCII, count, value at offset 26
            .pack('V', 0)                                     // no next IFD
            .$make;
        $app1 = "Exif\0\0".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    private function storedPath(Media $media, string $conversion = ''): string
    {
        return $conversion === '' ? $media->getPath() : $media->getPath($conversion);
    }

    public function test_an_uploaded_image_is_cleaned_of_exif_converted_and_keeps_its_alt_text_per_language(): void
    {
        $bytes = $this->jpegWithExif();
        $fixture = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
        file_put_contents($fixture, $bytes);
        $this->assertSame('SummitTestCam', @exif_read_data($fixture)['Make'] ?? null, 'the fixture carries EXIF');

        Livewire::test(CreateMediaAsset::class)
            ->fillForm([
                'name' => 'Office photo',
                'collection' => 'library',
                'file' => UploadedFile::fake()->createWithContent('Office Photo.jpg', $bytes),
                'alt' => ['en' => 'The meeting room', 'ar' => 'غرفة الاجتماعات', 'ru' => 'Переговорная'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $asset = MediaAsset::query()->where('name', 'Office photo')->firstOrFail();
        $media = $asset->file();
        $this->assertNotNull($media);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame(['en' => 'The meeting room', 'ar' => 'غرفة الاجتماعات', 'ru' => 'Переговорная'], $media->getCustomProperty('alt'));
        $this->assertSame('upload', $media->getCustomProperty('source'));
        $this->assertSame('pending', $asset->copy_source);
        $this->assertSame([1000, 1250], [$media->getCustomProperty('width'), $media->getCustomProperty('height')]);

        // EXIF removed from the stored original (and the conversions)
        $original = $this->storedPath($media);
        $this->assertFileExists($original);
        $exif = @exif_read_data($original);
        $this->assertFalse(is_array($exif) && isset($exif['Make']), 'no EXIF in the stored original');
        $this->assertStringNotContainsString('SummitTestCam', (string) file_get_contents($original));
        $this->assertStringNotContainsString("Exif\0\0", (string) file_get_contents($original));

        // WebP and AVIF at 480 and 768, and the image's own width (1000) for 1200; never upscaled, no 1600
        foreach (['webp', 'avif'] as $format) {
            foreach ([480 => 600, 768 => 960, 1200 => 1250] as $name => $height) {
                $this->assertTrue($media->hasGeneratedConversion("{$format}-{$name}"), "{$format}-{$name}");
                $path = $this->storedPath($media, "{$format}-{$name}");
                $this->assertFileExists($path);
                [$w, $h] = getimagesize($path);
                $this->assertSame([min($name, 1000), $height], [$w, $h], "{$format}-{$name} size");
                $this->assertStringNotContainsString('SummitTestCam', (string) file_get_contents($path));
            }
            $this->assertFalse($media->hasGeneratedConversion("{$format}-1600"), 'no upscaled 1600');
        }
        $this->assertSame('/storage/'.$media->getKey().'/conversions/office-photo-webp-480.webp 480w, /storage/'.$media->getKey().'/conversions/office-photo-webp-768.webp 768w, /storage/'.$media->getKey().'/conversions/office-photo-webp-1200.webp 1000w',
            MediaFiles::srcset($media->uuid, 'webp'));
        $this->assertSame('غرفة الاجتماعات', MediaFiles::alt($media->uuid, 'ar'));

        // the alt text and focal point are edited on the asset; saving is immediate
        Livewire::test(EditMediaAsset::class, ['record' => $asset->getKey()])
            ->fillForm(['alt' => ['en' => 'The meeting room', 'ar' => '', 'ru' => 'Переговорная комната'], 'focal_point' => [30.5, 40]])
            ->call('save')
            ->assertHasNoFormErrors();
        $media->refresh();
        $this->assertSame(['en' => 'The meeting room', 'ar' => '', 'ru' => 'Переговорная комната'], $media->getCustomProperty('alt'));
        $this->assertSame([30.5, 40], MediaFiles::focus($media->uuid));
        $this->assertSame('30.5% 40%', MediaFiles::objectPosition($media->uuid));

        // the list shows it, and the missing-alt filter finds it (Arabic is empty now)
        Livewire::test(ListMediaAssets::class)
            ->assertCanSeeTableRecords([$asset])
            ->filterTable('cms_media_missing_alt', true)
            ->assertCanSeeTableRecords([$asset]);

        // Trash and restore; a trashed asset is not served
        $asset->delete();
        $this->assertNull(MediaFiles::byUuid($media->uuid));
        $asset->restore();
        $this->assertNotNull(MediaFiles::byUuid($media->uuid));
        @unlink($fixture);
    }

    public function test_a_file_over_10_mb_is_refused(): void
    {
        Livewire::test(CreateMediaAsset::class)
            ->fillForm([
                'name' => 'Too big',
                'collection' => 'library',
                'file' => UploadedFile::fake()->create('big.jpg', 11 * 1024, 'image/jpeg'),
            ])
            ->call('create')
            ->assertHasFormErrors(['file']);
        $this->assertSame(0, MediaAsset::query()->where('name', 'Too big')->count());
        $this->assertSame(0, Media::query()->where('collection_name', MediaAsset::FILE)->count());

        $big = tempnam(sys_get_temp_dir(), 'big');
        file_put_contents($big, str_repeat('a', 11 * 1024 * 1024));
        try {
            MediaFiles::add(MediaAsset::query()->create(['name' => 'x', 'collection' => 'library']), $big, MediaAsset::FILE, 'x', 'x.jpg');
            $this->fail('A file over 10 MB must be refused.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('10 MB', $e->getMessage());
        } finally {
            @unlink($big);
        }
    }

    public function test_a_pdf_download_is_accepted_without_conversions(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
        Livewire::test(CreateMediaAsset::class)
            ->fillForm([
                'name' => 'Checklist',
                'collection' => 'library',
                'file' => UploadedFile::fake()->createWithContent('checklist.pdf', $pdf),
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $media = MediaAsset::query()->where('name', 'Checklist')->firstOrFail()->file();
        $this->assertSame('application/pdf', $media->mime_type);
        $this->assertSame([], array_filter((array) $media->generated_conversions));
        $this->assertSame($pdf, (string) file_get_contents($media->getPath()), 'a PDF is stored as it is');
        $this->assertSame('', MediaFiles::srcset($media->uuid));

        // a script is refused
        Livewire::test(CreateMediaAsset::class)
            ->fillForm(['name' => 'Script', 'collection' => 'library', 'file' => UploadedFile::fake()->createWithContent('x.html', '<script>alert(1)</script>')])
            ->call('create')
            ->assertHasFormErrors(['file']);
    }

    public function test_the_media_import_is_idempotent_and_changes_nothing_on_the_public_site(): void
    {
        $normalise = fn (string $html) => preg_replace(['/nonce="[^"]*"/', '/name="_token" value="[^"]*"/', '/<meta name="csrf-token" content="[^"]*"/', '/name="idempotency_key" value="[^"]*"/'], ['nonce=""', 'name="_token"', '<meta name="csrf-token"', 'name="idempotency_key"'], $html);
        $page = fn (string $locale, string $design) => $this->withUnencryptedCookies(['summit_design' => $design])->get("/{$locale}/about/")->assertOk()->getContent();
        $before = ['en2' => $page('en', '2'), 'ar2' => $page('ar', '2'), 'en1' => $page('en', '1')];

        $this->assertSame(0, Artisan::call('summit:media-import'));
        $media = Media::query()->count();
        $assets = MediaAsset::query()->count();
        $this->assertSame(2, TeamMember::query()->whereHas('media')->count(), 'the two supplied portraits');
        $this->assertSame(7, $assets, 'six brand files and the default social image');
        $this->assertSame(9, $media);
        $ahmed = TeamMember::query()->findOrFail('team-ahmed')->getFirstMedia(TeamMember::PHOTO);
        $this->assertSame('import', $ahmed->getCustomProperty('source'));
        $this->assertSame('ahmed-mohammadi-952.jpg', $ahmed->file_name);
        $this->assertSame([49.7, 33.2], $ahmed->getCustomProperty('focal_point'));
        $this->assertSame('أحمد محمدي، المدير التنفيذي والشريك المؤسس في سَمِت للاستشارات القانونية', $ahmed->getCustomProperty('alt')['ar']);
        $og = MediaAsset::query()->where('collection', 'social')->firstOrFail()->file();
        $this->assertSame(content()->ui('brand.name', 'ar'), $og->getCustomProperty('alt')['ar']);
        $this->assertSame([1200, 630], [$og->getCustomProperty('width'), $og->getCustomProperty('height')]);

        // second run: nothing new
        $this->assertSame(0, Artisan::call('summit:media-import'));
        $this->assertStringContainsString('| 0       | 9', Artisan::output());
        $this->assertSame($media, Media::query()->count());
        $this->assertSame($assets, MediaAsset::query()->count());

        // the imported originals are not uploads: the pages render exactly as before
        $this->assertSame($normalise($before['en2']), $normalise($page('en', '2')));
        $this->assertSame($normalise($before['ar2']), $normalise($page('ar', '2')));
        $this->assertSame($normalise($before['en1']), $normalise($page('en', '1')));
        $this->assertStringContainsString('/images/d2/team/ahmed-mohammadi-480.webp', $before['en2']);
    }

    public function test_the_team_screens_render_and_an_untouched_save_creates_no_draft(): void
    {
        Livewire::test(ListTeamMembers::class)
            ->assertOk()
            ->assertCanSeeTableRecords(TeamMember::query()->get())
            ->assertSee('Ahmed Mohammadi');

        Livewire::test(EditTeamMember::class, ['record' => 'team-ahmed'])
            ->assertOk()
            ->assertSee(__('cms_media.team.areas'))
            ->assertFormSet(fn (array $state) => ['areas.en.heading' => 'Areas of Focus'])
            ->assertFormSet(['focal_point' => [49.7, 33.2], 'photo_pending' => false, 'portrait' => 'ahmed-mohammadi'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull(TeamMember::query()->findOrFail('team-ahmed')->draft, 'saving an unchanged form creates no draft');

        Livewire::test(EditTeamMember::class, ['record' => 'team-sarah'])->assertOk()->assertSee(__('cms_media.team.focal_empty'));
    }

    public function test_new_expertise_items_beyond_three_are_refused_but_approved_ones_are_kept(): void
    {
        $member = TeamMember::query()->findOrFail('team-ahmed');
        $stored = count(Publisher::live($member)['areas']['en']['items']);
        $this->assertGreaterThan(3, $stored, 'the approved profile has more than 3 items');

        $component = Livewire::test(EditTeamMember::class, ['record' => 'team-ahmed']);
        $items = $component->get('data.areas.en.items');
        $items['new-item'] = ['text' => 'One more area'];
        $component->set('data.areas.en.items', $items)
            ->call('save')
            ->assertHasErrors(['data.areas.en.items']);
        $this->assertNull($member->refresh()->draft);
    }

    public function test_an_uploaded_team_photo_and_focal_point_are_used_by_the_design_2_about_page(): void
    {
        $this->assertSame(0, Artisan::call('summit:media-import'));

        Livewire::test(EditTeamMember::class, ['record' => 'team-ahmed'])
            ->fillForm([
                'photo' => UploadedFile::fake()->createWithContent('new-portrait.jpg', $this->jpegWithExif(900, 1200)),
                'focal_point' => [52, 30],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $upload = MediaFiles::teamPhoto('team-ahmed');
        $this->assertNotNull($upload, 'the upload is stored at once');
        $this->assertSame('upload', $upload->getCustomProperty('source'));
        $this->assertCount(2, TeamMember::query()->findOrFail('team-ahmed')->getMedia(TeamMember::PHOTO), 'the imported original stays');
        [$w, $h] = getimagesize($upload->getPath('webp-768'));
        $this->assertSame([768, 960], [$w, $h], 'cut to the 4:5 portrait frame');
        $this->assertStringNotContainsString('SummitTestCam', (string) file_get_contents($upload->getPath()));

        // the focal point is a draft field: published, it moves the eye line
        $member = TeamMember::query()->findOrFail('team-ahmed');
        $this->assertSame([52, 30], Publisher::draft($member)['focal_point'] ?? null);
        Publisher::publish($member);

        $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/about/')->assertOk()->getContent();
        $url = '/storage/'.$upload->getKey().'/conversions/new-portrait-webp-480.webp';
        $this->assertStringContainsString('src="'.$url.'"', $html);
        $this->assertStringContainsString('/storage/'.$upload->getKey().'/conversions/new-portrait-avif-768.avif 768w', $html);
        $this->assertStringContainsString('.portrait[data-member="team-ahmed"]{--portrait-x:52%;--portrait-y:30%', $html);
        $this->assertStringNotContainsString('/images/d2/team/ahmed-mohammadi-480.webp', $html);
        $this->assertStringContainsString('/images/d2/team/dr-mohamed-raouf-480.webp', $html, 'the other members keep their static files');

        // Design 1 keeps its static portraits
        $d1 = $this->withUnencryptedCookies(['summit_design' => '1'])->get('/en/about/')->assertOk()->getContent();
        $this->assertStringNotContainsString('/storage/', $d1);
    }

    public function test_the_document_samples_screens_render_without_create_or_delete(): void
    {
        Livewire::test(ListDocumentSamples::class)
            ->assertOk()
            ->assertCanSeeTableRecords(DocumentSample::query()->get());
        $this->assertFalse(\App\Filament\Resources\DocumentSamples\DocumentSampleResource::canCreate());

        Livewire::test(EditDocumentSample::class, ['record' => 'cheque'])
            ->assertOk()
            ->assertSee('Amount in words')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull(DocumentSample::query()->findOrFail('cheque')->draft, 'saving an unchanged form creates no draft');

        // a changed label becomes a draft; the part keys stay
        $component = Livewire::test(EditDocumentSample::class, ['record' => 'cheque']);
        $parts = $component->get('data.parts');
        $first = array_key_first($parts);
        $component->set("data.parts.{$first}.label.en", 'Date of the cheque')->call('save')->assertHasNoFormErrors();
        $draft = Publisher::draft(DocumentSample::query()->findOrFail('cheque'));
        $this->assertSame('Date of the cheque', $draft['parts'][0]['label']['en']);
        $this->assertSame(['date', 'payee', 'words', 'figures', 'signature', 'memo'], array_column($draft['parts'], 'key'));
    }
}
