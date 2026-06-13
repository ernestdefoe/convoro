<?php

namespace App\Http\Controllers;

use App\Jobs\BackupJob;
use App\Support\Backup;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin database backup + restore. Backups run in the background (BackupJob);
 * the page polls status(). Restore is destructive and snapshots first.
 */
class BackupController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('Admin/Backups', $this->state());
    }

    /** @return array<string,mixed> */
    private function state(): array
    {
        return [
            'available' => Backup::available(),
            'reason' => Backup::unavailableReason(),
            'driver' => Backup::driver(),
            'offsiteConfigured' => Backup::offsiteConfigured(),
            'running' => (bool) Settings::get('backups.running', false),
            'status' => (string) Settings::get('backups.status', ''),
            'last' => Settings::get('backups.last'),
            'backups' => Backup::list(),
            'settings' => [
                'enabled' => (bool) Settings::get('backups.enabled', false),
                'frequency' => (string) Settings::get('backups.frequency', 'daily'),
                'retention' => (int) Settings::get('backups.retention', 7),
                'offsite' => (bool) Settings::get('backups.offsite', false),
            ],
        ];
    }

    /** Lightweight poll for the page. */
    public function status(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->state());
    }

    /** Kick off a manual backup in the background. */
    public function run(): RedirectResponse
    {
        if (! Backup::available()) {
            return back()->with('status', Backup::unavailableReason() ?? __('Backups are unavailable on this host.'));
        }
        if (Settings::get('backups.running')) {
            return back()->with('status', __('A backup or restore is already running.'));
        }
        BackupJob::dispatch('create');

        return back()->with('status', __('Backup started — it runs in the background.'));
    }

    public function download(string $name): BinaryFileResponse
    {
        $path = Backup::absolutePath($name);
        abort_unless($path !== null, 404);

        return response()->download($path, basename($name));
    }

    public function destroy(string $name): RedirectResponse
    {
        Backup::delete($name);

        return back()->with('status', __('Backup deleted.'));
    }

    /** Restore from a stored backup name or an uploaded .gz file. */
    public function restore(Request $request): RedirectResponse
    {
        if (! Backup::available()) {
            return back()->with('status', Backup::unavailableReason() ?? __('Restore is unavailable on this host.'));
        }
        if (Settings::get('backups.running')) {
            return back()->with('status', __('A backup or restore is already running.'));
        }

        $request->validate([
            'name' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:524288'], // 512 MB
            'confirm' => ['required', 'in:RESTORE'],
        ]);

        $name = null;
        if ($request->hasFile('file')) {
            $upload = $request->file('file');
            $ext = strtolower($upload->getClientOriginalName());
            if (! str_ends_with($ext, '.gz')) {
                return back()->with('status', __('Please upload a .gz backup file.'));
            }
            $name = 'uploaded-'.now()->format('Y-m-d_His').'.'.($this->matchesDriverExt($ext));
            $upload->move(storage_path('app/'.Backup::DIR), $name);
        } elseif ($request->filled('name')) {
            $name = basename((string) $request->string('name'));
            abort_unless(Backup::absolutePath($name) !== null, 404);
        } else {
            return back()->with('status', __('Choose a backup to restore.'));
        }

        BackupJob::dispatch('restore', $name);

        return back()->with('status', __('Restore started — the site may be briefly unavailable.'));
    }

    private function matchesDriverExt(string $original): string
    {
        return Backup::driver() === 'sqlite' ? 'sqlite.gz' : 'sql.gz';
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'frequency' => ['required', 'in:daily,weekly'],
            'retention' => ['required', 'integer', 'min:1', 'max:365'],
            'offsite' => ['required', 'boolean'],
        ]);

        Settings::setMany([
            'backups.enabled' => $data['enabled'],
            'backups.frequency' => $data['frequency'],
            'backups.retention' => $data['retention'],
            'backups.offsite' => $data['offsite'] && Backup::offsiteConfigured(),
        ]);

        return back()->with('status', __('Backup settings saved.'));
    }
}
