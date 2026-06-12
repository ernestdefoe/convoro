<?php

namespace App\Http\Controllers;

use App\Jobs\RunFlarumImportJob;
use App\Support\FlarumImporter;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Import wizard — migrate a community from other forum software into Convoro.
 * v1 ships the Flarum importer; the wizard is built to host more sources later.
 */
class ImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Import', [
            'state' => $this->state(),
        ]);
    }

    /** Validate the connection and return source counts (wizard step 1). */
    public function test(Request $request): JsonResponse
    {
        $cfg = $this->validateCfg($request);

        try {
            $result = FlarumImporter::test($cfg);
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
        RunFlarumImportJob::dispatch($cfg, $opts);

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
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:64'],
            'flarum_url' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'host' => $data['host'],
            'port' => $data['port'] ?? 3306,
            'database' => $data['database'],
            'username' => $data['username'],
            'password' => $data['password'] ?? '',
            'prefix' => $data['prefix'] ?? '',
            'flarum_url' => $data['flarum_url'] ?? '',
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
