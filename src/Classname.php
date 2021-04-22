<?php

namespace Blueprint;

use Blueprint\Models\Model;
use Blueprint\Tree;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

class Classname extends Stringable
{
    public function isFullyQualifiedName()
    {
        return $this->startsWith('\\');
    }

    public function toRelationshipMethodName()
    {
        return $this->isFullyQualifiedName() ? (string)$this->afterLast('\\') : (string)$this->beforeLast('_id');
    }

    public function toRelationshipCode($type, Tree $tree, Model $model)
    {
        $is_model_fqn = $this->isFullyQualifiedName();

        $key = null;
        $class = null;

        $column_name = $this->value;
        $method_name = $this->toRelationshipMethodName();

        if (Str::contains($this->value, ':')) {
            [$foreign_reference, $column_name] = explode(':', $this->value);

            $method_name = $is_model_fqn ? Str::afterLast($foreign_reference, '\\') : Str::beforeLast($column_name, '_id');

            if (Str::contains($foreign_reference, '.')) {
                [$class, $key] = explode('.', $foreign_reference);

                if ($key === 'id') {
                    $key = null;
                } else {
                    $method_name = $is_model_fqn ? Str::lower(Str::afterLast($class, '\\')) : Str::lower($class);
                }
            } else {
                $class = $foreign_reference;
            }
        }

        if ($is_model_fqn) {
            $fqcn = $class ?? $column_name;
            $class_name = Str::afterLast($fqcn, '\\');
        } else {
            $class_name = Str::studly($class ?? $method_name);
            $fqcn = $this->fullyQualifyModelReference($class_name, $tree, $model) ?? $model->fullyQualifiedNamespace() . '\\' . $class_name;
        }

        $fqcn = Str::startsWith($fqcn, '\\') ? $fqcn : '\\'.$fqcn;

        if ($type === 'morphTo') {
            $relationship = sprintf('$this->%s()', $type);
        } elseif ($type === 'morphMany' || $type === 'morphOne') {
            $relation = Str::lower(Str::singular($column_name)) . 'able';
            $relationship = sprintf('$this->%s(%s::class, \'%s\')', $type, $fqcn, $relation);
        } elseif (!is_null($key)) {
            $relationship = sprintf('$this->%s(%s::class, \'%s\', \'%s\')', $type, $fqcn, $column_name, $key);
        } elseif (!is_null($class) && $type === 'belongsToMany') {
            $relationship = sprintf('$this->%s(%s::class, \'%s\')', $type, $fqcn, $column_name);
            $column_name = $class;
        } else {
            $relationship = sprintf('$this->%s(%s::class)', $type, $fqcn);
        }

        if ($type === 'morphTo') {
            $method_name = Str::lower($class_name);
        } elseif (in_array($type, ['hasMany', 'belongsToMany', 'morphMany'])) {
            $method_name = Str::plural($is_model_fqn ? Str::afterLast($column_name, '\\') : $column_name);
        }

        return [$method_name, $relationship];
    }

    private function fullyQualifyModelReference(string $model_name, Tree $tree, Model $model)
    {
        // TODO: get model_name from tree.
        // If not found, assume parallel namespace as controller.
        // Use respond-statement.php as test case.

        /** @var \Blueprint\Models\Model $model */
        $model = $tree->modelForContext($model_name);

        if (isset($model)) {
            return $model->fullyQualifiedClassName();
        }

        return null;
    }
}
