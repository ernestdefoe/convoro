<?php

namespace App\Http\Controllers;

use App\Support\Installer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstallController extends Controller
{
    /** The branded wizard (plain Blade — no Inertia / DB-dependent props). */
    public function show(): View
    {
        return view('install', [
            'requirements' => Installer::requirements(),
            'drivers' => Installer::drivers(),
            'appUrl' => url('/'),
        ]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $db = $request->validate([
            'driver' => ['required', 'in:mysql,pgsql'],
            'host' => ['required', 'string'],
            'port' => ['nullable', 'integer'],
            'database' => ['required', 'string'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
        ]);

        try {
            Installer::testDatabase($db);

            return response()->json(['ok' => true, 'message' => 'Connection successful.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function install(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site.name' => ['required', 'string', 'max:60'],
            'site.url' => ['required', 'url'],
            'db.driver' => ['required', 'in:mysql,pgsql'],
            'db.host' => ['required', 'string'],
            'db.port' => ['nullable', 'integer'],
            'db.database' => ['required', 'string'],
            'db.username' => ['nullable', 'string'],
            'db.password' => ['nullable', 'string'],
            'admin.name' => ['required', 'string', 'max:60'],
            'admin.email' => ['required', 'email'],
            'admin.password' => ['required', 'string', 'min:8'],
        ]);

        try {
            Installer::run($data);

            return response()->json(['ok' => true, 'redirect' => '/']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
