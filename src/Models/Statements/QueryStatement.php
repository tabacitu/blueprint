<?php

namespace Blueprint\Models\Statements;

use Illuminate\Support\Str;

class QueryStatement
{
    /**
     * @var string
     */
    private $operation;

    /**
     * @var array
     */
    private $clauses;

    /**
     * @var string
     */
    private $model = null;

    public function __construct(string $operation, array $clauses = [])
    {
        $this->operation = $operation;
        $this->clauses = $clauses;

        if ($operation === 'all' && !empty($clauses)) {
            $this->model = Str::studly(Str::singular($clauses[0]));
        }
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function clauses()
    {
        return $this->clauses;
    }

    public function output(string $controller): string
    {
        $model = $this->determineModel($controller);

        if ($this->operation() === 'all') {
            if (is_null($this->model())) {
                return '$' . Str::camel(Str::plural($model)) . ' = ' . $model . '::all();';
            } else {
                return '$' . Str::camel($this->clauses()[0]) . ' = ' . $this->model() . '::all();';
            }
        }

        $methods = [];
        foreach ($this->clauses as $clause) {
            [$method, $argument] = explode(':', $clause);

            if (in_array($method, ['where', 'order', 'pluck'])) {
                $column = $this->columnName($model, $argument);
            }

            if ($method === 'where') {
                $methods[] = $method . '(' . "'{$column}', $" . str_replace('.', '->', $argument) . ')';
            } elseif ($method === 'pluck') {
                $pluck_field = $argument;
                $methods[] = "pluck('{$column}')";
            } elseif ($method === 'order') {
                $methods[] = "orderBy('{$column}')";
            } else {
                $methods[] = $method . '(' . $argument . ')';
            }
        }

        if ($this->operation() === 'pluck') {
            $variable_name = $this->pluckName($pluck_field);
        } elseif ($this->operation() === 'count') {
            $variable_name = Str::camel($model) . '_count';
        } else {
            $variable_name = Str::camel(Str::plural($model));
        }

        $code = '$' . $variable_name . ' = ' . $model . '::';

        $code .= implode('->', $methods);

        if (in_array($this->operation(), ['get', 'count'])) {
            $code .= '->' . $this->operation() . '()';
        }

        $code .= ';';

        return $code;
    }

    private function columnName($model, $value)
    {
        if (Str::contains($value, '.')) {
            $reference = Str::before($value, '.');
            if (strcasecmp($model, $reference) === 0) {
                return Str::after($value, '.');
            }
        }

        return $value;
    }

    private function pluckName(string $field)
    {
        if (Str::contains($field, '.')) {
            return Str::lower(Str::plural(str_replace('.', '_', $field)));
        }

        return Str::lower('Post' . '_' . Str::plural($field));
    }

    private function determineModel(string $prefix)
    {
        if (empty($this->model())) {
            return Str::studly(Str::singular($prefix));
        }

        return Str::studly($this->model());
    }
}
