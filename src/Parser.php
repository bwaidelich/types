<?php

declare(strict_types=1);

namespace Wwwision\Types;

use Exception;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use UnitEnum;
use Webmozart\Assert\Assert;
use Wwwision\Types\Attributes\Description;
use Wwwision\Types\Attributes\IntegerBased;
use Wwwision\Types\Attributes\ListBased;
use Wwwision\Types\Attributes\StringBased;
use Wwwision\Types\Attributes\TypeBased;
use Wwwision\Types\Schema\EnumCaseSchema;
use Wwwision\Types\Schema\EnumSchema;
use Wwwision\Types\Schema\IntegerSchema;
use Wwwision\Types\Schema\ListSchema;
use Wwwision\Types\Schema\LiteralBooleanSchema;
use Wwwision\Types\Schema\LiteralIntegerSchema;
use Wwwision\Types\Schema\LiteralStringSchema;
use Wwwision\Types\Schema\OptionalSchema;
use Wwwision\Types\Schema\Schema;
use Wwwision\Types\Schema\ShapeSchema;
use Wwwision\Types\Schema\StringSchema;

use function get_debug_type;
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
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

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
        try {
            $instance = $schema->instantiate($input);
        } catch (Exception $exception) {
            throw new InvalidArgumentException(sprintf('Failed to instantiate %s: %s', $schema->getName(), $exception->getMessage()), 1688582285, $exception);
        }
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
        Assert::classExists($className, 'Failed to get schema for class "%s" because that class does not exist');
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
            return self::getShapeSchema($reflectionClass);
        }
        Assert::keyExists($baseTypeAttributes, 0, sprintf('Missing BaseType attribute for class "%s"', $reflectionClass->getName()));
        Assert::count($baseTypeAttributes, 1, 'Expected exactly %d BaseType attribute for class "' . $reflectionClass->getName() . '", got %d');
        $baseTypeAttribute = $baseTypeAttributes[0]->newInstance();
        return match ($baseTypeAttribute::class) {
            StringBased::class => new StringSchema($reflectionClass, self::getDescription($reflectionClass), $baseTypeAttribute->minLength, $baseTypeAttribute->maxLength, $baseTypeAttribute->pattern, $baseTypeAttribute->format),
            IntegerBased::class => new IntegerSchema($reflectionClass, self::getDescription($reflectionClass), $baseTypeAttribute->minimum, $baseTypeAttribute->maximum),
            ListBased::class => new ListSchema(
                $reflectionClass,
                self::getDescription($reflectionClass),
                self::getSchema($baseTypeAttribute->itemClassName),
                $baseTypeAttribute->minCount,
                $baseTypeAttribute->maxCount
            ),
            default => throw new InvalidArgumentException(sprintf('BaseType attribute for class "%s" has an invalid type of %s', $reflectionClass->getName(), get_debug_type($baseTypeAttribute)), 1688559710),
        };
    }

    /**
     * @param ReflectionParameter|ReflectionClass<object>|ReflectionClassConstant $reflection
     * @return string|null
     */
    private static function getDescription(ReflectionParameter|ReflectionClass|ReflectionClassConstant $reflection): ?string
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
     * @template T of object
     * @param ReflectionClass<T> $reflectionClass
     * @return Schema
     */
    private static function getShapeSchema(ReflectionClass $reflectionClass): Schema
    {
        $constructor = $reflectionClass->getConstructor();
        Assert::isInstanceOf($constructor, ReflectionMethod::class, sprintf('Missing constructor in class "%s"', $reflectionClass->getName()));
        $propertySchemas = [];
        $overriddenPropertyDescriptions = [];
        foreach ($constructor->getParameters() as $parameter) {
            $propertyName = $parameter->getName();
            $parameterType = $parameter->getType();
            Assert::notNull($parameterType, sprintf('Failed to determine type of constructor parameter "%s"', $propertyName));
            Assert::isInstanceOf($parameterType, ReflectionNamedType::class);

            if ($parameterType->isBuiltin()) {
                $propertySchema = match ($parameterType->getName()) {
                    'bool' => new LiteralBooleanSchema(self::getDescription($parameter)),
                    'int' => new LiteralIntegerSchema(self::getDescription($parameter)),
                    'string' => new LiteralStringSchema(self::getDescription($parameter)),
                    default => throw new InvalidArgumentException(sprintf('No support for constructor parameter "%s" of type %s', $propertyName, $parameter->getType())),
                };
            } else {
                /** @var class-string<T> $parameterTypeClassName */
                $parameterTypeClassName = $parameterType->getName();
                Assert::classExists($parameterTypeClassName);
                try {
                    $propertySchema = self::getSchema($parameterTypeClassName);
                } catch (InvalidArgumentException $exception) {
                    throw new InvalidArgumentException(sprintf('Failed to determine base type for "%s": %s', $propertyName, $exception->getMessage()), 1675172978, $exception);
                }
            }
            if ($parameter->isOptional()) {
                $propertySchema = new OptionalSchema($propertySchema);
            }
            $overwrittenDescription = self::getDescription($parameter);
            if ($overwrittenDescription !== null) {
                $overriddenPropertyDescriptions[$propertyName] = $overwrittenDescription;
            }
            $propertySchemas[$propertyName] = $propertySchema;
        }
        return new ShapeSchema($reflectionClass, self::getDescription($reflectionClass), $propertySchemas, $overriddenPropertyDescriptions);
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
