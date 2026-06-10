<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a fresh deployment to the web installer, and keeps /install
 * inaccessible once the site is installed.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $onInstaller = $request->is('install', 'install/*');
        $installed = Installer::isInstalled();

        if (! $installed && ! $onInstaller) {
            return redirect('/install');
        }

        if ($installed && $onInstaller) {
            return redirect('/');
        }

        return $next($request);
    }
}
