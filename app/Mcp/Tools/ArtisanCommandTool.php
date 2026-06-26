<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Name('artisan')]
#[Description('Run one of a small allowlist of non-destructive artisan commands and return its output. Allowed: about, env, route:list, migrate:status, queue:failed, queue:monitor, db:show, db:table, schedule:list, config:show, event:list, model:show.')]
#[IsReadOnly]
class ArtisanCommandTool extends Tool
{
    /** Only these read-only commands may be invoked. */
    protected array $allowed = [
        'about',
        'env',
        'route:list',
        'migrate:status',
        'queue:failed',
        'queue:monitor',
        'db:show',
        'db:table',
        'schedule:list',
        'config:show',
        'event:list',
        'model:show',
    ];

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()
                ->description('The artisan command name to run.')
                ->enum($this->allowed)
                ->required(),
            'arguments' => $schema->string()
                ->description('Optional space-separated arguments and options, e.g. "App\\Models\\Topic" or "--json".'),
        ];
    }

    public function handle(Request $request): Response
    {
        $command = trim((string) $request->get('command'));

        if (! in_array($command, $this->allowed, true)) {
            return Response::error('Command not allowed. Allowed commands: '.implode(', ', $this->allowed));
        }

        $args = $this->parseArgs($command, (string) ($request->get('arguments') ?? ''));

        try {
            $exit = Artisan::call($command, $args);
            $output = Artisan::output();
        } catch (Throwable $e) {
            return Response::error('Command failed: '.$e->getMessage());
        }

        $rendered = trim($args !== [] ? $command.' '.($request->get('arguments') ?? '') : $command);

        return Response::text("$ php artisan {$rendered}\n(exit code {$exit})\n\n".$output);
    }

    /**
     * Turn a free-form argument string into the array Artisan::call() expects,
     * mapping positional values onto the command's declared argument names.
     *
     * @return array<string, mixed>
     */
    protected function parseArgs(string $command, string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $positionalNames = [];
        $definitionCommand = Artisan::all()[$command] ?? null;
        if ($definitionCommand !== null) {
            $positionalNames = array_values(array_filter(
                array_keys($definitionCommand->getDefinition()->getArguments()),
                fn ($n) => $n !== 'command'
            ));
        }

        $parts = preg_split('/\s+/', $raw) ?: [];
        $args = [];
        $positionalIndex = 0;

        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                $option = substr($part, 2);
                if (str_contains($option, '=')) {
                    [$name, $value] = explode('=', $option, 2);
                    $args['--'.$name] = $value;
                } else {
                    $args['--'.$option] = true;
                }
            } elseif (isset($positionalNames[$positionalIndex])) {
                $args[$positionalNames[$positionalIndex]] = $part;
                $positionalIndex++;
            }
        }

        return $args;
    }
}
