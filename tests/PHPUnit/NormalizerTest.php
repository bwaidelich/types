<?php

declare(strict_types=1);

namespace Wwwision\Types\Tests\PHPUnit;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wwwision\Types\Attributes\Description;
use Wwwision\Types\Attributes\Discriminator;
use Wwwision\Types\Attributes\FloatBased;
use Wwwision\Types\Attributes\IntegerBased;
use Wwwision\Types\Attributes\ListBased;
use Wwwision\Types\Attributes\StringBased;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\Issues\Custom;
use Wwwision\Types\Exception\Issues\InvalidEnumValue;
use Wwwision\Types\Exception\Issues\InvalidString;
use Wwwision\Types\Exception\Issues\InvalidType;
use Wwwision\Types\Exception\Issues\IssueCode;
use Wwwision\Types\Exception\Issues\Issues;
use Wwwision\Types\Exception\Issues\TooBig;
use Wwwision\Types\Exception\Issues\TooSmall;
use Wwwision\Types\Exception\Issues\UnrecognizedKeys;
use Wwwision\Types\Normalizer\Normalizer;
use Wwwision\Types\Parser;
use Wwwision\Types\Schema\ArraySchema;
use Wwwision\Types\Schema\EnumCaseSchema;
use Wwwision\Types\Schema\EnumSchema;
use Wwwision\Types\Schema\FloatSchema;
use Wwwision\Types\Schema\IntegerSchema;
use Wwwision\Types\Schema\InterfaceSchema;
use Wwwision\Types\Schema\ListSchema;
use Wwwision\Types\Schema\LiteralBooleanSchema;
use Wwwision\Types\Schema\LiteralFloatSchema;
use Wwwision\Types\Schema\LiteralIntegerSchema;
use Wwwision\Types\Schema\LiteralStringSchema;
use Wwwision\Types\Schema\OneOfSchema;
use Wwwision\Types\Schema\OptionalSchema;
use Wwwision\Types\Schema\ShapeSchema;
use Wwwision\Types\Schema\StringSchema;
use Wwwision\Types\Schema\StringTypeFormat;
use Wwwision\Types\Tests\Fixture;

use function Wwwision\Types\instantiate;

require_once __DIR__ . '/../Fixture/Fixture.php';

#[CoversClass(ArraySchema::class)]
#[CoversClass(CoerceException::class)]
#[CoversClass(Custom::class)]
#[CoversClass(Description::class)]
#[CoversClass(Discriminator::class)]
#[CoversClass(EnumCaseSchema::class)]
#[CoversClass(EnumSchema::class)]
#[CoversClass(FloatBased::class)]
#[CoversClass(FloatSchema::class)]
#[CoversClass(IntegerBased::class)]
#[CoversClass(IntegerSchema::class)]
#[CoversClass(InterfaceSchema::class)]
#[CoversClass(InvalidEnumValue::class)]
#[CoversClass(InvalidString::class)]
#[CoversClass(InvalidType::class)]
#[CoversClass(IssueCode::class)]
#[CoversClass(Issues::class)]
#[CoversClass(ListBased::class)]
#[CoversClass(ListSchema::class)]
#[CoversClass(LiteralBooleanSchema::class)]
#[CoversClass(LiteralFloatSchema::class)]
#[CoversClass(LiteralIntegerSchema::class)]
#[CoversClass(LiteralStringSchema::class)]
#[CoversClass(Normalizer::class)]
#[CoversClass(OneOfSchema::class)]
#[CoversClass(OptionalSchema::class)]
#[CoversClass(Parser::class)]
#[CoversClass(ShapeSchema::class)]
#[CoversClass(StringBased::class)]
#[CoversClass(StringSchema::class)]
#[CoversClass(StringTypeFormat::class)]
#[CoversClass(TooBig::class)]
#[CoversClass(TooSmall::class)]
#[CoversClass(UnrecognizedKeys::class)]
#[CoversFunction('Wwwision\\Types\\instantiate')]
final class NormalizerTest extends TestCase
{
    public static function normalize_dataProvider(): Generator
    {
        yield 'string-based' => ['className' => Fixture\GivenName::class, 'input' => 'John'];
        yield 'string-based with type discrimination' => ['className' => Fixture\InterfaceWithDiscriminator::class, 'input' => ['t' => 'implementationA', '__value' => 'Foo']];
        yield 'list-based' => ['className' => Fixture\GivenNames::class, 'input' => ['Jane', 'John', 'Hans']];
        yield 'list of interfaces' => ['className' => Fixture\InterfaceList::class, 'input' => [['__type' => Fixture\ItemA::class, '__value' => 'A'], ['__type' => Fixture\ItemB::class, 'value' => 'B', 'givenName' => 'Jane']]];
        yield 'unbacked enum' => ['className' => Fixture\Title::class, 'input' => 'MRS'];
        yield 'int-backed enum' => ['className' => Fixture\Number::class, 'input' => 3];
        yield 'string-backed enum' => ['className' => Fixture\RomanNumber::class, 'input' => '2'];
        yield 'shape' => ['className' => Fixture\FullName::class, 'input' => ['givenName' => 'Jane', 'familyName' => 'Doe']];
        yield 'shape with array property' => ['className' => Fixture\ShapeWithArray::class, 'input' => ['givenName' => 'John', 'someArray' => ['some', 'array', 'values']]];
        yield 'shape with list-based property' => ['className' => Fixture\ShapeWithListBasedProperty::class, 'input' => ['givenNames' => ['John', 'Jane'], 'someString' => 'arbitrary string']];
        yield 'shape with union type and discriminator' => ['className' => Fixture\ShapeWithUnionTypeAndDiscriminator::class, 'input' => ['givenOrFamilyName' => ['type' => 'given', '__value' => 'Jane']]];
        yield 'shape with discriminated interface property' => ['className' => Fixture\ShapeWithDiscriminatedInterfaceProperty::class, 'input' => ['property' => ['t' => 'implementationA', '__value' => 'Foo']]];
        yield 'shape with interface property and discriminator' => ['className' => Fixture\ShapeWithInterfacePropertyAndDiscriminator::class, 'input' => ['property' => ['type' => 'a', '__value' => 'Bar']]];
        yield 'shape with type-discriminated properties' => ['className' => Fixture\ShapeWithTypeDiscriminatedProperties::class, 'input' => ['interfaceList' => [['__type' => Fixture\ItemA::class, '__value' => 'A'], ['__type' => Fixture\ItemB::class, 'value' => 'B', 'givenName' => 'Jane']], 'interfaceWithCustomDiscriminator' => ['customT' => 'A', '__value' => 'Foo'], 'interfaceWithDefaultDiscriminator' => ['t' => 'implementationA', '__value' => 'Bar'], 'stringArray' => ['foo', 'bar'], 'stringIterator' => ['first' => 'baz', 'second' => 'foos']]];
        yield 'shape with left out optional properties' => ['className' => Fixture\ShapeWithOptionalTypes::class, 'input' => ['stringBased' => 'Some Value', 'stringOrNull' => null]];
        yield 'shape with left out optional properties 2' => ['className' => Fixture\ShapeWithOptionalTypes::class, 'input' => ['stringBased' => 'Some Value', 'stringOrNull' => 'value']];
        yield 'shape with specified optional properties' => ['className' => Fixture\ShapeWithOptionalTypes::class, 'input' => ['stringBased' => 'Some Value', 'stringOrNull' => 'foo', 'optionalStringBased' => 'Optional', 'optionalInt' => 123, 'optionalBool' => false, 'optionalString' => 'Optional String', 'stringWithDefaultValue' => 'not the default', 'boolWithDefault' => true]];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('normalize_dataProvider')]
    public function test_normalize(string $className, mixed $input): void
    {
        $instance = instantiate($className, $input);
        $actualResult = (new Normalizer())->normalize($instance);
        self::assertSame($input, $actualResult);
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('normalize_dataProvider')]
    public function test_toJson(string $className, mixed $input): void
    {
        $instance = instantiate($className, $input);
        $expectedResult = json_encode($input, JSON_THROW_ON_ERROR);
        $actualResult = (new Normalizer())->toJson($instance);
        self::assertSame($expectedResult, $actualResult);
    }

    public function test_normalize_includes_type_discrimination_for_root_item_by_default(): void
    {
        $instance = instantiate(Fixture\ImplementationAOfInterfaceWithDiscriminator::class, 'Foo');
        $actualResult = (new Normalizer())->normalize($instance);
        self::assertSame(['t' => 'implementationA', '__value' => 'Foo'], $actualResult);
    }

    public function test_normalize_does_not_includes_type_discrimination_for_root_item_if_is_not_a_discriminated_value(): void
    {
        $instance = instantiate(Fixture\GivenName::class, 'Jane');
        $actualResult = (new Normalizer())->normalize($instance);
        self::assertSame('Jane', $actualResult);
    }

    public function test_normalize_does_not_include_type_discrimination_for_root_item_if_disabled(): void
    {
        $instance = instantiate(Fixture\ImplementationAOfInterfaceWithDiscriminator::class, 'Foo');
        $actualResult = (new Normalizer(includeRootLevelTypeDiscrimination: false))->normalize($instance);
        self::assertSame('Foo', $actualResult);
    }

    public function test_discriminator_is_null_if_instance_does_not_match_any_mapping_value(): void
    {
        $class = new class implements Fixture\InterfaceWithDiscriminator {};
        $instance = new $class();
        $actualResult = (new Normalizer())->normalize($instance);
        self::assertSame(['t' => null], $actualResult);
    }

    public function test_discriminator_is_set_for_concrete_instance_of_discriminated_interface_property(): void
    {
        $propertyInstance = instantiate(Fixture\ImplementationBOfInterfaceWithDiscriminator::class, 'foo');
        $instance = new Fixture\ShapeWithDiscriminatedInterfaceProperty($propertyInstance);
        $actualResult = (new Normalizer())->normalize($instance);
        self::assertSame(['property' => ['t' => 'implementationB', '__value' => 'foo']], $actualResult);
    }

    public function test_overridden_property_discriminator_is_respected(): void
    {
        $propertyInstance = instantiate(Fixture\ImplementationBOfInterfaceWithDiscriminator::class, 'foo');
        $instance = new Fixture\ShapeWithInterfacePropertyAndDiscriminator($propertyInstance);
        $actualResult = (new Normalizer())->normalize($instance);
        self::assertSame(['property' => ['type' => 'b', '__value' => 'foo']], $actualResult);
    }

    public function test_properties_are_removed_if_equal_to_optional_default(): void
    {
        $instance = new Fixture\ShapeWithOptionalTypes(
            stringBased: instantiate(Fixture\FamilyName::class, 'Doe'),
            stringOrNull: null,
            givenNamesOrNull: null,
            optionalStringBased: null,
            optionalInt: null,
            optionalBool: null,
            optionalString: null,
            stringWithDefaultValue: 'default',
            boolWithDefault: false,
        );
        $actualResult = (new Normalizer())->normalize($instance);
        self::assertSame(['stringBased' => 'Doe', 'stringOrNull' => null, 'givenNamesOrNull' => null], $actualResult);
    }

    public function test_normalize_respects_jsonSerializable_properties(): void
    {
        $instance = new Fixture\JsonSerializableShape(new Fixture\JsonSerializableSimpleShape('some name'), 123);
        $actualResult = (new Normalizer())->normalize($instance);
        self::assertSame(['name' => 'SOME NAME', 'age' => 123], $actualResult);
    }

}
