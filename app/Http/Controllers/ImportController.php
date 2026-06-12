<?php

namespace App\Http\Controllers;

use App\Jobs\RunFlarumImportJob;
use App\Support\Importers\DiscourseImporter;
use App\Support\Importers\PhpbbImporter;
use App\Support\Importers\VbulletinImporter;
use App\Support\Importers\XenForoImporter;
use App\Support\FlarumImporter;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Import wizard — migrate a community from other forum software into Convoro.
 * Source-pluggable: Flarum, XenForo, phpBB, Discourse and vBulletin.
 */
class ImportController extends Controller
{
    private const IMPORTERS = [
        'flarum' => FlarumImporter::class,
        'xenforo' => XenForoImporter::class,
        'phpbb' => PhpbbImporter::class,
        'discourse' => DiscourseImporter::class,
        'vbulletin' => VbulletinImporter::class,
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Import', [
            'state' => $this->state(),
            'sources' => [
                ['id' => 'flarum', 'name' => 'Flarum', 'db' => 'MySQL', 'prefix' => '', 'tested' => true],
                ['id' => 'xenforo', 'name' => 'XenForo', 'db' => 'MySQL', 'prefix' => '', 'tested' => false],
                ['id' => 'phpbb', 'name' => 'phpBB', 'db' => 'MySQL', 'prefix' => 'phpbb_', 'tested' => false],
                ['id' => 'discourse', 'name' => 'Discourse', 'db' => 'PostgreSQL', 'prefix' => '', 'tested' => false],
                ['id' => 'vbulletin', 'name' => 'vBulletin', 'db' => 'MySQL', 'prefix' => '', 'tested' => false],
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

    /** Kick off the import in the background (wizard step 3). */
    public function start(Request $request): RedirectResponse
    {
        if (Settings::get('import.running')) {
            return back()->with('status', __('An import is already running.'));
        }

        $cfg = $this->validateCfg($request);
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
