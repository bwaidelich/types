<?php

declare(strict_types=1);

namespace Wwwision\Types;

use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Webmozart\Assert\Assert;
use Wwwision\Types\Attributes\Description;
use Wwwision\Types\Attributes\Discriminator;
use Wwwision\Types\Attributes\FloatBased;
use Wwwision\Types\Attributes\Ignore;
use Wwwision\Types\Attributes\IntegerBased;
use Wwwision\Types\Attributes\ListBased;
use Wwwision\Types\Attributes\StringBased;
use Wwwision\Types\Attributes\TypeBased;
use Wwwision\Types\Schema\ArraySchema;
use Wwwision\Types\Schema\DeferredSchema;
use Wwwision\Types\Schema\EnumCaseSchema;
use Wwwision\Types\Schema\EnumSchema;
use Wwwision\Types\Schema\FloatSchema;
use Wwwision\Types\Schema\IntegerSchema;
use Wwwision\Types\Schema\InterfaceSchema;
use Wwwision\Types\Schema\ListSchema;
use Wwwision\Types\Schema\LiteralBooleanSchema;
use Wwwision\Types\Schema\LiteralFloatSchema;
use Wwwision\Types\Schema\LiteralIntegerSchema;
use Wwwision\Types\Schema\LiteralNullSchema;
use Wwwision\Types\Schema\LiteralStringSchema;
use Wwwision\Types\Schema\OneOfSchema;
use Wwwision\Types\Schema\OptionalSchema;
use Wwwision\Types\Schema\Schema;
use Wwwision\Types\Schema\ShapeSchema;
use Wwwision\Types\Schema\StringSchema;
use Wwwision\Types\Schema\Target\ClassTarget;

use function class_exists;
use function get_debug_type;
use function interface_exists;
use function is_a;
use function is_object;
use function sprintf;

final class Parser
{
    /**
     * @var array<class-string<object>, ReflectionClass<object>>
     */
    private static array $reflectionClassRuntimeCache = [];

    /**
     * @var array<class-string<object>, true>
     */
    private static array $currentlyParsing = [];

    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public static function instantiate(string $className, mixed $input, Options|null $options = null): object
    {
        if (is_object($input) && is_a($input, $className)) {
            return $input;
        }
        $schema = self::getSchema($className);
        $instance = $schema->instantiate($input, $options ?? Options::create());
        Assert::isInstanceOf($instance, $className);
        return $instance;
    }

    /**
     * @param class-string $className
     * @return Schema
     */
    public static function getSchema(string $className): Schema
    {
        Assert::notEmpty($className, 'Failed to get schema for empty class name');
        if (array_key_exists($className, self::$currentlyParsing)) {
            return new DeferredSchema(fn() => self::getSchema($className));
        }

        self::$currentlyParsing[$className] = true;
        try {
            if (interface_exists($className)) {
                $interfaceReflection = self::reflectClass($className);
                return self::getInterfaceSchema($interfaceReflection);
            }
            Assert::classExists($className, 'Failed to get schema for class %s because that class does not exist');
            $reflectionClass = self::reflectClass($className);

            if ($reflectionClass->isEnum()) {
                if (!is_a($className, \UnitEnum::class, true)) {
                    throw new InvalidArgumentException(sprintf('Expected enum class for "%s"', $className));
                }
                /** @var class-string<\UnitEnum> $className */
                $enumReflection = new ReflectionEnum($className);
                $caseSchemas = [];
                foreach ($enumReflection->getCases() as $caseReflection) {
                    $caseSchemas[$caseReflection->getName()] = new EnumCaseSchema(
                        $caseReflection,
                        self::getDescription($caseReflection),
                    );
                }
                return new EnumSchema(
                    $enumReflection,
                    self::getDescription($enumReflection),
                    $caseSchemas,
                );
            }

            $baseTypeAttributes = $reflectionClass->getAttributes(TypeBased::class, ReflectionAttribute::IS_INSTANCEOF);
            if ($baseTypeAttributes === []) {
                return self::getShapeSchema($reflectionClass);
            }
            Assert::keyExists($baseTypeAttributes, 0, sprintf('Missing BaseType attribute for class "%s"', $reflectionClass->getName()));
            Assert::count($baseTypeAttributes, 1, 'Expected exactly %d BaseType attribute for class "' . $reflectionClass->getName() . '", got %d');
            $baseTypeAttribute = $baseTypeAttributes[0]->newInstance();
            return match ($baseTypeAttribute::class) {
                StringBased::class => new StringSchema(new ClassTarget($reflectionClass), self::getDescription($reflectionClass), $baseTypeAttribute->minLength, $baseTypeAttribute->maxLength, $baseTypeAttribute->pattern, $baseTypeAttribute->format, $baseTypeAttribute->examples, $baseTypeAttribute->extensions),
                IntegerBased::class => new IntegerSchema(new ClassTarget($reflectionClass), self::getDescription($reflectionClass), $baseTypeAttribute->minimum, $baseTypeAttribute->maximum, $baseTypeAttribute->examples, $baseTypeAttribute->extensions),
                FloatBased::class => new FloatSchema(new ClassTarget($reflectionClass), self::getDescription($reflectionClass), $baseTypeAttribute->minimum, $baseTypeAttribute->maximum, $baseTypeAttribute->examples, $baseTypeAttribute->extensions),
                ListBased::class => new ListSchema(
                    new ClassTarget($reflectionClass),
                    self::getDescription($reflectionClass),
                    self::getSchema($baseTypeAttribute->itemClassName),
                    $baseTypeAttribute->minCount,
                    $baseTypeAttribute->maxCount,
                    $baseTypeAttribute->extensions,
                ),
                default => throw new InvalidArgumentException(sprintf('BaseType attribute for class "%s" has an invalid type of %s', $reflectionClass->getName(), get_debug_type($baseTypeAttribute)), 1688559710),
            };
        } finally {
            unset(self::$currentlyParsing[$className]);
        }
    }

    /**
     * @param ReflectionParameter|ReflectionProperty|ReflectionClass<object>|ReflectionClassConstant|ReflectionFunctionAbstract|ReflectionEnum<\UnitEnum> $reflection
     * @return string|null
     */
    private static function getDescription(ReflectionParameter|ReflectionProperty|ReflectionClass|ReflectionClassConstant|ReflectionFunctionAbstract|ReflectionEnum $reflection): string|null
    {
        $descriptionAttributes = $reflection->getAttributes(Description::class, ReflectionAttribute::IS_INSTANCEOF);
        if (!isset($descriptionAttributes[0])) {
            return null;
        }
        /** @var Description $instance */
        $instance = $descriptionAttributes[0]->newInstance();
        return $instance->value;
    }

    /**
     * @param ReflectionClass<object>|ReflectionParameter $reflection
     * @return Discriminator|null
     */
    private static function getDiscriminator(ReflectionClass|ReflectionParameter $reflection): Discriminator|null
    {
        $discriminatorAttributes = $reflection->getAttributes(Discriminator::class, ReflectionAttribute::IS_INSTANCEOF);
        if (!isset($discriminatorAttributes[0])) {
            return null;
        }
        /** @var Discriminator $instance */
        $instance = $discriminatorAttributes[0]->newInstance();
        return $instance;
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $reflectionClass
     */
    private static function getShapeSchema(ReflectionClass $reflectionClass): ShapeSchema
    {
        $constructor = $reflectionClass->getConstructor();
        Assert::isInstanceOf($constructor, ReflectionMethod::class, sprintf('Missing constructor in class "%s"', $reflectionClass->getName()));
        $propertySchemas = [];
        $overriddenPropertyDescriptions = [];
        $propertyDiscriminators = [];
        $propertyDefaults = [];
        foreach ($constructor->getParameters() as $parameter) {
            $propertyName = $parameter->getName();
            if ($parameter->isDefaultValueAvailable()) {
                $propertyDefaults[$propertyName] = $parameter->getDefaultValue();
            }
            $parameterType = $parameter->getType();
            Assert::notNull($parameterType, sprintf('Failed to determine type of constructor parameter "%s"', $propertyName));
            Assert::isInstanceOfAny($parameterType, [ReflectionNamedType::class, ReflectionUnionType::class, ReflectionIntersectionType::class]);
            /** @var ReflectionType $parameterType */
            try {
                $propertySchema = self::reflectionTypeToSchema($parameterType, self::getDescription($parameter));
            } catch (InvalidArgumentException $exception) {
                throw new InvalidArgumentException(sprintf('Failed to parse constructor argument "%s" of class "%s": %s', $propertyName, $reflectionClass->getShortName(), $exception->getMessage()), 1675172978, $exception);
            }
            $overwrittenDescription = self::getDescription($parameter);
            if ($overwrittenDescription !== null) {
                $overriddenPropertyDescriptions[$propertyName] = $overwrittenDescription;
            }
            $propertyDiscriminator = self::getDiscriminator($parameter);
            if ($propertyDiscriminator !== null) {
                $propertyDiscriminators[$propertyName] = $propertyDiscriminator;
                if ($propertySchema instanceof InterfaceSchema || $propertySchema instanceof OneOfSchema) {
                    $propertySchema = $propertySchema->withDiscriminator($propertyDiscriminator);
                }
            }
            if ($parameter->isOptional()) {
                $propertySchema = new OptionalSchema($propertySchema);
            } elseif ($parameter->allowsNull() && !$propertySchema instanceof OneOfSchema) {
                $propertySchema = new OneOfSchema([$propertySchema, new LiteralNullSchema(null)], null, null);
            }
            $propertySchemas[$propertyName] = $propertySchema;
        }
        return new ShapeSchema(new ClassTarget($reflectionClass), self::getDescription($reflectionClass), $propertySchemas, $overriddenPropertyDescriptions, $propertyDiscriminators, $propertyDefaults);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $interfaceReflection
     */
    private static function getInterfaceSchema(ReflectionClass $interfaceReflection): InterfaceSchema
    {
        $propertySchemas = [];
        $overriddenPropertyDescriptions = [];
        // 1. Schema properties declared via PHP property hooks (e.g. `public string $name { get; }`).
        foreach ($interfaceReflection->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->getAttributes(Ignore::class) !== []) {
                continue;
            }
            // Only readable properties (i.e. with a `get` hook) can be represented in the schema
            if (!array_key_exists('get', $reflectionProperty->getHooks())) {
                continue;
            }
            $propertyName = $reflectionProperty->getName();
            $propertyType = $reflectionProperty->getType();
            Assert::isInstanceOfAny($propertyType, [ReflectionNamedType::class, ReflectionUnionType::class], sprintf('Type of property "%s" of interface "%s" was expected to be of type %%2$s. Got: %%s', $propertyName, $interfaceReflection->getName()));
            /** @var ReflectionNamedType|ReflectionUnionType $propertyType */
            $propertySchema = self::reflectionTypeToSchema($propertyType);
            if ($propertyType->allowsNull()) {
                $propertySchema = new OptionalSchema($propertySchema);
            }
            $overwrittenDescription = self::getDescription($reflectionProperty);
            if ($overwrittenDescription !== null) {
                $overriddenPropertyDescriptions[$propertyName] = $overwrittenDescription;
            }
            $propertySchemas[$propertyName] = $propertySchema;
        }
        // 2. Schema properties declared via parameterless getter methods. Any method that does not qualify
        //    as a readable property (because it returns void, expects parameters, is static or has a return
        //    type that cannot be mapped to a schema) is silently skipped – it describes behavior, not data.
        foreach ($interfaceReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            $propertyName = $reflectionMethod->getName();
            // A property hook of the same name takes precedence over the method
            if (array_key_exists($propertyName, $propertySchemas)) {
                continue;
            }
            if ($reflectionMethod->getAttributes(Ignore::class) !== []) {
                continue;
            }
            if ($reflectionMethod->isStatic() || $reflectionMethod->getNumberOfParameters() !== 0) {
                continue;
            }
            $returnType = $reflectionMethod->getReturnType();
            if ($returnType === null || !self::isMappableType($returnType)) {
                continue;
            }
            $propertySchema = self::reflectionTypeToSchema($returnType);
            if ($returnType->allowsNull()) {
                $propertySchema = new OptionalSchema($propertySchema);
            }
            $overwrittenDescription = self::getDescription($reflectionMethod);
            if ($overwrittenDescription !== null) {
                $overriddenPropertyDescriptions[$propertyName] = $overwrittenDescription;
            }
            $propertySchemas[$propertyName] = $propertySchema;
        }
        $discriminator = self::getDiscriminator($interfaceReflection);
        return new InterfaceSchema($interfaceReflection, self::getDescription($interfaceReflection), $propertySchemas, $overriddenPropertyDescriptions, $discriminator);
    }

    /**
     * Determines whether the given type can be mapped to a {@see Schema} without resolving it.
     *
     * This is used to decide whether an interface method qualifies as a (readable) property: only methods
     * with a mappable return type are turned into properties, all others are treated as behavior and skipped.
     * The check intentionally only inspects the type itself (not the referenced classes) so that genuine
     * errors while building the referenced schema still surface instead of being swallowed.
     */
    private static function isMappableType(ReflectionType $reflectionType): bool
    {
        if ($reflectionType instanceof ReflectionUnionType) {
            foreach ($reflectionType->getTypes() as $subType) {
                if (!self::isMappableType($subType)) {
                    return false;
                }
            }
            return true;
        }
        if (!$reflectionType instanceof ReflectionNamedType) {
            // intersection types and any future reflection type are not supported
            return false;
        }
        if ($reflectionType->isBuiltin()) {
            return in_array($reflectionType->getName(), ['array', 'bool', 'float', 'int', 'string', 'null'], true);
        }
        return class_exists($reflectionType->getName()) || interface_exists($reflectionType->getName());
    }

    private static function reflectionTypeToSchema(ReflectionType $reflectionType, string|null $description = null): Schema
    {
        if ($reflectionType instanceof ReflectionUnionType) {
            $subSchemas = array_map(
                static function (ReflectionType $subReflectionType): Schema {
                    return self::reflectionTypeToSchema($subReflectionType);
                },
                $reflectionType->getTypes(),
            );
            return new OneOfSchema($subSchemas, $description, null);
        }

        if ($reflectionType instanceof ReflectionIntersectionType) {
            throw new InvalidArgumentException(sprintf('No support for intersection types (%s)', (string) $reflectionType));
        }

        if ($reflectionType instanceof ReflectionNamedType && $reflectionType->isBuiltin()) {
            return match ($reflectionType->getName()) {
                'array' => new ArraySchema($description),
                'bool' => new LiteralBooleanSchema($description),
                'float' => new LiteralFloatSchema($description),
                'int' => new LiteralIntegerSchema($description),
                'string' => new LiteralStringSchema($description),
                'null' => new LiteralNullSchema($description),
                default => throw new InvalidArgumentException(sprintf('No support for type %s', $reflectionType->getName())),
            };
        }

        if ($reflectionType instanceof ReflectionNamedType) {
            $typeClassName = $reflectionType->getName();
            if (!class_exists($typeClassName) && !interface_exists($typeClassName)) {
                throw new InvalidArgumentException(sprintf('Expected an existing class or interface name, got %s', $typeClassName), 1733999133);
            }
            return self::getSchema($typeClassName);
        }

        throw new InvalidArgumentException(sprintf('No support for reflection type %s', get_debug_type($reflectionType)));
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return ReflectionClass<T>
     */
    private static function reflectClass(string $className): ReflectionClass
    {
        if (!isset(self::$reflectionClassRuntimeCache[$className])) {
            self::$reflectionClassRuntimeCache[$className] = new ReflectionClass($className);
        }
        /** @var ReflectionClass<T> self::$reflectionClassRuntimeCache[$className] */
        return self::$reflectionClassRuntimeCache[$className];
    }
}
