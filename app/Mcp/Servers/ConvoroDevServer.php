<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ArtisanCommandTool;
use App\Mcp\Tools\ConfigGetTool;
use App\Mcp\Tools\DatabaseQueryTool;
use App\Mcp\Tools\DescribeModelTool;
use App\Mcp\Tools\ListModelsTool;
use App\Mcp\Tools\ListRoutesTool;
use App\Mcp\Tools\TailLogTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Convoro Dev')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Read-only developer tooling for the Convoro forum platform (Laravel 13 + Inertia/Vue 3).
Use these tools to introspect the running application instead of guessing about it:

- list-routes      find HTTP endpoints and the controller actions behind them
- list-models      enumerate the Eloquent models and their tables
- describe-model   inspect a model's columns, casts, fillable/guarded, and relations
- db-query         run SELECT-only queries against the live database
- config-get       read configuration values (secret-looking values are redacted)
- tail-log         read the tail of storage/logs/laravel.log
- artisan          run a small allowlist of non-destructive artisan commands

Every tool runs inside the booted Laravel kernel, so results reflect the real
application state. Never assume a table column, route, or config value exists —
query it. This server is registered only outside production.
TXT)]
class ConvoroDevServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListRoutesTool::class,
        ListModelsTool::class,
        DescribeModelTool::class,
        DatabaseQueryTool::class,
        ConfigGetTool::class,
        TailLogTool::class,
        ArtisanCommandTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [];

    /**
     * The prompts registered with this MCP server.
     *
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];
}
