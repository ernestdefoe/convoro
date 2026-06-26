<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('config-get')]
#[Description('Read a Laravel configuration value by dot-notation key, e.g. "app.name", "database.default", or a whole section like "mail". Secret-looking values (keys, passwords, tokens) are redacted.')]
#[IsReadOnly]
#[IsIdempotent]
class ConfigGetTool extends Tool
{
    protected string $secretPattern = '/(secret|password|passwd|token|api[_-]?key|\bkey\b|dsn|salt|cipher|private|credential)/i';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()
                ->description('Dot-notation config key, e.g. "app.name", "queue.default", or a section like "session".')
                ->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $key = trim((string) $request->get('key'));

        if ($key === '' || ! config()->has($key)) {
            return Response::error("Config key not found: {$key}");
        }

        $value = config($key);

        if (! is_array($value) && preg_match($this->secretPattern, $key)) {
            $value = '***REDACTED***';
        } else {
            $value = $this->redact($value);
        }

        return Response::text(json_encode([$key => $value], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && ! is_array($v) && ($v !== null && $v !== '') && preg_match($this->secretPattern, $k)) {
                $out[$k] = '***REDACTED***';
            } else {
                $out[$k] = $this->redact($v);
            }
        }

        return $out;
    }
}
