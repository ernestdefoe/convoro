<?php

namespace App\Http\Controllers;

use App\Jobs\RunFlarumImportJob;
use App\Support\Importers\MysqlDumpToSqlite;
use App\Support\Importers\DiscourseImporter;
use App\Support\Importers\InvisionImporter;
use App\Support\Importers\MybbImporter;
use App\Support\Importers\NodebbImporter;
use App\Support\Importers\PhpbbImporter;
use App\Support\Importers\SmfImporter;
use App\Support\Importers\VanillaImporter;
use App\Support\Importers\VbulletinImporter;
use App\Support\Importers\XenForoImporter;
use App\Support\FlarumImporter;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Import wizard — migrate a community from other forum software into Convoro.
 * Source-pluggable: Flarum, XenForo, phpBB, Discourse, vBulletin and Invision Community.
 */
class ImportController extends Controller
{
    private const IMPORTERS = [
        'flarum' => FlarumImporter::class,
        'xenforo' => XenForoImporter::class,
        'phpbb' => PhpbbImporter::class,
        'discourse' => DiscourseImporter::class,
        'vbulletin' => VbulletinImporter::class,
        'invision' => InvisionImporter::class,
        'mybb' => MybbImporter::class,
        'smf' => SmfImporter::class,
        'vanilla' => VanillaImporter::class,
        'nodebb' => NodebbImporter::class,
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Import', [
            'state' => $this->state(),
            'sources' => [
                ['id' => 'flarum', 'name' => 'Flarum', 'db' => 'MySQL', 'prefix' => '', 'tested' => true],
                ['id' => 'xenforo', 'name' => 'XenForo', 'db' => 'MySQL', 'prefix' => '', 'tested' => true],
                ['id' => 'phpbb', 'name' => 'phpBB', 'db' => 'MySQL', 'prefix' => 'phpbb_', 'tested' => true],
                ['id' => 'discourse', 'name' => 'Discourse', 'db' => 'PostgreSQL', 'prefix' => '', 'tested' => true],
                ['id' => 'vbulletin', 'name' => 'vBulletin (3–6)', 'db' => 'MySQL', 'prefix' => '', 'tested' => true],
                ['id' => 'invision', 'name' => 'Invision Community', 'db' => 'MySQL', 'prefix' => '', 'tested' => true],
                ['id' => 'mybb', 'name' => 'MyBB', 'db' => 'MySQL', 'prefix' => 'mybb_', 'tested' => true],
                ['id' => 'smf', 'name' => 'SMF', 'db' => 'MySQL', 'prefix' => 'smf_', 'tested' => true],
                ['id' => 'vanilla', 'name' => 'Vanilla Forums', 'db' => 'MySQL', 'prefix' => 'GDN_', 'tested' => true],
                ['id' => 'nodebb', 'name' => 'NodeBB', 'db' => 'Redis', 'prefix' => '', 'tested' => true],
            ],
        ]);
    }

    /** Validate the connection and return source counts (wizard step 1). */
    public function test(Request $request): JsonResponse
    {
        $cfg = $this->validateCfg($request);

        try {
            $result = self::IMPORTERS[$cfg['source']]::test($cfg);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * Upload a database FILE instead of connecting live — for managed hosts that
     * only give you your DB. A SQLite file is used directly; a MySQL dump
     * (.sql / .sql.gz) is loaded into a scratch SQLite database. Either way the
     * existing importers run against it unchanged.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'source' => ['required', Rule::in(array_keys(self::IMPORTERS))],
            'file' => ['required', 'file', 'max:1048576'],   // up to 1 GB (subject to php.ini upload limits)
            'prefix' => ['nullable', 'string', 'max:64'],
            'source_url' => ['nullable', 'string', 'max:255'],
        ]);

        $source = $request->input('source');
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        self::cleanupScratch();
        $dir = storage_path('app/imports');
        File::ensureDirectoryExists($dir);
        $sqlitePath = $dir.'/scratch-'.Str::random(8).'.sqlite';

        try {
            if (in_array($ext, ['sqlite', 'sqlite3', 'db'], true)) {
                $file->move($dir, basename($sqlitePath));   // already a SQLite DB
            } else {
                MysqlDumpToSqlite::convert($file->getRealPath(), $sqlitePath);   // SQL dump → SQLite
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => __('Could not read that file: :err', ['err' => $e->getMessage()])], 422);
        }

        $cfg = [
            'source' => $source, 'driver' => 'sqlite', 'database' => $sqlitePath,
            'host' => '', 'port' => null, 'username' => '', 'password' => '',
            'prefix' => (string) $request->input('prefix', ''),
            'source_url' => (string) $request->input('source_url', ''),
            'flarum_url' => (string) $request->input('source_url', ''),
        ];

        try {
            $result = self::IMPORTERS[$source]::test($cfg);
        } catch (\Throwable $e) {
            @unlink($sqlitePath);

            return response()->json(['ok' => false, 'message' => __('File loaded, but it doesn’t look like a :name database: :err', ['name' => $source, 'err' => $e->getMessage()])], 422);
        }

        Settings::set('import.file_cfg', $cfg);

        return response()->json($result + ['file' => true]);
    }

    /** Kick off the import in the background (wizard step 3). */
    public function start(Request $request): RedirectResponse
    {
        if (Settings::get('import.running')) {
            return back()->with('status', __('An import is already running.'));
        }

        if ($request->input('mode') === 'file') {
            $cfg = Settings::get('import.file_cfg');
            if (! $cfg) {
                return back()->with('status', __('Upload a database file first.'));
            }
        } else {
            $cfg = $this->validateCfg($request);
        }
        $opts = ['tags' => $request->boolean('import_tags', true)];

        Settings::setMany([
            'import.running' => true,
            'import.percent' => 0,
            'import.status' => __('Starting…'),
            'import.summary' => [],
        ]);
        RunFlarumImportJob::dispatch($cfg, $opts, self::IMPORTERS[$cfg['source']]);

        return back()->with('status', __('Import started — it runs in the background. Progress updates below.'));
    }

    /** Poll for live progress. */
    public function progress(): JsonResponse
    {
        return response()->json($this->state());
    }

    private function validateCfg(Request $request): array
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(self::IMPORTERS))],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:64'],
            'source_url' => ['nullable', 'string', 'max:255'],
        ]);

        $sourceUrl = $data['source_url'] ?? '';

        return [
            'source' => $data['source'],
            'host' => $data['host'],
            'port' => $data['port'] ?? null,
            'database' => $data['database'],
            'username' => $data['username'],
            'password' => $data['password'] ?? '',
            'prefix' => $data['prefix'] ?? '',
            'source_url' => $sourceUrl,
            'flarum_url' => $sourceUrl, // back-compat for the Flarum importer
        ];
    }

    /** Remove any prior scratch SQLite databases from earlier uploads. */
    private static function cleanupScratch(): void
    {
        $dir = storage_path('app/imports');
        if (is_dir($dir)) {
            foreach (glob($dir.'/scratch-*.sqlite') ?: [] as $f) {
                @unlink($f);
            }
        }
        Settings::set('import.file_cfg', null);
    }

    private function state(): array
    {
        return [
            'running' => (bool) Settings::get('import.running', false),
            'percent' => (int) Settings::get('import.percent', 0),
            'status' => Settings::get('import.status'),
            'summary' => Settings::get('import.summary', []),
            'lastStatus' => Settings::get('import.last_status'),
        ];
    }
}
