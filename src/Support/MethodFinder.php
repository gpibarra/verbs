<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class MethodFinder
{
    protected object $reflect;

    protected ?string $prefix = null;

    protected ?Collection $types = null;

    public static function for(object|string $object_or_class): static
    {
        return new static($object_or_class);
    }

    public function __construct(object|string $object_or_class)
    {
        $this->reflect = new ReflectionClass($object_or_class);
    }

    public function prefixed(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function expecting(string|array $types): static
    {
        $this->types = Collection::make(Arr::wrap($types));

        return $this;
    }

    /** @return Collection<int, ReflectionMethod> */
    public function find(): Collection
    {
        return collect($this->reflect->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter($this->matchesPrefix(...))
            ->filter($this->expectsParameters(...));
    }

    /**
     * @template TMapValue
     *
     * @param  callable(ReflectionMethod, int): TMapValue  $callback
     * @return Collection<int, TMapValue>
     */
    public function map(callable $callback): Collection
    {
        return $this->find()->map($callback);
    }

    protected function matchesPrefix(ReflectionMethod $method): bool
    {
        return $this->prefix === null || Str::startsWith($method->getName(), $this->prefix);
    }

    protected function expectsParameters(ReflectionMethod $method): bool
    {
        if (count($this->types) === 0) {
            return true;
        }

        foreach ($method->getParameters() as $parameter) {
            $expected = collect(Reflector::getParameterClassNames($parameter));
            $matches = $expected->intersect($this->types)->count();

            if ($matches < $expected->count() || $matches < $this->types->count()) {
                return false;
            }
        }

        return true;
    }
}
