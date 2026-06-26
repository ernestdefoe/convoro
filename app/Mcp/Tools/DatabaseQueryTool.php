<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Name('db-query')]
#[Description('Run a single read-only SQL SELECT query against the Convoro database and return the rows as JSON. Only SELECT and WITH...SELECT statements are accepted; any write or schema statement is rejected. A LIMIT is added automatically when missing.')]
#[IsReadOnly]
class DatabaseQueryTool extends Tool
{
    /** Keywords that must never appear in a query passed to this tool. */
    protected array $forbidden = [
        'insert', 'update', 'delete', 'drop', 'truncate', 'alter', 'create',
        'replace', 'grant', 'revoke', 'attach', 'detach', 'pragma', 'into',
        'call', 'merge', 'upsert', 'vacuum',
    ];

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('A single SQL SELECT statement. Inline literal values directly; no parameter binding is performed.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum rows to return (default 100, max 1000). Ignored when the query already has a LIMIT.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $sql = trim((string) $request->get('query'));
        $limit = min(max((int) ($request->get('limit') ?? 100), 1), 1000);

        if ($sql === '') {
            return Response::error('No query provided.');
        }

        // Must start with SELECT or WITH (CTE) — stripping any leading parens/whitespace.
        $normalized = strtolower(ltrim($sql, " \t\n\r("));
        if (! str_starts_with($normalized, 'select') && ! str_starts_with($normalized, 'with')) {
            return Response::error('Only SELECT (or WITH ... SELECT) queries are allowed.');
        }

        // Reject multiple statements.
        if (str_contains(rtrim($sql, "; \t\n\r"), ';')) {
            return Response::error('Only a single statement is allowed (no semicolons mid-query).');
        }

        // Defense-in-depth: block write/DDL keywords even inside a "select".
        foreach ($this->forbidden as $kw) {
            if (preg_match('/\b'.$kw.'\b/i', $sql)) {
                return Response::error("Disallowed keyword in query: {$kw}.");
            }
        }

        // Append a LIMIT when the author did not specify one.
        if (! preg_match('/\blimit\b/i', $sql)) {
            $sql = rtrim($sql, "; \t\n\r").' limit '.$limit;
        }

        try {
            $rows = DB::select($sql);
        } catch (Throwable $e) {
            return Response::error('Query failed: '.$e->getMessage());
        }

        $rows = array_map(fn ($row) => (array) $row, $rows);

        $payload = [
            'connection' => DB::getDefaultConnection(),
            'row_count' => count($rows),
            'rows' => $rows,
        ];

        return Response::text(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
