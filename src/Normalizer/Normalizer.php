<?php

declare(strict_types=1);

namespace Wwwision\Types\Normalizer;

use BackedEnum;
use JsonException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Traversable;
use UnitEnum;
use Wwwision\Types\Attributes\Discriminator;
use Wwwision\Types\Attributes\ListBased;
use Wwwision\Types\Attributes\TypeBased;

final class Normalizer
{
    public function __construct(
        private bool $includeRootLevelTypeDiscrimination = true,
    ) {}

    public function normalize(object $object): mixed
    {
        $reflectionClass = new ReflectionClass($object);
        $result = $this->normalizeInternal($object, $reflectionClass);
        if ($this->includeRootLevelTypeDiscrimination && ($classDiscriminator = self::getClassDiscriminator($reflectionClass)) !== null) {
            $result = self::discriminateValue($result, $object::class, $classDiscriminator);
        }
        return $result;
    }

    /**
     * @template T of object
     * @param T $object
     * @param ReflectionClass<T> $reflectionClass
     */
    private function normalizeInternal(object $object, ReflectionClass $reflectionClass): mixed
    {
        if ($object instanceof BackedEnum) {
            return $object->value;
        }
        if ($object instanceof UnitEnum) {
            return $object->name;
        }
        if ($object instanceof Traversable) {
            $result = $this->normalizeIterable($object, $reflectionClass);
        } elseif (self::isTypeBased($reflectionClass)) {
            $properties = get_object_vars($object);
            $result = $properties[array_key_first($properties)];
        } else {
            $result = [];
            foreach (get_object_vars($object) as $propertyName => $propertyValue) {
                if (is_iterable($propertyValue)) {
                    $normalizedPropertyValue = is_object($propertyValue) ? $this->normalizeIterable($propertyValue, new ReflectionClass($propertyValue)) : $propertyValue;
                } elseif (is_object($propertyValue)) {
                    $normalizedPropertyValue = $this->normalizeInternal($propertyValue, new ReflectionClass($propertyValue));
                } else {
                    $normalizedPropertyValue = $propertyValue;
                }
                if (is_object($propertyValue) && ($propertyDiscriminator = self::getPropertyDiscriminator($reflectionClass, $propertyName)) !== null) {
                    $normalizedPropertyValue = self::discriminateValue($normalizedPropertyValue, $propertyValue::class, $propertyDiscriminator);
                }
                $result[$propertyName] = $normalizedPropertyValue;
            }
        }
        return $result;
    }

    public function toJson(object $object): string
    {
        try {
            return json_encode($this->normalize($object), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            // @codeCoverageIgnoreStart
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to JSON encode object of type "%s": %s', get_debug_type($object), $e->getMessage()), 1734598009, $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @template T of object
     * @param iterable<mixed> $iterable
     * @param ReflectionClass<T> $reflectionClass
     * @return array<mixed>
     */
    private function normalizeIterable(iterable $iterable, ReflectionClass $reflectionClass): array
    {
        $itemDiscriminator = null;
        /** @var ReflectionAttribute<ListBased>|null $listBasedReflectionAttribute */
        $listBasedReflectionAttribute = $reflectionClass->getAttributes(ListBased::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        if ($listBasedReflectionAttribute !== null) {
            $itemDiscriminator = self::getClassDiscriminator(new ReflectionClass($listBasedReflectionAttribute->newInstance()->itemClassName));
        }
        $result = [];
        foreach ($iterable as $key => $item) {
            if (is_object($item)) {
                $normalizedItem = $this->normalizeInternal($item, new ReflectionClass($item));
                if ($itemDiscriminator !== null) {
                    $normalizedItem = self::discriminateValue($normalizedItem, get_class($item), $itemDiscriminator);
                }
            } else {
                $normalizedItem = $item;
            }
            $result[$key] = $normalizedItem;
        }
        return $result;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    private static function isTypeBased(ReflectionClass $reflectionClass): bool
    {
        return $reflectionClass->getAttributes(TypeBased::class, \ReflectionAttribute::IS_INSTANCEOF) !== [];
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    private static function getClassDiscriminator(ReflectionClass $reflectionClass): Discriminator|null
    {
        $reflectionInterfaces = $reflectionClass->isInterface() ? [$reflectionClass] : $reflectionClass->getInterfaces();
        foreach ($reflectionInterfaces as $reflectionInterface) {
            /** @var array<ReflectionAttribute<Discriminator>> $discriminatorAttributes */
            $discriminatorAttributes = $reflectionInterface->getAttributes(Discriminator::class);
            if ($discriminatorAttributes === []) {
                continue;
            }
            return $discriminatorAttributes[0]->newInstance();
        }
        if ($reflectionClass->isInterface()) {
            return new Discriminator('__type');
        }
        return null;
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    private static function getPropertyDiscriminator(ReflectionClass $reflectionClass, string $propertyName): Discriminator|null
    {
        $propertyReflection = $reflectionClass->getProperty($propertyName);
        /** @var array<ReflectionAttribute<Discriminator>> $discriminatorAttributes */
        $discriminatorAttributes = $propertyReflection->getAttributes(Discriminator::class);
        if ($discriminatorAttributes === []) {
            $propertyType = $propertyReflection->getType();
            if ($propertyType instanceof ReflectionNamedType && interface_exists($propertyType->getName())) {
                return self::getClassDiscriminator(new ReflectionClass($propertyType->getName()));
            }
            return null;
        }
        return $discriminatorAttributes[0]->newInstance();
    }

    /**
     * @param class-string $valueClassName
     */
    private static function discriminateValue(mixed $value, string $valueClassName, Discriminator $discriminator): mixed
    {
        if (!is_array($value)) {
            $value = ['__value' => $value];
        }
        return [$discriminator->propertyName => self::getDiscriminatorValueForClassName($valueClassName, $discriminator->mapping), ...$value];
    }

    /**
     * @param class-string $className
     * @param array<non-empty-string, class-string>|null $mapping
     */
    private static function getDiscriminatorValueForClassName(string $className, array|null $mapping): string|null
    {
        if ($mapping === null) {
            return $className;
        }
        foreach ($mapping as $mappingValue => $mappingClassName) {
            if ($className === $mappingClassName) {
                return $mappingValue;
            }
        }
        return null;
    }

}
