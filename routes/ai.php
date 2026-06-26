<?php

use App\Mcp\Servers\ConvoroDevServer;
use Laravel\Mcp\Facades\Mcp;

// Local (stdio) developer-tooling MCP server for working ON the Convoro codebase.
// Start it with:  php artisan mcp:start convoro-dev
//
// This is intentionally a *local* server: it is driven over stdin/stdout by a
// CLI client (Claude Code / Desktop) and is never exposed over HTTP, so it adds
// no network attack surface. It is also safe to keep registered here because
// laravel/mcp is a require-dev dependency — production deploys run
// `composer install --no-dev`, where the package (and this file's loader) does
// not exist, so the server is simply absent on live forums.
//
// Do NOT add an Mcp::web(...) registration for this server.
Mcp::local('convoro-dev', ConvoroDevServer::class);
