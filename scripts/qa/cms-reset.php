<?php

/*
| Local QA only (tests/e2e-admin): puts the CMS back to the approved content after the Playwright admin flows.
|   php scripts/qa/cms-reset.php snapshot <file>          save the settings rows (before the flows)
|   php scripts/qa/cms-reset.php restore <file> <since>   restore them, drop what the flows created after <since> (UTC
|                                                         "Y-m-d H:i:s"): new guides, uploaded photos, revisions,
|                                                         redirects; clear drafts and schedules, set updated_by back to
|                                                         null and run summit:content-import (the approved sources) and
|                                                         summit:media-import
| Refuses to run in production.
*/

use App\Content\Cms\ContentCache;
use App\Content\Cms\Editing\Registry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (app()->isProduction()) {
    fwrite(STDERR, "Refusing to run in production.\n");
    exit(1);
}

[$script, $mode, $file] = array_pad($argv, 3, null);
$since = $argv[3] ?? null;

if ($mode === 'snapshot') {
    file_put_contents($file, json_encode(DB::table('settings')->get()->map(fn ($r) => (array) $r)->all(), JSON_UNESCAPED_UNICODE));
    echo "snapshot: ".DB::table('settings')->count()." settings\n";
    exit(0);
}

if ($mode !== 'restore' || $since === null) {
    fwrite(STDERR, "usage: snapshot <file> | restore <file> <since>\n");
    exit(1);
}

foreach (json_decode((string) file_get_contents($file), true) as $row) {
    DB::table('settings')->where('id', $row['id'])->update(['payload' => $row['payload'], 'locked' => $row['locked']]);
}

foreach (Registry::models() as $class) {
    $table = (new $class)->getTable();
    $key = (new $class)->getKeyName();
    // records the flows created
    foreach (DB::table($table)->where('created_at', '>=', $since)->pluck($key) as $id) {
        DB::table('revisions')->where('revisionable_type', Registry::type($class))->where('revisionable_id', (string) $id)->delete();
        DB::table($table)->where($key, $id)->delete();
    }
    DB::table($table)->whereNotNull('updated_by')->orWhereNotNull('draft')->orWhereNotNull('publish_at')->orWhereNotNull('deleted_at')
        ->update(['updated_by' => null, 'draft' => null, 'publish_at' => null, 'deleted_at' => null, 'status' => 'published']);
}
DB::table('navigation_items')->update(['updated_by' => null]);
DB::table('revisions')->where('created_at', '>=', $since)->delete();
DB::table('redirects')->where('created_at', '>=', $since)->delete();

// uploaded team photos
foreach (App\Models\Content\TeamMember::all() as $member) {
    foreach ($member->getMedia('photo') as $media) {
        if ($media->getCustomProperty('source') === 'upload') {
            $media->delete();
        }
    }
}

// the import puts back every row nobody edited whose source differs (all of them now: updated_by is null)
Artisan::call('summit:content-import');
echo Artisan::output();
Artisan::call('summit:media-import');
// the import writes a revision for every row it put back: those belong to this QA run too
DB::table('revisions')->where('created_at', '>=', $since)->delete();
ContentCache::bump();
echo "restored\n";
