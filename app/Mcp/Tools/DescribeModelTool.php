<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

#[Name('describe-model')]
#[Description('Describe a single Eloquent model: its table, columns (with types), casts, fillable/guarded attributes, and its relationships. Pass a short name like "Topic" or a fully-qualified class.')]
#[IsReadOnly]
#[IsIdempotent]
class DescribeModelTool extends Tool
{
    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()
                ->description('Model class name, e.g. "Topic" or "App\\Models\\Topic".')
                ->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $name = trim((string) $request->get('model'));
        $class = str_contains($name, '\\') ? ltrim($name, '\\') : 'App\\Models\\'.$name;

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return Response::error("Unknown model: {$name}. Use list-models to see what is available.");
        }

        /** @var Model $model */
        $model = new $class;
        $table = $model->getTable();

        $out = [];
        $out[] = "Model:   {$class}";
        $out[] = "Table:   {$table}";
        $out[] = 'Key:     '.$model->getKeyName().' ('.$model->getKeyType().')';
        $out[] = '';

        $out[] = 'Columns:';
        $out[] = $this->columns($table);
        $out[] = '';

        $casts = $model->getCasts();
        $out[] = 'Casts:';
        $out[] = empty($casts)
            ? '  (none)'
            : implode("\n", array_map(fn ($k, $v) => "  {$k} => {$v}", array_keys($casts), array_values($casts)));
        $out[] = '';

        $guarded = $model->getGuarded();
        $fillable = $model->getFillable();
        $out[] = 'Mass assignment:';
        $out[] = $guarded === ['*'] || $guarded === []
            ? '  guarded: ['.implode(', ', $guarded).']'.($guarded === [] ? '  (all attributes fillable)' : '')
            : '  guarded: ['.implode(', ', $guarded).']';
        if (! empty($fillable)) {
            $out[] = '  fillable: ['.implode(', ', $fillable).']';
        }
        $out[] = '';

        $relations = $this->relations($class, $model);
        $out[] = 'Relations:';
        $out[] = empty($relations) ? '  (none declared with a typed return)' : implode("\n", $relations);

        return Response::text(implode("\n", $out));
    }

    protected function columns(string $table): string
    {
        try {
            $columns = Schema::getColumns($table);
        } catch (Throwable $e) {
            // Driver may not support full column introspection; fall back to names.
            try {
                $names = Schema::getColumnListing($table);

                return empty($names) ? '  (table not found)' : implode("\n", array_map(fn ($c) => '  '.$c, $names));
            } catch (Throwable $inner) {
                // Database unreachable entirely (e.g. credentials not configured).
                return '  (unable to read columns: '.$inner->getMessage().')';
            }
        }

        if (empty($columns)) {
            return '  (table not found)';
        }

        return implode("\n", array_map(function (array $c) {
            $flags = [];
            if (! empty($c['nullable'])) {
                $flags[] = 'nullable';
            }
            if (! empty($c['auto_increment'])) {
                $flags[] = 'auto_increment';
            }
            if (($c['default'] ?? null) !== null) {
                $flags[] = 'default='.$c['default'];
            }

            return sprintf('  %-26s %s%s', $c['name'], $c['type'], $flags ? '  ['.implode(', ', $flags).']' : '');
        }, $columns));
    }

    /**
     * @return array<int, string>
     */
    protected function relations(string $class, Model $model): array
    {
        $relations = [];
        $ref = new ReflectionClass($class);

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $class || $method->getNumberOfParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();
            if (! $returnType instanceof ReflectionNamedType
                || ! is_subclass_of($returnType->getName(), Relation::class)) {
                continue;
            }

            $kind = class_basename($returnType->getName());

            try {
                $related = class_basename(get_class($model->{$method->getName()}()->getRelated()));
                $relations[] = sprintf('  %-20s %s -> %s', $method->getName(), $kind, $related);
            } catch (Throwable $e) {
                $relations[] = sprintf('  %-20s %s', $method->getName(), $kind);
            }
        }

        sort($relations);

        return $relations;
    }
}
