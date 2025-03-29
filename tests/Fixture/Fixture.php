<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Wwwision\Types\Tests\Fixture;

use ArrayIterator;
use DateTimeImmutable;
use IteratorAggregate;
use JsonSerializable;
use stdClass;
use Traversable;
use Wwwision\Types\Attributes\Description;
use Wwwision\Types\Attributes\Discriminator;
use Wwwision\Types\Attributes\FloatBased;
use Wwwision\Types\Attributes\IntegerBased;
use Wwwision\Types\Attributes\ListBased;
use Wwwision\Types\Attributes\StringBased;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Parser;
use Wwwision\Types\Schema\StringTypeFormat;

use function Wwwision\Types\instantiate;

#[StringBased(minLength: 3, maxLength: 20)]
#[Description('First name of a person')]
final class GivenName implements SomeInterface
{
    private function __construct(public readonly string $value) {}

    public function someMethod(): string
    {
        return 'bar';
    }

    public function someOtherMethod(): FamilyName
    {
        return instantiate(FamilyName::class, $this->value);
    }
}

#[StringBased(minLength: 3, maxLength: 20)]
#[Description('Last name of a person')]
final class FamilyName implements SomeInterface
{
    private function __construct(public readonly string $value) {}

    public function someMethod(): string
    {
        return 'bar';
    }

    public function someOtherMethod(): FamilyName
    {
        return instantiate(self::class, $this->value);
    }
}

#[IntegerBased(minimum: 1, maximum: 120)]
#[Description('The age of a person in years')]
final class Age
{
    private function __construct(public readonly int $value) {}
}


#[Description('First and last name of a person')]
final class FullName implements SomeInterface
{
    public function __construct(
        #[Description('Overridden given name description')]
        public readonly GivenName $givenName,
        public readonly FamilyName $familyName,
    ) {}

    public function someMethod(): string
    {
        return 'baz';
    }

    public function someOtherMethod(): FamilyName
    {
        return $this->familyName;
    }
}

/** @implements IteratorAggregate<FullName> */
#[ListBased(itemClassName: FullName::class, minCount: 2, maxCount: 5)]
final class FullNames implements IteratorAggregate
{
    /**
     * @var array<FullName>
     */
    private array $fullNames;

    private function __construct(FullName... $fullNames)
    {
        $this->fullNames = $fullNames;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->fullNames);
    }
}

/**
 * @implements IteratorAggregate<GivenName>
 */
#[ListBased(itemClassName: GivenName::class, maxCount: 4)]
final class GivenNames implements IteratorAggregate
{
    /** @param array<GivenName> $givenNames */
    private function __construct(private readonly array $givenNames) {}

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->givenNames);
    }
}

/**
 * @implements IteratorAggregate<Uri>
 */
#[ListBased(itemClassName: Uri::class)]
final class UriMap implements IteratorAggregate
{
    /**
     * @param array<Uri> $entries
     */
    private function __construct(private readonly array $entries)
    {
        if (array_keys($entries) !== array_filter(\array_keys($entries), '\is_string')) {
            throw CoerceException::custom('Expected associative array with string keys', $entries, Parser::getSchema(self::class), );
        }
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entries);
    }
}

#[StringBased(pattern: '^(?!magic).*')]
final class NotMagic
{
    private function __construct(public readonly string $value) {}
}

#[StringBased(format: StringTypeFormat::email)]
final class EmailAddress
{
    private function __construct(public readonly string $value) {}
}

#[StringBased(format: StringTypeFormat::idn_email)]
final class IdnEmailAddress
{
    private function __construct(public readonly string $value) {}
}

#[StringBased(format: StringTypeFormat::hostname)]
final class Hostname
{
    private function __construct(public readonly string $value) {}
}

#[StringBased(format: StringTypeFormat::ipv4)]
final class Ipv4
{
    private function __construct(public readonly string $value) {}
}

#[StringBased(format: StringTypeFormat::ipv6)]
final class Ipv6
{
    private function __construct(public readonly string $value) {}
}

#[StringBased(format: StringTypeFormat::regex)]
final class Regex
{
    private function __construct(public readonly string $value) {}
}

#[StringBased(format: StringTypeFormat::uri)]
final class Uri
{
    private function __construct(public readonly string $value) {}
}

#[StringBased(format: StringTypeFormat::date)]
final class Date
{
    private function __construct(public readonly string $value)
    {
        $now = new DateTimeImmutable();
        if (DateTimeImmutable::createFromFormat('Y-m-d', $this->value) > $now) {
            throw CoerceException::custom('Future dates are not allowed', $value, Parser::getSchema(self::class), ['some' => 'param']);
        }
    }
}

#[StringBased(format: StringTypeFormat::time)]
final class Time
{
    private function __construct(public readonly string $value) {}
}

#[StringBased(format: StringTypeFormat::date_time)]
final class DateTime
{
    private function __construct(public readonly string $value) {}
}

#[StringBased(format: StringTypeFormat::duration)]
final class Duration
{
    private function __construct(public readonly string $value) {}
}

#[StringBased(format: StringTypeFormat::uuid)]
final class Uuid
{
    private function __construct(public readonly string $value) {}
}

#[Description('honorific title of a person')]
enum Title
{
    #[Description('for men, regardless of marital status, who do not have another professional or academic title')]
    case MR;
    #[Description('for married women who do not have another professional or academic title')]
    case MRS;
    #[Description('for girls, unmarried women and married women who continue to use their maiden name')]
    case MISS;
    #[Description('for women, regardless of marital status or when marital status is unknown')]
    case MS;
    #[Description('for any other title that does not match the above')]
    case OTHER;
}

#[Description('A number')]
enum Number: int
{
    #[Description('The number 1')]
    case ONE = 1;
    case TWO = 2;
    case THREE = 3;
}

enum RomanNumber: string
{
    case I = '1';
    #[Description('random description')]
    case II = '2';
    case III = '3';
    case IV = '4';
}

final class NestedShape
{
    public function __construct(
        public readonly ShapeWithOptionalTypes $shapeWithOptionalTypes,
        public readonly ShapeWithBool $shapeWithBool,
    ) {}
}

final class ShapeWithOptionalTypes
{
    public function __construct(
        public readonly FamilyName $stringBased,
        public readonly string|null $stringOrNull,
        public readonly GivenNames|null $givenNamesOrNull,
        public readonly ?FamilyName $optionalStringBased = null,
        #[Description('Some description')]
        public readonly ?int $optionalInt = null,
        public readonly ?bool $optionalBool = null,
        public readonly ?string $optionalString = null,
        public readonly string $stringWithDefaultValue = 'default',
        public readonly bool $boolWithDefault = false,
    ) {}
}

final class ShapeWithInvalidObjectProperty
{
    public function __construct(
        public readonly stdClass $someProperty,
    ) {}
}

final class ShapeWithBool
{
    private function __construct(
        #[Description('Description for literal bool')]
        public readonly bool $value,
    ) {}
}

final class ShapeWithInt
{
    private function __construct(
        #[Description('Description for literal int')]
        public readonly int $value,
    ) {}
}

final class ShapeWithString
{
    private function __construct(
        #[Description('Description for literal string')]
        public readonly string $value,
    ) {}
}

final class ShapeWithFloat
{
    private function __construct(
        #[Description('Description for literal float')]
        public readonly float $value,
    ) {}
}

#[Description('SomeInterface description')]
interface SomeInterface
{
    #[Description('Custom description for "someMethod"')]
    public function someMethod(): string;
    #[Description('Custom description for "someOtherMethod"')]
    public function someOtherMethod(): ?FamilyName;
}


#[FloatBased(minimum: -180.0, maximum: 180.5)]
final class Longitude
{
    private function __construct(
        public readonly float $value,
    ) {}
}

#[FloatBased(minimum: -90, maximum: 90)]
final class Latitude
{
    private function __construct(
        public readonly float $value,
    ) {}
}

final class GeoCoordinates
{
    public function __construct(
        public readonly Longitude $longitude,
        public readonly Latitude $latitude,
    ) {}
}


interface SomeInvalidInterface
{
    public function methodWithParameters(string|null $param = null): string;
}

#[StringBased(minLength: 10, maxLength: 2, pattern: '^foo$', format: StringTypeFormat::email)]
final class ImpossibleString
{
    private function __construct(public readonly string $value) {}
}

#[IntegerBased(minimum: 10, maximum: 2)]
final class ImpossibleInt
{
    private function __construct(public readonly string $value) {}
}

#[FloatBased(minimum: 10.23, maximum: 2.45)]
final class ImpossibleFloat
{
    private function __construct(public readonly string $value) {}
}

#[ListBased(itemClassName: GivenName::class, minCount: 10, maxCount: 2)]
final class ImpossibleList
{
    /**
     * @param array<GivenName> $items
     */
    private function __construct(public readonly array $items) {}
}

final class ShapeWithArray
{
    /**
     * @param array<mixed> $someArray
     */
    public function __construct(
        public readonly GivenName $givenName,
        #[Description('We can use arrays, too')]
        public readonly array $someArray,
    ) {}
}

final class ShapeWithListBasedProperty
{
    public function __construct(
        public readonly GivenNames $givenNames,
        public readonly string $someString,
    ) {}
}

final class ShapeWithUnionType
{
    public function __construct(
        public readonly GivenName|FamilyName $givenOrFamilyName,
    ) {}
}

final class ShapeWithOptionalUnionType
{
    public function __construct(
        public readonly GivenName|FamilyName|null $givenOrFamilyNameOrNull,
    ) {}
}

final class ShapeWithSimpleUnionType
{
    public function __construct(
        public readonly int|string $integerOrString,
    ) {}
}

final class ShapeWithInterfaceProperty
{
    public function __construct(
        public readonly SomeInterface $property,
    ) {}
}

final class ShapeWithoutConstructor {}

final class ShapeWithDiscriminatedInterfaceProperty
{
    public function __construct(
        public readonly InterfaceWithDiscriminator $property,
    ) {}
}

final class ShapeWithInterfacePropertyAndDiscriminator
{
    public function __construct(
        #[Discriminator(propertyName: 'type', mapping: ['a' => ImplementationAOfInterfaceWithDiscriminator::class, 'b' => ImplementationBOfInterfaceWithDiscriminator::class])]
        public readonly InterfaceWithDiscriminator $property,
    ) {}
}

final class ShapeWithInterfacePropertyAndDiscriminatorWithoutMapping
{
    public function __construct(
        #[Discriminator(propertyName: 'type')]
        public readonly InterfaceWithDiscriminator $property,
    ) {}
}

final class ShapeWithUnionTypeAndDiscriminator
{
    public function __construct(
        #[Discriminator(propertyName: 'type', mapping: ['given' => GivenName::class, 'family' => FamilyName::class, 'invalid' => 'NoClassName'])] // @phpstan-ignore-line
        public readonly GivenName|FamilyName $givenOrFamilyName,
    ) {}
}

final class ShapeWithUnionTypeAndDiscriminatorWithoutMapping
{
    public function __construct(
        #[Discriminator(propertyName: 'type')]
        public readonly GivenName|FamilyName $givenOrFamilyName,
    ) {}
}

final class ShapeWithUnionTypeAndAmbiguousDiscriminator
{
    public function __construct(
        #[Discriminator(propertyName: 'type')]
        public readonly ShapeWithPropertyOfNameType|FamilyName $someProperty,
    ) {}
}

final class ShapeWithOptionalInterfacePropertyAndCustomDiscriminator
{
    public function __construct(
        #[Discriminator(propertyName: 'type', mapping: ['givenName' => GivenName::class, 'familyName' => FamilyName::class])]
        public readonly SomeInterface|null $property = null,
    ) {}
}

#[Discriminator(propertyName: 't', mapping: ['implementationA' => ImplementationAOfInterfaceWithDiscriminator::class, 'implementationB' => ImplementationBOfInterfaceWithDiscriminator::class, 'empty' => EmptyImplementationOfInterfaceWithDiscriminator::class, 'invalid' => 'NoClassName'])] // @phpstan-ignore-line
interface InterfaceWithDiscriminator {}

#[StringBased]
final class ImplementationAOfInterfaceWithDiscriminator implements InterfaceWithDiscriminator
{
    private function __construct(public readonly string $value) {}
}

#[StringBased]
final class ImplementationBOfInterfaceWithDiscriminator implements InterfaceWithDiscriminator
{
    private function __construct(public readonly string $value) {}
}

final class EmptyImplementationOfInterfaceWithDiscriminator implements InterfaceWithDiscriminator
{
    public function __construct()
    {
        // a constructor is required
    }
}

#[Discriminator(propertyName: 'type')]
interface InterfaceWithAmbiguousDiscriminator {}

final class ShapeWithPropertyOfNameType implements InterfaceWithAmbiguousDiscriminator
{
    public function __construct(
        public readonly string $type,
        public readonly bool $flag,
    ) {}
}

final class ShapeWithInvalidDiscriminatorAttribute
{
    public function __construct(
        #[Discriminator(propertyName: 'type', mapping: ['given' => GivenName::class])]
        public readonly GivenName $givenName,
    ) {}
}

final class ShapeWithInvalidDiscriminatorAttributeOnOptionalProperty
{
    public function __construct(
        #[Discriminator(propertyName: 'type', mapping: ['given' => GivenName::class])]
        public readonly GivenName|null $givenName = null,
    ) {}
}

final class ShapeWithPropertyOfNonExistingClass
{
    public function __construct(
        public readonly Non\Existing\Class $someProperty, // @phpstan-ignore-line
    ) {}
}

/**
 * @implements IteratorAggregate<ItemInterface>
 */
#[ListBased(itemClassName: ItemInterface::class)]
final class InterfaceList implements IteratorAggregate
{
    /** @param array<ItemInterface> $items */
    private function __construct(private readonly array $items) {}

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}

interface ItemInterface {}

#[StringBased]
final class ItemA implements ItemInterface
{
    private function __construct(
        public string $value,
    ) {}
}

final class ItemB implements ItemInterface
{
    private function __construct(
        public string $value,
        public GivenName $givenName,
    ) {}
}

/**
 * @implements IteratorAggregate<mixed>
 */
final class StringIterator implements IteratorAggregate
{
    public function __construct(
        private string $first,
        private string $second,
    ) {}

    public function getIterator(): Traversable
    {
        yield from get_object_vars($this);
    }
}

final class ShapeWithTypeDiscriminatedProperties
{
    /**
     * @param array<string> $stringArray
     */
    public function __construct(
        public readonly InterfaceList $interfaceList,
        #[Discriminator(propertyName: 'customT', mapping: ['A' => ImplementationAOfInterfaceWithDiscriminator::class, 'B' => ImplementationBOfInterfaceWithDiscriminator::class])]
        public readonly InterfaceWithDiscriminator $interfaceWithCustomDiscriminator,
        public readonly InterfaceWithDiscriminator $interfaceWithDefaultDiscriminator,
        public readonly array $stringArray,
        public readonly StringIterator $stringIterator,
    ) {}
}

#[Description('Description on recursive class')]
final class ClassWithRecursion
{
    public function __construct(
        public readonly SubClassWithRecursion $subClass,
    ) {}
}

final class SubClassWithRecursion
{
    public function __construct(
        #[Description('recursive property')]
        public readonly ClassWithRecursion $parentClass,
    ) {}
}

final class JsonSerializableShape implements JsonSerializable
{
    public function __construct(
        public readonly JsonSerializableSimpleShape $name,
        public readonly int $age,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'age' => $this->age,
        ];
    }
}

final class JsonSerializableSimpleShape implements JsonSerializable
{
    public function __construct(
        public readonly string $name,
    ) {}

    public function jsonSerialize(): string
    {
        return strtoupper($this->name);
    }
}
