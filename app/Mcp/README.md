# Convoro Dev MCP Server

A local [Model Context Protocol](https://modelcontextprotocol.io) server for working
**on the Convoro codebase**. It boots inside the Laravel kernel and gives an AI client
(Claude Code / Claude Desktop) read-only tools to introspect the running application
instead of guessing about it.

Built on the official [`laravel/mcp`](https://github.com/laravel/mcp) package.

## Tools

| Tool             | What it does                                                                 | Annotations         |
| ---------------- | --------------------------------------------------------------------------- | ------------------- |
| `list-routes`    | List HTTP routes, filterable by URI/name substring and method.              | read-only, idempotent |
| `list-models`    | Enumerate Eloquent models in `app/Models` and their tables.                 | read-only, idempotent |
| `describe-model` | Columns, casts, fillable/guarded, and relations for one model.              | read-only, idempotent |
| `db-query`       | Run a **SELECT-only** SQL query (write/DDL statements are rejected).        | read-only           |
| `config-get`     | Read a config value by dot-key; secret-looking values are redacted.         | read-only, idempotent |
| `tail-log`       | Tail the last N lines of a `storage/logs` file (default `laravel.log`).     | read-only           |
| `artisan`        | Run an **allowlisted** non-destructive artisan command.                     | read-only           |

`artisan` allowlist: `about`, `env`, `route:list`, `migrate:status`, `queue:failed`,
`queue:monitor`, `db:show`, `db:table`, `schedule:list`, `config:show`, `event:list`,
`model:show`. Anything else is refused.

## Layout

```
app/Mcp/
  Servers/ConvoroDevServer.php   # registers the tools, name "Convoro Dev"
  Tools/*.php                    # one class per tool
routes/ai.php                    # Mcp::local('convoro-dev', ConvoroDevServer::class)
```

## Security model

- **Local (stdio) only.** The server is registered with `Mcp::local()` and is driven
  over stdin/stdout by a CLI client. There is **no** `Mcp::web()` registration, so it
  is never exposed over HTTP and adds no network attack surface. Do not add one.
- **Dev-only dependency.** `laravel/mcp` is in `require-dev`. Production deploys run
  `composer install --no-dev`, so the package — and the loader that includes
  `routes/ai.php` — is absent on live forums. The server simply does not exist there.
- **Read-only by construction.** `db-query` is SELECT-guarded (plus a write/DDL keyword
  blocklist and single-statement enforcement), `artisan` is allowlisted to read-only
  commands, and `config-get` redacts secret-looking keys.

## Connecting

### Claude Code

A project-scoped [`.mcp.json`](../../.mcp.json) is committed at the repo root, so running
`claude` from the project directory auto-discovers the `convoro-dev` server. `php` must be
on your `PATH` (Herd provides it on both macOS and Windows).

### Claude Desktop

Add to `claude_desktop_config.json`
(`%APPDATA%\Claude\` on Windows, `~/Library/Application Support/Claude/` on macOS):

```json
{
  "mcpServers": {
    "convoro-dev": {
      "command": "php",
      "args": ["artisan", "mcp:start", "convoro-dev"],
      "cwd": "/absolute/path/to/convoro"
    }
  }
}
```

### Debugging

```bash
php artisan mcp:inspector convoro-dev
```

Opens the MCP Inspector to browse tools and call them interactively.

## Adding a tool

```bash
php artisan make:mcp-tool MyThingTool
```

Then add a `#[Name('my-thing')]` and `#[Description(...)]`, implement `schema()` and
`handle()`, mark it `#[IsReadOnly]` if it does not mutate state, and register it in
`ConvoroDevServer::$tools`. Keep this server read-only — anything that writes belongs
behind an explicit, clearly-named, non-`IsReadOnly` tool with its own guards.
