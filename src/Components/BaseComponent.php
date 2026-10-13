<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Components;

use ReflectionClass;
use ReflectionProperty;

abstract class BaseComponent implements ComponentInterface
{
    public function toArray(): array
    {
        $result = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $value = $this->{$propertyName};

            if (is_object($value) && method_exists($value, 'toArray')) {
                $result[$propertyName] = $value->toArray();
            } else {
                $result[$propertyName] = $value;
            }
        }

        return array_filter($result, fn ($value) => $value !== null);
    }
}
