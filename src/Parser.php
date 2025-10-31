<?php

declare(strict_types=1);

namespace Wwwision\Types;

use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use UnitEnum;
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

use function get_debug_type;
use function interface_exists;
use function is_a;
use function is_object;
use function is_subclass_of;
use function sprintf;

final class Parser
{
    /**
     * @var array<class-string<object>, ReflectionClass<object>|ReflectionEnum>
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
     * @param mixed $input
     * @return T
     */
    public static function instantiate(string $className, mixed $input): object
    {
        if (is_object($input) && is_a($input, $className)) {
            return $input;
        }
        $schema = self::getSchema($className);
        $instance = $schema->instantiate($input);
        Assert::isInstanceOf($instance, $className);
        return $instance;
    }

    /**
     * @param class-string $className
     * @return Schema
     */
    public static function getSchema(string $className): Schema
    {
        try {
            Assert::notEmpty($className, 'Failed to get schema for empty class name');
            if (array_key_exists($className, self::$currentlyParsing)) {
                return new DeferredSchema(fn() => self::getSchema($className));
            }
            self::$currentlyParsing[$className] = true;
            if (interface_exists($className)) {
                $interfaceReflection = self::reflectClass($className);
                $schema = self::getInterfaceSchema($interfaceReflection);
                return $schema;
            }
            Assert::classExists($className, 'Failed to get schema for class %s because that class does not exist');
            $reflectionClass = self::reflectClass($className);
            if (is_a($reflectionClass, ReflectionEnum::class, true)) {
                $caseSchemas = [];
                foreach ($reflectionClass->getCases() as $caseReflection) {
                    $caseSchemas[$caseReflection->getName()] = new EnumCaseSchema($caseReflection, self::getDescription($caseReflection));
                }
                /** @var Schema $schema */
                $schema = new EnumSchema($reflectionClass, self::getDescription($reflectionClass), $caseSchemas);
                return $schema;
            }
            $baseTypeAttributes = $reflectionClass->getAttributes(TypeBased::class, ReflectionAttribute::IS_INSTANCEOF);
            if ($baseTypeAttributes === []) {
                $schema = self::getShapeSchema($reflectionClass);
                return $schema;
            }
            Assert::keyExists($baseTypeAttributes, 0, sprintf('Missing BaseType attribute for class "%s"', $reflectionClass->getName()));
            Assert::count($baseTypeAttributes, 1, 'Expected exactly %d BaseType attribute for class "' . $reflectionClass->getName() . '", got %d');
            $baseTypeAttribute = $baseTypeAttributes[0]->newInstance();
            $schema = match ($baseTypeAttribute::class) {
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
            return $schema;
        } finally {
            unset(self::$currentlyParsing[$className]);
        }
    }

    /**
     * @param ReflectionParameter|ReflectionClass<object>|ReflectionClassConstant|ReflectionFunctionAbstract $reflection
     * @return string|null
     */
    private static function getDescription(ReflectionParameter|ReflectionClass|ReflectionClassConstant|ReflectionFunctionAbstract $reflection): string|null
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
            Assert::isInstanceOfAny($parameterType, [ReflectionNamedType::class, ReflectionUnionType::class]);
            /** @var ReflectionNamedType|ReflectionUnionType $parameterType */
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

    private static function reflectionTypeToSchema(ReflectionNamedType|ReflectionUnionType $reflectionType, string|null $description = null): Schema
    {
        if ($reflectionType instanceof ReflectionUnionType) {
            $subSchemas = array_map(static function (ReflectionType $subReflectionType) {
                Assert::isInstanceOfAny($subReflectionType, [ReflectionNamedType::class, ReflectionUnionType::class]);
                /** @var ReflectionNamedType|ReflectionUnionType $subReflectionType */
                return self::reflectionTypeToSchema($subReflectionType);
            }, $reflectionType->getTypes());
            return new OneOfSchema($subSchemas, $description, null);
        }
        if ($reflectionType->isBuiltin()) {
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
        $typeClassName = $reflectionType->getName();
        if (!class_exists($typeClassName) && !interface_exists($typeClassName)) {
            throw new InvalidArgumentException(sprintf('Expected an existing class or interface name, got %s', $typeClassName), 1733999133);
        }
        return self::getSchema($typeClassName);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return ReflectionClass<T>|ReflectionEnum
     */
    private static function reflectClass(string $className): ReflectionClass
    {
        if (!isset(self::$reflectionClassRuntimeCache[$className])) {
            try {
                self::$reflectionClassRuntimeCache[$className] = is_subclass_of($className, UnitEnum::class) ? new ReflectionEnum($className) : new ReflectionClass($className);
                // @codeCoverageIgnoreStart
            } catch (ReflectionException $e) {
                throw new RuntimeException(sprintf('Failed to reflect class "%s": %s', $className, $e->getMessage()), 1688570076, $e);
            }
            // @codeCoverageIgnoreEnd
        }
        /** @var ReflectionClass<T>|ReflectionEnum self::$reflectionClassRuntimeCache[$className] */
        return self::$reflectionClassRuntimeCache[$className];
    }
}
