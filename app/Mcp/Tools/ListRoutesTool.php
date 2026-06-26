<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-routes')]
#[Description('List the application HTTP routes, optionally filtered by a URI/name substring or HTTP method. Returns the methods, URI, route name, and controller action for each match.')]
#[IsReadOnly]
#[IsIdempotent]
class ListRoutesTool extends Tool
{
    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('Only include routes whose URI or name contains this substring (case-insensitive).'),
            'method' => $schema->string()
                ->description('Only include routes serving this HTTP method, e.g. GET or POST.'),
            'limit' => $schema->integer()
                ->description('Maximum number of routes to return (default 200).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $path = $request->get('path');
        $method = $request->get('method') ? strtoupper((string) $request->get('method')) : null;
        $limit = max(1, (int) ($request->get('limit') ?? 200));

        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $methods = array_values(array_diff($route->methods(), ['HEAD']));

            if ($method !== null && ! in_array($method, $methods, true)) {
                continue;
            }

            $uri = '/'.ltrim($route->uri(), '/');
            $name = $route->getName() ?? '';

            if ($path !== null && stripos($uri, $path) === false && stripos($name, $path) === false) {
                continue;
            }

            $action = ltrim(str_replace('App\\Http\\Controllers\\', '', $route->getActionName()), '\\');

            $routes[] = [
                'methods' => implode('|', $methods),
                'uri' => $uri,
                'name' => $name,
                'action' => $action,
            ];
        }

        usort($routes, fn ($a, $b) => $a['uri'] <=> $b['uri']);

        $total = count($routes);
        if ($total === 0) {
            return Response::text('No routes matched the given filters.');
        }

        $routes = array_slice($routes, 0, $limit);

        $lines = array_map(
            fn ($r) => sprintf('%-11s %-46s %-32s %s', $r['methods'], $r['uri'], $r['name'], $r['action']),
            $routes
        );

        $header = sprintf('%d route(s) matched, showing %d:', $total, count($routes));

        return Response::text($header."\n\n".implode("\n", $lines));
    }
}
