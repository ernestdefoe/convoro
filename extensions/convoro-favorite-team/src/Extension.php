<?php

namespace Convoro\Ext\FavoriteTeam;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Favorite Team — first-party Convoro extension.
 *
 * Members pick one of 136 FBS teams; the choice is stored as the team's ESPN id
 * on their users row (see the migration) and surfaces as a logo badge next to
 * their name on posts. The team catalog is a static JSON file bundled with the
 * extension — no external calls, no database table for teams.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        // Public: the full team catalog (drives the badge logos + the picker).
        // Static data — let the browser cache it.
        Route::middleware('web')->get('/api/ext/favorite-team/teams', function () {
            return response()
                ->json(['teams' => self::teams()])
                ->header('Cache-Control', 'public, max-age=86400');
        });

        // Signed-in member: read + set their own team ("" / null clears it).
        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('/api/ext/favorite-team/me', fn () => response()->json([
                'teamId' => Auth::user()->favorite_team ?: null,
            ]));

            Route::post('/api/ext/favorite-team/set', function (Request $r) {
                $id = trim((string) $r->input('teamId', ''));
                if ($id !== '' && ! self::exists($id)) {
                    return response()->json(['ok' => false, 'error' => 'unknown_team'], 422);
                }
                $user = Auth::user();
                $user->favorite_team = $id !== '' ? $id : null;
                $user->save();

                return response()->json(['ok' => true, 'teamId' => $user->favorite_team]);
            });
        });
    }

    /** The bundled team catalog, memoized for the request. */
    private static ?array $teams = null;

    private static function teams(): array
    {
        if (self::$teams === null) {
            $json = @file_get_contents(__DIR__.'/../resources/teams.json');
            self::$teams = $json ? (json_decode($json, true) ?: []) : [];
        }

        return self::$teams;
    }

    /** Whether a team id is in the catalog. */
    private static function exists(string $id): bool
    {
        foreach (self::teams() as $t) {
            if ((string) ($t['id'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }
}
