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
use ReflectionType;
use ReflectionUnionType;
use Webmozart\Assert\Assert;
use Wwwision\Types\Attributes\Description;
use Wwwision\Types\Attributes\Discriminator;
use Wwwision\Types\Attributes\FloatBased;
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
                StringBased::class => new StringSchema($reflectionClass, self::getDescription($reflectionClass), $baseTypeAttribute->minLength, $baseTypeAttribute->maxLength, $baseTypeAttribute->pattern, $baseTypeAttribute->format, $baseTypeAttribute->examples),
                IntegerBased::class => new IntegerSchema($reflectionClass, self::getDescription($reflectionClass), $baseTypeAttribute->minimum, $baseTypeAttribute->maximum, $baseTypeAttribute->examples),
                FloatBased::class => new FloatSchema($reflectionClass, self::getDescription($reflectionClass), $baseTypeAttribute->minimum, $baseTypeAttribute->maximum, $baseTypeAttribute->examples),
                ListBased::class => new ListSchema(
                    $reflectionClass,
                    self::getDescription($reflectionClass),
                    self::getSchema($baseTypeAttribute->itemClassName),
                    $baseTypeAttribute->minCount,
                    $baseTypeAttribute->maxCount,
                ),
                default => throw new InvalidArgumentException(sprintf('BaseType attribute for class "%s" has an invalid type of %s', $reflectionClass->getName(), get_debug_type($baseTypeAttribute)), 1688559710),
            };
        } finally {
            unset(self::$currentlyParsing[$className]);
        }
    }

    /**
     * @param ReflectionParameter|ReflectionClass<object>|ReflectionClassConstant|ReflectionFunctionAbstract|ReflectionEnum<\UnitEnum> $reflection
     * @return string|null
     */
    private static function getDescription(ReflectionParameter|ReflectionClass|ReflectionClassConstant|ReflectionFunctionAbstract|ReflectionEnum $reflection): string|null
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
        foreach ($constructor->getParameters() as $parameter) {
            $propertyName = $parameter->getName();
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
        return new ShapeSchema($reflectionClass, self::getDescription($reflectionClass), $propertySchemas, $overriddenPropertyDescriptions, $propertyDiscriminators);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $interfaceReflection
     */
    private static function getInterfaceSchema(ReflectionClass $interfaceReflection): InterfaceSchema
    {
        $propertySchemas = [];
        $overriddenPropertyDescriptions = [];
        foreach ($interfaceReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            Assert::isEmpty($reflectionMethod->getParameters(), sprintf('Method "%s" of interface "%s" has at least one parameter, but this is currently not supported', $reflectionMethod->getName(), $interfaceReflection->getName()));
            $propertyName = $reflectionMethod->getName();
            $returnType = $reflectionMethod->getReturnType();
            Assert::notNull($returnType, sprintf('Return type of method "%s" of interface "%s" is missing', $reflectionMethod->getName(), $interfaceReflection->getName()));
            Assert::isInstanceOf($returnType, ReflectionNamedType::class, sprintf('Return type of method "%s" of interface "%s" was expected to be of type %%2$s. Got: %%s', $reflectionMethod->getName(), $interfaceReflection->getName()));
            /** @var ReflectionNamedType $returnType */
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
