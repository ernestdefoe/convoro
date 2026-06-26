<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-models')]
#[Description('List all Eloquent models defined in app/Models along with the database table each one maps to. Use describe-model for the full shape of a single model.')]
#[IsReadOnly]
#[IsIdempotent]
class ListModelsTool extends Tool
{
    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        $dir = app_path('Models');
        $models = [];

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
            $class = 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $instance = new $class;
            $models[] = sprintf('%-24s table=%s', class_basename($class), $instance->getTable());
        }

        if (empty($models)) {
            return Response::text('No Eloquent models found in app/Models.');
        }

        sort($models);

        return Response::text(count($models)." model(s):\n\n".implode("\n", $models));
    }
}
