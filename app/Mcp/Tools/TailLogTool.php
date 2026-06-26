<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('tail-log')]
#[Description('Return the last N lines of a log file under storage/logs (default laravel.log). Useful for inspecting recent errors and stack traces without leaving the editor.')]
#[IsReadOnly]
class TailLogTool extends Tool
{
    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lines' => $schema->integer()
                ->description('Number of trailing lines to return (default 100, max 2000).'),
            'file' => $schema->string()
                ->description('Log filename under storage/logs (default "laravel.log"). Path components are stripped.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $lines = min(max((int) ($request->get('lines') ?? 100), 1), 2000);
        $file = basename((string) ($request->get('file') ?: 'laravel.log'));
        $path = storage_path('logs/'.$file);

        if (! is_file($path)) {
            return Response::error("Log file not found: {$file}");
        }

        $content = $this->tail($path, $lines);

        if (trim($content) === '') {
            return Response::text("({$file} is empty)");
        }

        return Response::text("Last {$lines} line(s) of {$file}:\n\n".$content);
    }

    protected function tail(string $path, int $lines): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        $buffer = '';
        $chunkSize = 8192;
        $position = filesize($path);

        while ($position > 0 && substr_count($buffer, "\n") <= $lines) {
            $read = (int) min($chunkSize, $position);
            $position -= $read;
            fseek($handle, $position);
            $buffer = fread($handle, $read).$buffer;
        }

        fclose($handle);

        $all = explode("\n", rtrim($buffer, "\n"));

        return implode("\n", array_slice($all, -$lines));
    }
}
