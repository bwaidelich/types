<?php

/** @noinspection PhpDocMissingThrowsInspection */

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Wwwision\Types\Tests\PHPUnit;

use ArrayIterator;
use DateTimeImmutable;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionEnumUnitCase;
use stdClass;
use UnitEnum;
use Wwwision\Types\Attributes\Description;
use Wwwision\Types\Attributes\Discriminator;
use Wwwision\Types\Attributes\FloatBased;
use Wwwision\Types\Attributes\IntegerBased;
use Wwwision\Types\Attributes\ListBased;
use Wwwision\Types\Attributes\StringBased;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Exception\InvalidSchemaException;
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
use Wwwision\Types\Schema\Schema;
use Wwwision\Types\Schema\ShapeSchema;
use Wwwision\Types\Schema\StringSchema;
use Wwwision\Types\Schema\StringTypeFormat;
use Wwwision\Types\Tests\Fixture;

use function json_encode;
use function Wwwision\Types\instantiate;

use const JSON_THROW_ON_ERROR;

require_once __DIR__ . '/../Fixture/Fixture.php';

/**
 * @phpstan-type CoercionIssue array{'code':'case invalid_type'|'unrecognized_keys'|'invalid_enum_value'|'invalid_return_type'|'invalid_string'|'too_small'|'too_big'|'custom', message: string, path: string[]}
 */
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
final class IntegrationTest extends TestCase
{
    public function test_getSchema_throws_if_className_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to get schema for empty class name');
        Parser::getSchema(''); // @phpstan-ignore-line
    }

    public function test_getSchema_throws_if_className_is_not_a_className(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to get schema for class "notAClass" because that class does not exist');
        Parser::getSchema('notAClass'); // @phpstan-ignore-line
    }

    public function test_getSchema_throws_if_given_class_is_shape_with_invalid_properties(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to parse constructor argument "someProperty" of class "ShapeWithInvalidObjectProperty": Missing constructor in class "stdClass"');
        Parser::getSchema(Fixture\ShapeWithInvalidObjectProperty::class);
    }

    /**
     * Note: Currently methods with parameters are not supported, but this can change at some point
     */
    public function test_getSchema_throws_if_given_class_is_interface_with_parameterized_methods(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Method "methodWithParameters" of interface "Wwwision\Types\Tests\Fixture\SomeInvalidInterface" has at least one parameter, but this is currently not supported');
        Parser::getSchema(Fixture\SomeInvalidInterface::class);
    }

    public function test_getSchema_throws_if_shape_has_no_constructor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing constructor in class "Wwwision\Types\Tests\Fixture\ShapeWithoutConstructor"');
        Parser::getSchema(Fixture\ShapeWithoutConstructor::class);
    }

    public function test_getSchema_throws_if_shape_constructor_refers_to_unknown_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to parse constructor argument "someProperty" of class "ShapeWithPropertyOfNonExistingClass": Expected an existing class or interface name, got Wwwision\Types\Tests\Fixture\Non\Existing\Class');
        Parser::getSchema(Fixture\ShapeWithPropertyOfNonExistingClass::class);
    }

    public static function getSchema_dataProvider(): Generator
    {
        yield 'enum' => ['className' => Fixture\Title::class, 'expectedResult' => '{"type":"enum","name":"Title","description":"honorific title of a person","cases":[{"type":"string","description":"for men, regardless of marital status, who do not have another professional or academic title","name":"MR","value":"MR"},{"type":"string","description":"for married women who do not have another professional or academic title","name":"MRS","value":"MRS"},{"type":"string","description":"for girls, unmarried women and married women who continue to use their maiden name","name":"MISS","value":"MISS"},{"type":"string","description":"for women, regardless of marital status or when marital status is unknown","name":"MS","value":"MS"},{"type":"string","description":"for any other title that does not match the above","name":"OTHER","value":"OTHER"}]}'];
        yield 'int backed enum' => ['className' => Fixture\Number::class, 'expectedResult' => '{"type":"enum","name":"Number","description":"A number","cases":[{"type":"integer","description":"The number 1","name":"ONE","value":1},{"type":"integer","description":null,"name":"TWO","value":2},{"type":"integer","description":null,"name":"THREE","value":3}]}'];
        yield 'string backed enum' => ['className' => Fixture\RomanNumber::class, 'expectedResult' => '{"type":"enum","name":"RomanNumber","description":null,"cases":[{"type":"string","description":null,"name":"I","value":"1"},{"type":"string","description":"random description","name":"II","value":"2"},{"type":"string","description":null,"name":"III","value":"3"},{"type":"string","description":null,"name":"IV","value":"4"}]}'];

        yield 'float based object' => ['className' => Fixture\Longitude::class, 'expectedResult' => '{"type":"float","name":"Longitude","description":null,"minimum":-180,"maximum":180.5}'];
        yield 'integer based object' => ['className' => Fixture\Age::class, 'expectedResult' => '{"type":"integer","name":"Age","description":"The age of a person in years","minimum":1,"maximum":120}'];
        yield 'list object' => ['className' => Fixture\FullNames::class, 'expectedResult' => '{"type":"array","name":"FullNames","description":null,"itemType":"FullName","minCount":2,"maxCount":5}'];
        yield 'list of interfaces' => ['className' => Fixture\InterfaceList::class, 'expectedResult' => '{"type":"array","name":"InterfaceList","description":null,"itemType":"ItemInterface"}'];
        yield 'shape object' => ['className' => Fixture\FullName::class, 'expectedResult' => '{"type":"object","name":"FullName","description":"First and last name of a person","properties":[{"type":"GivenName","name":"givenName","description":"First name of a person"},{"type":"FamilyName","name":"familyName","description":"Last name of a person"}]}'];
        yield 'shape object with optional properties' => ['className' => Fixture\ShapeWithOptionalTypes::class, 'expectedResult' => '{"type":"object","name":"ShapeWithOptionalTypes","description":null,"properties":[{"type":"FamilyName","name":"stringBased","description":"Last name of a person"},{"type":"FamilyName|null","name":"stringBasedOrNull","description":null},{"type":"string|null","name":"stringOrNull","description":null},{"type":"GivenNames|null","name":"givenNamesOrNull","description":null},{"type":"FamilyName","name":"optionalStringBased","description":"Last name of a person","optional":true},{"type":"int","name":"optionalInt","description":"Some description","optional":true},{"type":"boolean","name":"optionalBool","description":null,"optional":true},{"type":"string","name":"optionalString","description":null,"optional":true},{"type":"string","name":"stringWithDefaultValue","description":null,"optional":true},{"type":"boolean","name":"boolWithDefault","description":null,"optional":true}]}'];

        yield 'string based object' => ['className' => Fixture\GivenName::class, 'expectedResult' => '{"type":"string","name":"GivenName","description":"First name of a person","minLength":3,"maxLength":20}'];
        yield 'string based object with format' => ['className' => Fixture\EmailAddress::class, 'expectedResult' => '{"type":"string","name":"EmailAddress","description":null,"format":"email"}'];
        yield 'string based object with pattern' => ['className' => Fixture\NotMagic::class, 'expectedResult' => '{"type":"string","name":"NotMagic","description":null,"pattern":"^(?!magic).*"}'];

        yield 'shape with bool' => ['className' => Fixture\ShapeWithBool::class, 'expectedResult' => '{"type":"object","name":"ShapeWithBool","description":null,"properties":[{"type":"boolean","name":"value","description":"Description for literal bool"}]}'];
        yield 'shape with int' => ['className' => Fixture\ShapeWithInt::class, 'expectedResult' => '{"type":"object","name":"ShapeWithInt","description":null,"properties":[{"type":"int","name":"value","description":"Description for literal int"}]}'];
        yield 'shape with string' => ['className' => Fixture\ShapeWithString::class, 'expectedResult' => '{"type":"object","name":"ShapeWithString","description":null,"properties":[{"type":"string","name":"value","description":"Description for literal string"}]}'];
        yield 'shape with float' => ['className' => Fixture\ShapeWithFloat::class, 'expectedResult' => '{"type":"object","name":"ShapeWithFloat","description":null,"properties":[{"type":"float","name":"value","description":"Description for literal float"}]}'];
        yield 'shape with floats' => ['className' => Fixture\GeoCoordinates::class, 'expectedResult' => '{"type":"object","name":"GeoCoordinates","description":null,"properties":[{"type":"Longitude","name":"longitude","description":null},{"type":"Latitude","name":"latitude","description":null}]}'];
        yield 'shape with array' => ['className' => Fixture\ShapeWithArray::class, 'expectedResult' => '{"type":"object","name":"ShapeWithArray","description":null,"properties":[{"type":"GivenName","name":"givenName","description":"First name of a person"},{"type":"array","name":"someArray","description":"We can use arrays, too"}]}'];
        yield 'shape with union type' => ['className' => Fixture\ShapeWithUnionType::class, 'expectedResult' => '{"type":"object","name":"ShapeWithUnionType","description":null,"properties":[{"type":"GivenName|FamilyName","name":"givenOrFamilyName","description":null}]}'];
        yield 'shape with optional union type' => ['className' => Fixture\ShapeWithOptionalUnionType::class, 'expectedResult' => '{"type":"object","name":"ShapeWithOptionalUnionType","description":null,"properties":[{"type":"GivenName|FamilyName|null","name":"givenOrFamilyNameOrNull","description":null}]}'];
        yield 'shape with simple union type' => ['className' => Fixture\ShapeWithSimpleUnionType::class, 'expectedResult' => '{"type":"object","name":"ShapeWithSimpleUnionType","description":null,"properties":[{"type":"string|int","name":"integerOrString","description":null}]}'];

        yield 'interface' => ['className' => Fixture\SomeInterface::class, 'expectedResult' => '{"description":"SomeInterface description","name":"SomeInterface","properties":[{"description":"Custom description for \"someMethod\"","name":"someMethod","type":"string"},{"description":"Custom description for \"someOtherMethod\"","name":"someOtherMethod","optional":true,"type":"FamilyName"}],"type":"interface"}'];
        yield 'shape with interface property' => ['className' => Fixture\ShapeWithInterfaceProperty::class, 'expectedResult' => '{"description":null,"name":"ShapeWithInterfaceProperty","properties":[{"description":"SomeInterface description","name":"property","type":"SomeInterface"}],"type":"object"}'];
        yield 'shape with interface property and discriminator' => ['className' => Fixture\ShapeWithInterfacePropertyAndDiscriminator::class, 'expectedResult' => '{"description":null,"name":"ShapeWithInterfacePropertyAndDiscriminator","properties":[{"description":null,"name":"property","type":"InterfaceWithDiscriminator"}],"type":"object"}'];
        yield 'shape with interface property and discriminator without mapping' => ['className' => Fixture\ShapeWithInterfacePropertyAndDiscriminatorWithoutMapping::class, 'expectedResult' => '{"description":null,"name":"ShapeWithInterfacePropertyAndDiscriminatorWithoutMapping","properties":[{"description":null,"name":"property","type":"InterfaceWithDiscriminator"}],"type":"object"}'];
        yield 'shape with union type and discriminator' => ['className' => Fixture\ShapeWithUnionTypeAndDiscriminator::class, 'expectedResult' => '{"description":null,"name":"ShapeWithUnionTypeAndDiscriminator","properties":[{"description":null,"name":"givenOrFamilyName","type":"GivenName|FamilyName"}],"type":"object"}'];
        yield 'shape with optional interface property and custom discriminator' => ['className' => Fixture\ShapeWithOptionalInterfacePropertyAndCustomDiscriminator::class, 'expectedResult' => '{"description":null,"name":"ShapeWithOptionalInterfacePropertyAndCustomDiscriminator","properties":[{"description":"SomeInterface description","name":"property","optional":true,"type":"SomeInterface"}],"type":"object"}'];
        yield 'interface with discriminator' => ['className' => Fixture\InterfaceWithDiscriminator::class, 'expectedResult' => '{"type":"interface","name":"InterfaceWithDiscriminator","description":null,"properties":[]}'];
        yield 'shape with recursion' => ['className' => Fixture\ClassWithRecursion::class, 'expectedResult' => '{"type":"object","name":"ClassWithRecursion","description":"Description on recursive class","properties":[{"type":"SubClassWithRecursion","name":"subClass","description":null}]}'];
        yield 'shape with recursion 2' => ['className' => Fixture\SubClassWithRecursion::class, 'expectedResult' => '{"type":"object","name":"SubClassWithRecursion","description":null,"properties":[{"type":"ClassWithRecursion","name":"parentClass","description":"Description on recursive class"}]}'];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('getSchema_dataProvider')]
    public function test_getSchema(string $className, string $expectedResult): void
    {
        $schema = Parser::getSchema($className);
        self::assertJsonStringEqualsJsonString($expectedResult, json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public static function isInstance_dataProvider(): Generator
    {
        yield 'enum' => ['className' => Fixture\Title::class, 'value' => Fixture\Title::MISS];
        yield 'int backed enum' => ['className' => Fixture\Number::class, 'value' => Fixture\Number::TWO];
        yield 'string backed enum' => ['className' => Fixture\RomanNumber::class, 'value' => Fixture\RomanNumber::IV];

        yield 'integer based object' => ['className' => Fixture\Age::class, 'value' => instantiate(Fixture\Age::class, 44)];
        yield 'list object' => ['className' => Fixture\GivenNames::class, 'value' => instantiate(Fixture\GivenNames::class, ['Jane', 'John'])];
        yield 'list of interfaces' => ['className' => Fixture\InterfaceList::class, 'value' => instantiate(Fixture\InterfaceList::class, [['__type' => Fixture\ItemA::class, '__value' => 'Some value'], ['__type' => Fixture\ItemB::class, 'value' => 'some value', 'givenName' => 'Jane']])];
        yield 'shape object' => ['className' => Fixture\FullName::class, 'value' => instantiate(Fixture\FullName::class, ['givenName' => 'John', 'familyName' => 'Doe'])];
        yield 'shape object with interface property and discriminator' => ['className' => Fixture\ShapeWithInterfacePropertyAndDiscriminator::class, 'value' => instantiate(Fixture\ShapeWithInterfacePropertyAndDiscriminator::class, ['property' => ['type' => 'a', '__value' => 'Jane']])];
        yield 'shape object with union type and discriminator' => ['className' => Fixture\ShapeWithUnionTypeAndDiscriminator::class, 'value' => instantiate(Fixture\ShapeWithUnionTypeAndDiscriminator::class, ['givenOrFamilyName' => ['type' => 'given', '__value' => 'Jane']])];

        yield 'string based object' => ['className' => Fixture\GivenName::class, 'value' => instantiate(Fixture\GivenName::class, 'Jane')];

        yield 'interface' => ['className' => Fixture\SomeInterface::class, 'value' => instantiate(Fixture\GivenName::class, 'Jane')];
        yield 'interface with discriminator' => ['className' => Fixture\InterfaceWithDiscriminator::class, 'value' => instantiate(Fixture\ImplementationBOfInterfaceWithDiscriminator::class, 'Foo')];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('isInstance_dataProvider')]
    public function test_isInstance(string $className, mixed $value): void
    {
        $schema = Parser::getSchema($className);
        self::assertTrue($schema->isInstance($value));
    }


    public function test_getSchema_for_shape_object_allows_to_retrieve_overridden_property_descriptions(): void
    {
        $schema = Parser::getSchema(Fixture\ShapeWithOptionalTypes::class);
        self::assertInstanceOf(ShapeSchema::class, $schema);
        self::assertNull($schema->overriddenPropertyDescription('unknown'));
        self::assertNull($schema->overriddenPropertyDescription('stringBased'));
        self::assertSame('Some description', $schema->overriddenPropertyDescription('optionalInt'));
    }

    public function test_getSchema_for_literal_boolean(): void
    {
        $literalBooleanSchema = new LiteralBooleanSchema('Some Description');
        self::assertJsonStringEqualsJsonString('{"type":"boolean","name":"boolean","description":"Some Description"}', json_encode($literalBooleanSchema, JSON_THROW_ON_ERROR));
    }

    public function test_getSchema_for_literal_integer(): void
    {
        $literalIntegerSchema = new LiteralIntegerSchema('Some Description');
        self::assertJsonStringEqualsJsonString('{"type":"integer","name":"int","description":"Some Description"}', json_encode($literalIntegerSchema, JSON_THROW_ON_ERROR));
    }

    public function test_getSchema_for_literal_float(): void
    {
        $literalFloatSchema = new LiteralFloatSchema('Some Description');
        self::assertJsonStringEqualsJsonString('{"type":"float","name":"float","description":"Some Description"}', json_encode($literalFloatSchema, JSON_THROW_ON_ERROR));
    }

    public static function instantiate_literal_float_dataProvider(): Generator
    {
        yield 'zero' => ['value' => 0, 'expectedResult' => 0.0, 'requiresCoercion' => true];
        yield 'string representing floating number' => ['value' => '1.2', 'expectedResult' => 1.2, 'requiresCoercion' => true];
        yield 'string representing integer' => ['value' => '123', 'expectedResult' => 123.0, 'requiresCoercion' => true];

        yield 'zero with point' => ['value' => 0., 'expectedResult' => 0.0, 'requiresCoercion' => false];
        yield 'double' => ['value' => 123.45, 'expectedResult' => 123.45, 'requiresCoercion' => false];
    }

    #[DataProvider('instantiate_literal_float_dataProvider')]
    public function test_instantiate_for_literal_float(mixed $value, mixed $expectedResult, bool $requiresCoercion): void
    {
        $literalFloatSchema = new LiteralFloatSchema(null);
        self::assertSame($requiresCoercion, !$literalFloatSchema->isInstance($value));
        self::assertSame($expectedResult, $literalFloatSchema->instantiate($value));
    }

    public static function instantiate_failing_literal_float_dataProvider(): Generator
    {
        yield 'string representing no number' => ['value' => 'NaN', 'expectedException' => 'Failed to cast string of "NaN" to float: invalid_type (Expected float, received string)'];
        yield 'object' => ['value' => new stdClass(), 'expectedException' => 'Failed to cast value of type stdClass to float: invalid_type (Expected float, received object)'];
    }

    #[DataProvider('instantiate_failing_literal_float_dataProvider')]
    public function test_instantiate_failing_for_literal_float(mixed $value, string $expectedException): void
    {
        $literalFloatSchema = new LiteralFloatSchema(null);

        $this->expectException(CoerceException::class);
        $this->expectExceptionMessage($expectedException);
        $literalFloatSchema->instantiate($value);
    }

    public function test_getSchema_for_literal_string(): void
    {
        $literalStringSchema = new LiteralStringSchema('Some Description');
        self::assertJsonStringEqualsJsonString('{"type":"string","name":"string","description":"Some Description"}', json_encode($literalStringSchema, JSON_THROW_ON_ERROR));
    }

    public function test_getSchema_for_literal_array(): void
    {
        $arraySchema = new ArraySchema('Some Description');
        self::assertJsonStringEqualsJsonString('{"type":"array","name":"array","description":"Some Description"}', json_encode($arraySchema, JSON_THROW_ON_ERROR));
    }

    public function test_getSchema_for_optional(): void
    {
        $mockWrapped = $this->getMockBuilder(Schema::class)->getMock();
        $mockWrapped->method('getType')->willReturn('WrappedType');
        $mockWrapped->method('getName')->willReturn('WrappedName');
        $mockWrapped->method('getDescription')->willReturn('WrappedDescription');
        $literalBooleanSchema = new OptionalSchema($mockWrapped);
        self::assertJsonStringEqualsJsonString('{"type":"WrappedType","name":"WrappedName","description":"WrappedDescription","optional":true}', json_encode($literalBooleanSchema, JSON_THROW_ON_ERROR));
    }

    public static function instantiate_optional_dataProvider(): Generator
    {
        yield 'string' => ['value' => 'foo', 'expectedResult' => 'foo', 'requiresCoercion' => false];
        yield 'null' => ['value' => null, 'expectedResult' => null, 'requiresCoercion' => false];
        yield 'integer' => ['value' => 123, 'expectedResult' => '123', 'requiresCoercion' => true];
    }

    #[DataProvider('instantiate_optional_dataProvider')]
    public function test_instantiate_for_optional(mixed $value, mixed $expectedResult, bool $requiresCoercion): void
    {
        $optionalSchema = new OptionalSchema(new LiteralStringSchema(null));
        self::assertSame($requiresCoercion, !$optionalSchema->isInstance($value));
        self::assertSame($expectedResult, $optionalSchema->instantiate($value));
    }

    public function test_instantiate_throws_if_className_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to get schema for empty class name');
        instantiate('', null); // @phpstan-ignore-line
    }

    public function test_instantiate_throws_if_className_is_not_a_className(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to get schema for class "notAClass" because that class does not exist');
        instantiate('notAClass', null); // @phpstan-ignore-line
    }

    public static function instantiate_enum_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'MR\' | \'MRS\' | \'MISS\' | \'MS\' | \'OTHER\', received null', 'path' => [], 'expected' => 'enum', 'received' => 'null']]];
        yield 'from string that is no case' => ['value' => 'mr', 'expectedIssues' => [['code' => 'invalid_enum_value', 'message' => 'Invalid enum value. Expected \'MR\' | \'MRS\' | \'MISS\' | \'MS\' | \'OTHER\', received \'mr\'', 'path' => [], 'received' => '\'mr\'', 'options' => ['MR', 'MRS', 'MISS', 'MS', 'OTHER']]]];
        yield 'from long string that is no case' => ['value' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum', 'expectedIssues' => [['code' => 'invalid_enum_value', 'message' => 'Invalid enum value. Expected \'MR\' | \'MRS\' | \'MISS\' | \'MS\' | \'OTHER\', received \'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt[...]\'', 'path' => [], 'received' => '\'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt[...]\'', 'options' => ['MR', 'MRS', 'MISS', 'MS', 'OTHER']]]];
        yield 'from object' => ['value' => new stdClass(), 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'MR\' | \'MRS\' | \'MISS\' | \'MS\' | \'OTHER\', received object', 'path' => [], 'expected' => 'enum', 'received' => 'object']]];
        yield 'from boolean' => ['value' => true, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'MR\' | \'MRS\' | \'MISS\' | \'MS\' | \'OTHER\', received boolean', 'path' => [], 'expected' => 'enum', 'received' => 'boolean']]];
        yield 'from integer' => ['value' => 3, 'expectedIssues' => [['code' => 'invalid_enum_value', 'message' => 'Invalid enum value. Expected \'MR\' | \'MRS\' | \'MISS\' | \'MS\' | \'OTHER\', received integer', 'path' => [], 'received' => 'integer', 'options' => ['MR', 'MRS', 'MISS', 'MS', 'OTHER']]]];
        yield 'from float without fraction' => ['value' => 2.0, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'MR\' | \'MRS\' | \'MISS\' | \'MS\' | \'OTHER\', received double', 'path' => [], 'expected' => 'enum', 'received' => 'double']]];
        yield 'from float with fraction' => ['value' => 2.5, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'MR\' | \'MRS\' | \'MISS\' | \'MS\' | \'OTHER\', received double', 'path' => [], 'expected' => 'enum', 'received' => 'double']]];
    }

    /**
     * @param array<CoercionIssue[]> $expectedIssues
     */
    #[DataProvider('instantiate_enum_failing_dataProvider')]
    public function test_instantiate_enum_failing(mixed $value, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate(Fixture\Title::class, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
    }

    public static function instantiate_enum_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => Fixture\Title::MR, 'expectedResult' => Fixture\Title::MR];
        yield 'from string matching a case' => ['value' => 'MRS', 'expectedResult' => Fixture\Title::MRS];
        yield 'from stringable object matching a case' => ['value' => new class {
            public function __toString()
            {
                return 'MISS';
            }
        }, 'expectedResult' => Fixture\Title::MISS];
        yield 'from already converted instance' => ['value' => Fixture\Title::MS, 'expectedResult' => Fixture\Title::MS];
    }

    #[DataProvider('instantiate_enum_dataProvider')]
    public function test_instantiate_enum(mixed $value, Fixture\Title $expectedResult): void
    {
        self::assertSame($expectedResult, instantiate(Fixture\Title::class, $value));
    }

    public static function instantiate_enumCase_dataProvider(): Generator
    {
        yield 'unit enum from string' => ['unitEnum' => Fixture\Title::MISS, 'value' => 'MISS', 'requiresCoercion' => true];
        yield 'unit enum from unit enum' => ['unitEnum' => Fixture\Title::MISS, 'value' => Fixture\Title::MISS, 'requiresCoercion' => false];

        yield 'int-backed enum from string' => ['unitEnum' => Fixture\Number::TWO, 'value' => '2', 'requiresCoercion' => true];
        yield 'int-backed enum from int' => ['unitEnum' => Fixture\Number::TWO, 'value' => 2, 'requiresCoercion' => true];
        yield 'int-backed enum from unit enum' => ['unitEnum' => Fixture\Number::TWO, 'value' => Fixture\Number::TWO, 'requiresCoercion' => false];

        yield 'string-backed enum from string' => ['unitEnum' => Fixture\Number::TWO, 'value' => '2', 'requiresCoercion' => true];
        yield 'string-backed enum from int' => ['unitEnum' => Fixture\Number::TWO, 'value' => 2, 'requiresCoercion' => true];
        yield 'string-backed enum from unit enum' => ['unitEnum' => Fixture\Number::TWO, 'value' => Fixture\Number::TWO, 'requiresCoercion' => false];
    }

    #[DataProvider('instantiate_enumCase_dataProvider')]
    public function test_instantiate_for_enumCase(UnitEnum $unitEnum, mixed $value, bool $requiresCoercion): void
    {
        $reflectionEnumUnitCase = new ReflectionEnumUnitCase($unitEnum, $unitEnum->name);
        $enumCaseSchema = new EnumCaseSchema($reflectionEnumUnitCase, null);
        self::assertSame($requiresCoercion, !$enumCaseSchema->isInstance($value));
    }

    public static function instantiate_int_backed_enum_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected 1 | 2 | 3, received null', 'path' => [], 'expected' => 'enum', 'received' => 'null']]];
        yield 'from string' => ['value' => 'TWO', 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected 1 | 2 | 3, received string', 'path' => [], 'expected' => 'enum', 'received' => 'string']]];
        yield 'from object' => ['value' => new stdClass(), 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected 1 | 2 | 3, received object', 'path' => [], 'expected' => 'enum', 'received' => 'object']]];
        yield 'from float with fraction' => ['value' => 2.5, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected 1 | 2 | 3, received double', 'path' => [], 'expected' => 'enum', 'received' => 'double']]];
        yield 'from float with fraction 2' => ['value' => 2.345678, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected 1 | 2 | 3, received double', 'path' => [], 'expected' => 'enum', 'received' => 'double']]];
        yield 'from int that matches no case' => ['value' => 5, 'expectedIssues' => [['code' => 'invalid_enum_value', 'message' => 'Invalid enum value. Expected 1 | 2 | 3, received integer', 'path' => [], 'received' => 'integer', 'options' => [1, 2, 3]]]];
    }

    /**
     * @param array<CoercionIssue[]> $expectedIssues
     */
    #[DataProvider('instantiate_int_backed_enum_failing_dataProvider')]
    public function test_instantiate_int_backed_enum_failing(mixed $value, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate(Fixture\Number::class, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
    }

    public static function instantiate_int_backed_enum_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => Fixture\Number::ONE, 'expectedResult' => Fixture\Number::ONE];
        yield 'from numeric string' => ['value' => '2', 'expectedResult' => Fixture\Number::TWO];
        yield 'from integer' => ['value' => 3, 'expectedResult' => Fixture\Number::THREE];
        yield 'from float without fraction' => ['value' => 1.0, 'expectedResult' => Fixture\Number::ONE];
    }

    #[DataProvider('instantiate_int_backed_enum_dataProvider')]
    public function test_instantiate_int_backed_enum(mixed $value, Fixture\Number $expectedResult): void
    {
        self::assertSame($expectedResult, instantiate(Fixture\Number::class, $value));
    }

    public static function instantiate_string_backed_enum_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'1\' | \'2\' | \'3\' | \'4\', received null', 'path' => [], 'expected' => 'enum', 'received' => 'null']]];
        yield 'from string that is no case' => ['value' => 'i', 'expectedIssues' => [['code' => 'invalid_enum_value', 'message' => 'Invalid enum value. Expected \'1\' | \'2\' | \'3\' | \'4\', received \'i\'', 'path' => [], 'received' => '\'i\'', 'options' => ['1', '2', '3', '4']]]];
        yield 'from object' => ['value' => new stdClass(), 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'1\' | \'2\' | \'3\' | \'4\', received object', 'path' => [], 'expected' => 'enum', 'received' => 'object']]];
        yield 'from boolean' => ['value' => false, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'1\' | \'2\' | \'3\' | \'4\', received boolean', 'path' => [], 'expected' => 'enum', 'received' => 'boolean']]];
        yield 'from integer that matches no case' => ['value' => 12, 'expectedIssues' => [['code' => 'invalid_enum_value', 'message' => 'Invalid enum value. Expected \'1\' | \'2\' | \'3\' | \'4\', received integer', 'path' => [], 'received' => 'integer', 'options' => ['1', '2', '3', '4']]]];
        yield 'from float without fraction that matches a case' => ['value' => 2.0, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'1\' | \'2\' | \'3\' | \'4\', received double', 'path' => [], 'expected' => 'enum', 'received' => 'double']]];
        yield 'from float with fraction' => ['value' => 2.5, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'1\' | \'2\' | \'3\' | \'4\', received double', 'path' => [], 'expected' => 'enum', 'received' => 'double']]];
    }

    /**
     * @param array<CoercionIssue[]> $expectedIssues
     */
    #[DataProvider('instantiate_string_backed_enum_failing_dataProvider')]
    public function test_instantiate_string_backed_enum_failing(mixed $value, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate(Fixture\RomanNumber::class, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
    }

    public static function instantiate_string_backed_enum_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => Fixture\RomanNumber::I, 'expectedResult' => Fixture\RomanNumber::I];
        yield 'from string that is a case' => ['value' => '2', 'expectedResult' => Fixture\RomanNumber::II];
        yield 'from integer matching a case' => ['value' => 4, 'expectedResult' => Fixture\RomanNumber::IV];
    }

    #[DataProvider('instantiate_string_backed_enum_dataProvider')]
    public function test_instantiate_string_backed_enum(mixed $value, Fixture\RomanNumber $expectedResult): void
    {
        self::assertSame($expectedResult, instantiate(Fixture\RomanNumber::class, $value));
    }

    public static function instantiate_float_based_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => Fixture\Longitude::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected float, received null', 'path' => [], 'expected' => 'float', 'received' => 'null']]];
        yield 'from object' => ['value' => new stdClass(), 'className' => Fixture\Longitude::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected float, received object', 'path' => [], 'expected' => 'float', 'received' => 'object']]];
        yield 'from boolean' => ['value' => false, 'className' => Fixture\Longitude::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected float, received boolean', 'path' => [], 'expected' => 'float', 'received' => 'boolean']]];
        yield 'from string' => ['value' => 'not numeric', 'className' => Fixture\Longitude::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected float, received string', 'path' => [], 'expected' => 'float', 'received' => 'string']]];

        yield 'from float violating minimum' => ['value' => -181.0, 'className' => Fixture\Longitude::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Number must be greater than or equal to -180.000', 'path' => [], 'type' => 'double', 'minimum' => -180, 'inclusive' => true, 'exact' => false]]];
        yield 'from float with fraction violating minimum' => ['value' => -90.123, 'className' => Fixture\Latitude::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Number must be greater than or equal to -90.000', 'path' => [], 'type' => 'double', 'minimum' => -90, 'inclusive' => true, 'exact' => false]]];
        yield 'from float violating maximum' => ['value' => 181.0, 'className' => Fixture\Longitude::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Number must be less than or equal to 180.500', 'path' => [], 'type' => 'double', 'maximum' => 180.5, 'inclusive' => true, 'exact' => false]]];
        yield 'from float with fraction violating maximum' => ['value' => 90.123, 'className' => Fixture\Latitude::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Number must be less than or equal to 90.000', 'path' => [], 'type' => 'double', 'maximum' => 90, 'inclusive' => true, 'exact' => false]]];

        yield 'from float with fraction violating multiple constraints' => ['value' => 5.34, 'className' => Fixture\ImpossibleFloat::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Number must be less than or equal to 2.450', 'path' => [], 'type' => 'double', 'maximum' => 2.45, 'inclusive' => true, 'exact' => false], ['code' => 'too_small', 'message' => 'Number must be greater than or equal to 10.230', 'path' => [], 'type' => 'double', 'minimum' => 10.23, 'inclusive' => true, 'exact' => false]]];
    }

    /**
     * @param class-string<object> $className
     * @param array<CoercionIssue[]> $expectedIssues
     */
    #[DataProvider('instantiate_float_based_object_failing_dataProvider')]
    public function test_instantiate_float_based_object_failing(mixed $value, string $className, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate($className, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
    }

    public static function instantiate_float_based_object_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => instantiate(Fixture\Longitude::class, 120), 'expectedResult' => 120.0];
        yield 'from integer that matches constraints' => ['value' => 120, 'expectedResult' => 120];
        yield 'from numeric string that matches constraints' => ['value' => '1', 'expectedResult' => 1];
        yield 'from numeric string with floating point that matches constraints' => ['value' => '1.234', 'expectedResult' => 1.234];
        yield 'from float without fraction' => ['value' => 4.0, 'expectedResult' => 4];
        yield 'from float with fraction' => ['value' => 4.456, 'expectedResult' => 4.456];
    }

    #[DataProvider('instantiate_float_based_object_dataProvider')]
    public function test_instantiate_float_based_object(mixed $value, float $expectedResult): void
    {
        self::assertSame($expectedResult, instantiate(Fixture\Longitude::class, $value)->value);
    }

    public function test_instantiate_float_based_object_returns_same_instance_if_already_valid(): void
    {
        $instance = instantiate(Fixture\Longitude::class, 120);
        $converted = Parser::getSchema(Fixture\Longitude::class)->instantiate($instance);

        self::assertSame($instance, $converted);
    }

    public static function instantiate_int_based_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => Fixture\Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received null', 'path' => [], 'expected' => 'integer', 'received' => 'null']]];
        yield 'from object' => ['value' => new stdClass(), 'className' => Fixture\Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received object', 'path' => [], 'expected' => 'integer', 'received' => 'object']]];
        yield 'from boolean' => ['value' => false, 'className' => Fixture\Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received boolean', 'path' => [], 'expected' => 'integer', 'received' => 'boolean']]];
        yield 'from string' => ['value' => 'not numeric', 'className' => Fixture\Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received string', 'path' => [], 'expected' => 'integer', 'received' => 'string']]];
        yield 'from string with float' => ['value' => '2.0', 'className' => Fixture\Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received string', 'path' => [], 'expected' => 'integer', 'received' => 'string']]];
        yield 'from float with fraction' => ['value' => 2.5, 'className' => Fixture\Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received double', 'path' => [], 'expected' => 'integer', 'received' => 'double']]];

        yield 'from integer violating minimum' => ['value' => 0, 'className' => Fixture\Age::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Number must be greater than or equal to 1', 'path' => [], 'type' => 'integer', 'minimum' => 1, 'inclusive' => true, 'exact' => false]]];
        yield 'from integer violating maximum' => ['value' => 121, 'className' => Fixture\Age::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Number must be less than or equal to 120', 'path' => [], 'type' => 'integer', 'maximum' => 120, 'inclusive' => true, 'exact' => false]]];

        yield 'from integer violating multiple constraints' => ['value' => 5, 'className' => Fixture\ImpossibleInt::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Number must be less than or equal to 2', 'path' => [], 'type' => 'integer', 'maximum' => 2, 'inclusive' => true, 'exact' => false], ['code' => 'too_small', 'message' => 'Number must be greater than or equal to 10', 'path' => [], 'type' => 'integer', 'minimum' => 10, 'inclusive' => true, 'exact' => false]]];
    }

    /**
     * @param class-string<object> $className
     * @param array<CoercionIssue[]> $expectedIssues
     */
    #[DataProvider('instantiate_int_based_object_failing_dataProvider')]
    public function test_instantiate_int_based_object_failing(mixed $value, string $className, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate($className, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
    }

    public static function instantiate_int_based_object_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => instantiate(Fixture\Age::class, 120), 'expectedResult' => 120];
        yield 'from integer that matches constraints' => ['value' => 120, 'expectedResult' => 120];
        yield 'from numeric string that matches constraints' => ['value' => '1', 'expectedResult' => 1];
        yield 'from float without fraction' => ['value' => 4.0, 'expectedResult' => 4];
    }

    #[DataProvider('instantiate_int_based_object_dataProvider')]
    public function test_instantiate_int_based_object(mixed $value, int $expectedResult): void
    {
        self::assertSame($expectedResult, instantiate(Fixture\Age::class, $value)->value);
    }

    public static function instantiate_list_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => Fixture\FullNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected array, received null', 'path' => [], 'expected' => 'array', 'received' => 'null']]];
        yield 'from object' => ['value' => new stdClass(), 'className' => Fixture\FullNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected array, received object', 'path' => [], 'expected' => 'array', 'received' => 'object']]];
        yield 'from boolean' => ['value' => false, 'className' => Fixture\FullNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected array, received boolean', 'path' => [], 'expected' => 'array', 'received' => 'boolean']]];
        yield 'from string' => ['value' => 'some string', 'className' => Fixture\FullNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected array, received string', 'path' => [], 'expected' => 'array', 'received' => 'string']]];

        yield 'from array with invalid item' => ['value' => [123.45], 'className' => Fixture\GivenNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received double', 'path' => [0], 'expected' => 'string', 'received' => 'double']]];
        yield 'from array with invalid items' => ['value' => ['Some value', 'Some other value'], 'className' => Fixture\FullNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received string', 'path' => [0], 'expected' => 'object', 'received' => 'string'], ['code' => 'invalid_type', 'message' => 'Expected object, received string', 'path' => [1], 'expected' => 'object', 'received' => 'string']]];
        yield 'from non-assoc array with custom exception' => ['value' => ['https://wwwision.de', 'https://neos.io'], 'className' => Fixture\UriMap::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Expected associative array with string keys', 'path' => [], 'params' => []]]];
        yield 'from array violating minCount' => ['value' => [], 'className' => Fixture\FullNames::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Array must contain at least 2 element(s)', 'path' => [], 'type' => 'array', 'minimum' => 2, 'inclusive' => true, 'exact' => false]]];
        yield 'from array violating minCount 2' => ['value' => [['givenName' => 'John', 'familyName' => 'Doe']], 'className' => Fixture\FullNames::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Array must contain at least 2 element(s)', 'path' => [], 'type' => 'array', 'minimum' => 2, 'inclusive' => true, 'exact' => false]]];
        yield 'from array violating maxCount' => ['value' => ['John', 'Jane', 'Max', 'Jack', 'Fred'], 'className' => Fixture\GivenNames::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Array must contain at most 4 element(s)', 'path' => [], 'type' => 'array', 'maximum' => 4, 'inclusive' => true, 'exact' => false]]];
        yield 'from array violating mixCount and maxCount' => ['value' => ['foo', 'bar', 'baz'], 'className' => Fixture\ImpossibleList::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Array must contain at least 10 element(s)', 'path' => [], 'type' => 'array', 'minimum' => 10, 'inclusive' => true, 'exact' => false], ['code' => 'too_big', 'message' => 'Array must contain at most 2 element(s)', 'path' => [], 'type' => 'array', 'maximum' => 2, 'inclusive' => true, 'exact' => false]]];
        yield 'from array violating mixCount and maxCount and element constraints' => ['value' => ['a', 'bar', 'c'], 'className' => Fixture\ImpossibleList::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Array must contain at least 10 element(s)', 'path' => [], 'type' => 'array', 'minimum' => 10, 'inclusive' => true, 'exact' => false], ['code' => 'too_big', 'message' => 'Array must contain at most 2 element(s)', 'path' => [], 'type' => 'array', 'maximum' => 2, 'inclusive' => true, 'exact' => false], ['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => [0], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false], ['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => [2], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false]]];
        yield 'from array missing type discriminators' => ['value' => [['value' => 'foo']], 'className' => Fixture\InterfaceList::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing discriminator key "__type"', 'path' => [0], 'params' => []]]];
    }

    /**
     * @param class-string $className
     * @param array<CoercionIssue[]> $expectedIssues
     */
    #[DataProvider('instantiate_list_object_failing_dataProvider')]
    public function test_instantiate_list_object_failing(mixed $value, string $className, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate($className, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
    }

    public static function instantiate_list_object_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => instantiate(Fixture\GivenNames::class, ['John', 'Jack', 'Jane']), 'className' => Fixture\GivenNames::class, 'expectedResult' => '["John","Jack","Jane"]'];
        yield 'from strings' => ['value' => ['John', 'Jack', 'Jane'], 'className' => Fixture\GivenNames::class, 'expectedResult' => '["John","Jack","Jane"]'];
        yield 'map of strings' => ['value' => ['wwwision' => 'https://wwwision.de', 'Neos CMS' => 'https://neos.io'], 'className' => Fixture\UriMap::class, 'expectedResult' => '{"wwwision":"https://wwwision.de","Neos CMS":"https://neos.io"}'];
        yield 'items with discriminators' => ['value' => [['__type' => Fixture\ItemA::class, '__value' => 'Value a'], ['__type' => Fixture\ItemB::class, 'value' => 'Value b', 'givenName' => 'Jane']], 'className' => Fixture\InterfaceList::class, 'expectedResult' => '[{"__type":"Wwwision\\\Types\\\Tests\\\Fixture\\\ItemA","__value":"Value a"},{"__type":"Wwwision\\\Types\\\Tests\\\Fixture\\\ItemB","value":"Value b","givenName":"Jane"}]'];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('instantiate_list_object_dataProvider')]
    public function test_instantiate_list_object(mixed $value, string $className, string $expectedResult): void
    {
        $instance = instantiate($className, $value);
        $actualResult = (new Normalizer())->toJson($instance);
        self::assertSame($expectedResult, $actualResult);
    }

    public static function instantiate_shape_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => Fixture\FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received null', 'path' => [], 'expected' => 'object', 'received' => 'null']]];
        yield 'from empty object' => ['value' => new stdClass(), 'className' => Fixture\FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Required', 'path' => ['givenName'], 'expected' => 'string', 'received' => 'undefined'], ['code' => 'invalid_type', 'message' => 'Required', 'path' => ['familyName'], 'expected' => 'string', 'received' => 'undefined']]];
        yield 'from boolean' => ['value' => false, 'className' => Fixture\FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received boolean', 'path' => [], 'expected' => 'object', 'received' => 'boolean']]];
        yield 'from string' => ['value' => 'some string', 'className' => Fixture\FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received string', 'path' => [], 'expected' => 'object', 'received' => 'string']]];

        yield 'from array with missing key' => ['value' => ['givenName' => 'Some first name'], 'className' => Fixture\FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Required', 'path' => ['familyName'], 'expected' => 'string', 'received' => 'undefined']]];
        yield 'from array with missing keys' => ['value' => [], 'className' => Fixture\FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Required', 'path' => ['givenName'], 'expected' => 'string', 'received' => 'undefined'], ['code' => 'invalid_type', 'message' => 'Required', 'path' => ['familyName'], 'expected' => 'string', 'received' => 'undefined']]];
        yield 'from array with additional key' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Some last name', 'additional' => 'not allowed'], 'className' => Fixture\FullName::class, 'expectedIssues' => [['code' => 'unrecognized_keys', 'message' => 'Unrecognized key(s) in object: \'additional\'', 'path' => [], 'keys' => ['additional']]]];
        yield 'from array with additional keys' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Some last name', 'additional' => 'not allowed', 'another additional' => 'also not allowed'], 'className' => Fixture\FullName::class, 'expectedIssues' => [['code' => 'unrecognized_keys', 'message' => 'Unrecognized key(s) in object: \'additional\', \'another additional\'', 'path' => [], 'keys' => ['additional', 'another additional']]]];
        yield 'from array with missing keys for union type' => ['value' => ['givenOrFamilyName' => ['__type' => Fixture\GivenName::class]], 'className' => Fixture\ShapeWithUnionType::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing keys for union of type GivenName|FamilyName', 'path' => ['givenOrFamilyName'], 'params' => []]]];
        yield 'from array with invalid __type for union type' => ['value' => ['givenOrFamilyName' => ['__type' => Fixture\EmailAddress::class, '__value' => 'foo@bar.com']], 'className' => Fixture\ShapeWithUnionType::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'The given "__type" of "Wwwision\\Types\\Tests\\Fixture\\EmailAddress" is not an implementation of GivenName|FamilyName', 'path' => ['givenOrFamilyName'], 'params' => []]]];
        yield 'from array with invalid value for simple union type' => ['value' => ['integerOrString' => 12.34], 'className' => Fixture\ShapeWithSimpleUnionType::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string|int, received double', 'path' => ['integerOrString'], 'expected' => 'string|int', 'received' => 'double']]];

        yield 'from array with property violating constraint' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Ab'], 'className' => Fixture\FullName::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => ['familyName'], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false]]];
        yield 'from array with properties violating constraints' => ['value' => ['givenName' => 'Ab', 'familyName' => 'Ab'], 'className' => Fixture\FullName::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => ['givenName'], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false], ['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => ['familyName'], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false]]];

        yield 'bool from string' => ['value' => ['value' => 'not a bool'], 'className' => Fixture\ShapeWithBool::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected boolean, received string', 'path' => ['value'], 'expected' => 'boolean', 'received' => 'string']]];
        yield 'bool from int' => ['value' => ['value' => 123], 'className' => Fixture\ShapeWithBool::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected boolean, received integer', 'path' => ['value'], 'expected' => 'boolean', 'received' => 'integer']]];
        yield 'bool from object' => ['value' => ['value' => new stdClass()], 'className' => Fixture\ShapeWithBool::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected boolean, received object', 'path' => ['value'], 'expected' => 'boolean', 'received' => 'object']]];
        yield 'string from float' => ['value' => ['value' => 123.45], 'className' => Fixture\ShapeWithString::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received double', 'path' => ['value'], 'expected' => 'string', 'received' => 'double']]];
        yield 'integer from float' => ['value' => ['value' => 123.45], 'className' => Fixture\ShapeWithInt::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received double', 'path' => ['value'], 'expected' => 'integer', 'received' => 'double']]];
        yield 'integer from string' => ['value' => ['value' => 'not numeric'], 'className' => Fixture\ShapeWithInt::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received string', 'path' => ['value'], 'expected' => 'integer', 'received' => 'string']]];
        yield 'integer from object' => ['value' => ['value' => new stdClass()], 'className' => Fixture\ShapeWithInt::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received object', 'path' => ['value'], 'expected' => 'integer', 'received' => 'object']]];

        yield 'nested shape' => ['value' => ['shapeWithOptionalTypes' => ['stringBased' => '123', 'optionalInt' => 'not an int']], 'className' => Fixture\NestedShape::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Required', 'path' => ['shapeWithOptionalTypes', 'stringBasedOrNull'], 'expected' => 'FamilyName|null', 'received' => 'undefined'], ['code' => 'invalid_type', 'message' => 'Required', 'path' => ['shapeWithOptionalTypes', 'stringOrNull'], 'expected' => 'string|null', 'received' => 'undefined'], ['code' => 'invalid_type', 'message' => 'Required', 'path' => ['shapeWithOptionalTypes', 'givenNamesOrNull'], 'expected' => 'GivenNames|null', 'received' => 'undefined'], ['code' => 'invalid_type', 'message' => 'Expected integer, received string', 'path' => ['shapeWithOptionalTypes', 'optionalInt'], 'expected' => 'integer', 'received' => 'string'], ['code' => 'invalid_type', 'message' => 'Required', 'path' => ['shapeWithBool'], 'expected' => 'object', 'received' => 'undefined']]];

        yield 'with array property from non-iterable' => ['value' => ['givenName' => 'John', 'someArray' => new stdClass()], 'className' => Fixture\ShapeWithArray::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected array, received object', 'path' => ['someArray'], 'expected' => 'array', 'received' => 'object']]];

        yield 'from array missing type discriminator for interface property' => ['value' => ['property' => ['value' => 'John']], 'className' => Fixture\ShapeWithInterfaceProperty::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing discriminator key "__type"', 'path' => ['property'], 'params' => []]]];
        yield 'from array missing custom type discriminator for interface property' => ['value' => ['property' => ['value' => 'John']], 'className' => Fixture\ShapeWithInterfacePropertyAndDiscriminator::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing discriminator key "type"', 'path' => ['property'], 'params' => []]]];
        yield 'from array missing custom type discriminator for interface property without mapping' => ['value' => ['property' => ['value' => 'John']], 'className' => Fixture\ShapeWithInterfacePropertyAndDiscriminatorWithoutMapping::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing discriminator key "type"', 'path' => ['property'], 'params' => []]]];
        yield 'from array with invalid discriminator type for optional interface property' => ['value' => ['property' => ['value' => 'John', 'type' => 'fullName']], 'className' => Fixture\ShapeWithOptionalInterfacePropertyAndCustomDiscriminator::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Discriminator key "type" has to be one of "givenName", "familyName". Got: "fullName"', 'path' => ['property'], 'params' => []]]];
        yield 'from array missing custom type discriminator for union property' => ['value' => ['givenOrFamilyName' => ['value' => 'John']], 'className' => Fixture\ShapeWithUnionTypeAndDiscriminator::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing discriminator key "type"', 'path' => ['givenOrFamilyName'], 'params' => []]]];
        yield 'from array missing custom type discriminator for union property without mapping' => ['value' => ['givenOrFamilyName' => ['value' => 'John']], 'className' => Fixture\ShapeWithUnionTypeAndDiscriminatorWithoutMapping::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing discriminator key "type"', 'path' => ['givenOrFamilyName'], 'params' => []]]];
    }

    /**
     * @param class-string<object> $className
     * @param array<CoercionIssue[]> $expectedIssues
     */
    #[DataProvider('instantiate_shape_object_failing_dataProvider')]
    public function test_instantiate_shape_object_failing(mixed $value, string $className, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate($className, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
    }

    public function test_instantiate_shape_object_fails_if_discriminator_mapping_cannot_be_resolved_to_a_className(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Class "ShapeWithInvalidDiscriminatorAttribute" has a Wwwision\Types\Attributes\Discriminator attribute for property "givenName" but the corresponding property schema is of type Wwwision\Types\Schema\StringSchema which is not one of the supported schema types Wwwision\Types\Schema\OneOfSchema, Wwwision\Types\Schema\InterfaceSchema');
        Parser::instantiate(Fixture\ShapeWithInvalidDiscriminatorAttribute::class, ['givenName' => ['type' => 'given', '__value' => 'does not matter']]);
    }

    public function test_instantiate_shape_object_fails_if_discriminator_attribute_is_added_to_optional_property_of_invalid_type(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Class "ShapeWithInvalidDiscriminatorAttributeOnOptionalProperty" incorrectly has a Wwwision\Types\Attributes\Discriminator attribute for property "givenName": The schema for type "GivenName" is of type Wwwision\Types\Schema\StringSchema which is not one of the supported schema types Wwwision\Types\Schema\OneOfSchema, Wwwision\Types\Schema\InterfaceSchema');
        Parser::instantiate(Fixture\ShapeWithInvalidDiscriminatorAttributeOnOptionalProperty::class, ['givenName' => ['type' => 'given', '__value' => 'does not matter']]);
    }

    public static function instantiate_shape_object_dataProvider(): Generator
    {
        yield 'from array matching all constraints' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Some last name'], 'className' => Fixture\FullName::class, 'expectedResult' => '{"givenName":"Some first name","familyName":"Some last name"}'];
        yield 'from iterable matching all constraints' => ['value' => new ArrayIterator(['givenName' => 'Some first name', 'familyName' => 'Some last name']), 'className' => Fixture\FullName::class, 'expectedResult' => '{"givenName":"Some first name","familyName":"Some last name"}'];
        yield 'from array without optionals' => ['value' => ['stringBased' => 'Some value', 'stringBasedOrNull' => null, 'stringOrNull' => null, 'givenNamesOrNull' => null], 'className' => Fixture\ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":"Some value","stringBasedOrNull":null,"stringOrNull":null,"givenNamesOrNull":null}'];
        yield 'from array with optionals' => ['value' => ['stringBased' => 'Some value', 'stringBasedOrNull' => null, 'stringOrNull' => 'some string', 'givenNamesOrNull' => ['John', 'Jane'], 'optionalString' => 'optionalString value', 'optionalStringBased' => 'oSB value', 'optionalInt' => 42, 'optionalBool' => true], 'className' => Fixture\ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":"Some value","stringBasedOrNull":null,"stringOrNull":"some string","givenNamesOrNull":["John","Jane"],"optionalStringBased":"oSB value","optionalInt":42,"optionalBool":true,"optionalString":"optionalString value"}'];
        yield 'from array with optionals and coercion' => ['value' => ['stringBased' => 'Some value', 'stringBasedOrNull' => null, 'stringOrNull' => null, 'givenNamesOrNull' => null, 'optionalString' => new class {
            public function __toString()
            {
                return 'optionalString value';
            }
        }, 'optionalStringBased' => 'oSB value', 'optionalInt' => '123', 'optionalBool' => 1], 'className' => Fixture\ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":"Some value","stringBasedOrNull":null,"stringOrNull":null,"givenNamesOrNull":null,"optionalStringBased":"oSB value","optionalInt":123,"optionalBool":true,"optionalString":"optionalString value"}'];
        yield 'from array with optionals and coercion 2' => ['value' => ['stringBased' => 'Some value', 'stringBasedOrNull' => 'Some value', 'stringOrNull' => 'some string', 'givenNamesOrNull' => instantiate(Fixture\GivenNames::class, ['Jane', 'John']), 'optionalString' => new class {
            public function __toString()
            {
                return 'optionalString value';
            }
        }, 'optionalStringBased' => 'oSB value', 'optionalInt' => 55.0, 'optionalBool' => '0'], 'className' => Fixture\ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":"Some value","stringBasedOrNull":"Some value","stringOrNull":"some string","givenNamesOrNull":["Jane","John"],"optionalStringBased":"oSB value","optionalInt":55,"optionalBool":false,"optionalString":"optionalString value"}'];
        yield 'from array with optionals and coercion 3' => ['value' => ['stringBased' => 'Some value', 'stringBasedOrNull' => 'Some value', 'stringOrNull' => 'some string', 'givenNamesOrNull' => instantiate(Fixture\GivenNames::class, ['Jane', 'John']), 'optionalString' => null, 'optionalStringBased' => 'oSB value', 'optionalInt' => 55.0, 'optionalBool' => 'false', 'boolWithDefault' => 'true'], 'className' => Fixture\ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":"Some value","stringBasedOrNull":"Some value","stringOrNull":"some string","givenNamesOrNull":["Jane","John"],"optionalStringBased":"oSB value","optionalInt":55,"optionalBool":false,"boolWithDefault":true}'];
        yield 'from array with null-values for optionals' => ['value' => ['stringBased' => 'Some value', 'stringBasedOrNull' => null, 'stringOrNull' => null, 'givenNamesOrNull' => null, 'optionalBool' => null], 'className' => Fixture\ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":"Some value","stringBasedOrNull":null,"stringOrNull":null,"givenNamesOrNull":null}'];
        yield 'from array to shape with floats' => ['value' => ['latitude' => 33, 'longitude' => '123.45'], 'className' => Fixture\GeoCoordinates::class, 'expectedResult' => '{"longitude":123.45,"latitude":33}'];
        $class = new stdClass();
        $class->givenName = 'Some first name';
        $class->familyName = 'Some last name';
        yield 'from stdClass matching all constraints' => ['value' => $class, 'className' => Fixture\FullName::class, 'expectedResult' => '{"givenName":"Some first name","familyName":"Some last name"}'];

        yield 'with array property' => ['value' => ['givenName' => 'John', 'someArray' => ['some', 'array', 'values']], 'className' => Fixture\ShapeWithArray::class, 'expectedResult' => '{"givenName":"John","someArray":["some","array","values"]}'];
        yield 'with array property from iterable' => ['value' => ['givenName' => 'Jane', 'someArray' => new ArrayIterator(['some', 'iterable', 'values'])], 'className' => Fixture\ShapeWithArray::class, 'expectedResult' => '{"givenName":"Jane","someArray":["some","iterable","values"]}'];

        yield 'with union type' => ['value' => ['givenOrFamilyName' => ['__type' => Fixture\GivenName::class, '__value' => 'Jane']], 'className' => Fixture\ShapeWithUnionType::class, 'expectedResult' => '{"givenOrFamilyName":"Jane"}'];
        yield 'with simple union type (integer)' => ['value' => ['integerOrString' => 123], 'className' => Fixture\ShapeWithSimpleUnionType::class, 'expectedResult' => '{"integerOrString":123}'];
        yield 'with simple union type (string)' => ['value' => ['integerOrString' => 'foo'], 'className' => Fixture\ShapeWithSimpleUnionType::class, 'expectedResult' => '{"integerOrString":"foo"}'];

        yield 'from array for shape with interface property' => ['value' => ['property' => ['__type' => Fixture\GivenName::class, '__value' => 'Jane']], 'className' => Fixture\ShapeWithInterfaceProperty::class, 'expectedResult' => '{"property":{"__type":"Wwwision\\\Types\\\Tests\\\Fixture\\\GivenName","__value":"Jane"}}'];
        yield 'from array for shape with interface property with discriminator' => ['value' => ['property' => ['type' => 'a', '__value' => 'A']], 'className' => Fixture\ShapeWithInterfacePropertyAndDiscriminator::class, 'expectedResult' => '{"property":{"type":"a","__value":"A"}}'];
        yield 'from array for shape with interface property with discriminator without mapping' => ['value' => ['property' => ['type' => Fixture\ImplementationBOfInterfaceWithDiscriminator::class, '__value' => 'B']], 'className' => Fixture\ShapeWithInterfacePropertyAndDiscriminatorWithoutMapping::class, 'expectedResult' => '{"property":{"type":"Wwwision\\\Types\\\Tests\\\Fixture\\\ImplementationBOfInterfaceWithDiscriminator","__value":"B"}}'];
        yield 'from array for shape with union type property and discriminator' => ['value' => ['givenOrFamilyName' => ['type' => 'given', '__value' => 'Jane']], 'className' => Fixture\ShapeWithUnionTypeAndDiscriminator::class, 'expectedResult' => '{"givenOrFamilyName":{"type":"given","__value":"Jane"}}'];
        yield 'from array for shape with union type property and discriminator without mapping' => ['value' => ['givenOrFamilyName' => ['type' => Fixture\GivenName::class, '__value' => 'Jane']], 'className' => Fixture\ShapeWithUnionTypeAndDiscriminatorWithoutMapping::class, 'expectedResult' => '{"givenOrFamilyName":{"type":"Wwwision\\\Types\\\Tests\\\Fixture\\\GivenName","__value":"Jane"}}'];
        yield 'from array for shape with optional interface property and custom discriminator' => ['value' => ['property' => ['type' => 'givenName', '__value' => 'Jane']], 'className' => Fixture\ShapeWithOptionalInterfacePropertyAndCustomDiscriminator::class, 'expectedResult' => '{"property":{"type":"givenName","__value":"Jane"}}'];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('instantiate_shape_object_dataProvider')]
    public function test_instantiate_shape_object(mixed $value, string $className, string $expectedResult): void
    {
        $instance = instantiate($className, $value);
        $actualResult = (new Normalizer())->toJson($instance);
        self::assertSame($expectedResult, $actualResult);
    }

    public static function instantiate_string_based_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => Fixture\GivenName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received null', 'path' => [], 'expected' => 'string', 'received' => 'null']]];
        yield 'from object' => ['value' => new stdClass(), 'className' => Fixture\GivenName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received object', 'path' => [], 'expected' => 'string', 'received' => 'object']]];
        yield 'from boolean' => ['value' => false, 'className' => Fixture\GivenName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received boolean', 'path' => [], 'expected' => 'string', 'received' => 'boolean']]];
        yield 'from float' => ['value' => 2.0, 'className' => Fixture\GivenName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received double', 'path' => [], 'expected' => 'string', 'received' => 'double']]];

        yield 'from string violating minLength' => ['value' => 'ab', 'className' => Fixture\GivenName::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => [], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false]]];
        yield 'from string violating maxLength' => ['value' => 'This is a bit too long', 'className' => Fixture\GivenName::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'String must contain at most 20 character(s)', 'path' => [], 'type' => 'string', 'maximum' => 20, 'inclusive' => true, 'exact' => false]]];
        yield 'from string violating pattern' => ['value' => 'magic foo', 'className' => Fixture\NotMagic::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Value does not match regular expression', 'path' => [], 'validation' => 'regex']]];

        yield 'from string violating format "date_time"' => ['value' => 'not.a.date', 'className' => Fixture\DateTime::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid date_time', 'path' => [], 'validation' => 'date_time']]];
        yield 'from string violating format "date_time" because time part is missing' => ['value' => '2025-02-15', 'className' => Fixture\DateTime::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid date_time', 'path' => [], 'validation' => 'date_time']]];

        yield 'from string violating format "duration"' => ['value' => 'not.a.duration', 'className' => Fixture\Duration::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid duration', 'path' => [], 'validation' => 'duration']]];
        yield 'from string violating format "duration" empty components' => ['value' => 'PT', 'className' => Fixture\Duration::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid duration', 'path' => [], 'validation' => 'duration']]];
        yield 'from string violating format "duration" missing time component' => ['value' => 'P3MT', 'className' => Fixture\Duration::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid duration', 'path' => [], 'validation' => 'duration']]];

        yield 'from string violating format "idn_email"' => ['value' => 'not.an.idn.email', 'className' => Fixture\IdnEmailAddress::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid idn_email', 'path' => [], 'validation' => 'idn_email']]];
        yield 'from string violating format "idn_email" more than one @' => ['value' => 'not@an@idn.email', 'className' => Fixture\IdnEmailAddress::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid idn_email', 'path' => [], 'validation' => 'idn_email']]];

        yield 'from string violating format "email"' => ['value' => 'not.an@email', 'className' => Fixture\EmailAddress::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid email', 'path' => [], 'validation' => 'email']]];

        yield 'from string violating format "hostname"' => ['value' => 'not.a.hostname', 'className' => Fixture\Hostname::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid hostname', 'path' => [], 'validation' => 'hostname']]];
        yield 'from string violating format "hostname" because it is numeric' => ['value' => '01010', 'className' => Fixture\Hostname::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid hostname', 'path' => [], 'validation' => 'hostname']]];
        yield 'from string violating format "hostname" because it ends with a dash' => ['value' => 'A0c-', 'className' => Fixture\Hostname::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid hostname', 'path' => [], 'validation' => 'hostname']]];
        yield 'from string violating format "hostname" because it starts with a dash' => ['value' => '-A0c', 'className' => Fixture\Hostname::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid hostname', 'path' => [], 'validation' => 'hostname']]];
        yield 'from string violating format "hostname" because it exceeds max length' => ['value' => 'o123456701234567012345670123456701234567012345670123456701234567', 'className' => Fixture\Hostname::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid hostname', 'path' => [], 'validation' => 'hostname']]];

        yield 'from string violating format "ipv4"' => ['value' => 'not.an.ipv4', 'className' => Fixture\Ipv4::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid ipv4', 'path' => [], 'validation' => 'ipv4']]];

        yield 'from string violating format "ipv6"' => ['value' => 'not.an.ipv6', 'className' => Fixture\Ipv6::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid ipv6', 'path' => [], 'validation' => 'ipv6']]];

        yield 'from string violating format "regex"' => ['value' => '(not.a.regex', 'className' => Fixture\Regex::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid regex', 'path' => [], 'validation' => 'regex']]];

        yield 'from string violating format "time"' => ['value' => 'not.a.time', 'className' => Fixture\Time::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid time', 'path' => [], 'validation' => 'time']]];
        yield 'from string violating format "time" because value contains date part' => ['value' => '2025-02-15Z13:12:11', 'className' => Fixture\Time::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid time', 'path' => [], 'validation' => 'time']]];

        yield 'from string violating format "uri"' => ['value' => 'not.a.uri', 'className' => Fixture\Uri::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid uri', 'path' => [], 'validation' => 'uri']]];
        yield 'from string violating format "date"' => ['value' => 'not.a.date', 'className' => Fixture\Date::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid date', 'path' => [], 'validation' => 'date']]];
        yield 'from string violating format "date" because value contains time part' => ['value' => '2025-02-15Z13:12:11', 'className' => Fixture\Date::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid date', 'path' => [], 'validation' => 'date']]];
        yield 'from string custom "date" validation' => ['value' => (new DateTimeImmutable('+1 day'))->format('Y-m-d'), 'className' => Fixture\Date::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Future dates are not allowed', 'path' => [], 'params' => ['some' => 'param']]]];
        yield 'from string violating format "uuid"' => ['value' => 'not.a.uuid', 'className' => Fixture\Uuid::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid uuid', 'path' => [], 'validation' => 'uuid']]];

        yield 'from string violating multiple constraints' => ['value' => 'invalid', 'className' => Fixture\ImpossibleString::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'String must contain at least 10 character(s)', 'path' => [], 'type' => 'string', 'minimum' => 10, 'inclusive' => true, 'exact' => false], ['code' => 'too_big', 'message' => 'String must contain at most 2 character(s)', 'path' => [], 'type' => 'string', 'maximum' => 2, 'inclusive' => true, 'exact' => false], ['code' => 'invalid_string', 'message' => 'Value does not match regular expression', 'path' => [], 'validation' => 'regex'], ['code' => 'invalid_string', 'message' => 'Invalid email', 'path' => [], 'validation' => 'email']]];
    }

    /**
     * @param class-string<object> $className
     * @param array<CoercionIssue[]> $expectedIssues
     */
    #[DataProvider('instantiate_string_based_object_failing_dataProvider')]
    public function test_instantiate_string_based_object_failing(mixed $value, string $className, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate($className, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
    }

    public static function instantiate_string_based_object_dataProvider(): Generator
    {
        yield 'from string that matches constraints' => ['value' => 'this is valid', 'className' => Fixture\GivenName::class, 'expectedResult' => 'this is valid'];
        yield 'from string that matches pattern' => ['value' => 'this is not magic', 'className' => Fixture\NotMagic::class, 'expectedResult' => 'this is not magic'];
        yield 'from integer' => ['value' => 123, 'className' => Fixture\NotMagic::class, 'expectedResult' => '123'];
        yield 'from stringable object' => ['value' => new class {
            public function __toString()
            {
                return 'from object';
            }
        }, 'className' => Fixture\GivenName::class, 'expectedResult' => 'from object'];

        yield 'from string matching format "date"' => ['value' => '1980-12-13', 'className' => Fixture\Date::class, 'expectedResult' => '1980-12-13'];

        yield 'from string matching format "date_time"' => ['value' => '2018-11-13T20:20:39+00:00', 'className' => Fixture\DateTime::class, 'expectedResult' => '2018-11-13T20:20:39+00:00'];
        yield 'from string matching format "date_time" with UTC zone designator' => ['value' => '2018-11-13T20:20:39Z', 'className' => Fixture\DateTime::class, 'expectedResult' => '2018-11-13T20:20:39Z'];

        yield 'from string matching format "duration"' => ['value' => 'P2MT30M', 'className' => Fixture\Duration::class, 'expectedResult' => 'P2MT30M'];
        yield 'from string matching format "duration" time component only' => ['value' => 'PT6H', 'className' => Fixture\Duration::class, 'expectedResult' => 'PT6H'];

        yield 'from string matching format "hostname"' => ['value' => 'ab-cd', 'className' => Fixture\Hostname::class, 'expectedResult' => 'ab-cd'];
        yield 'from string matching format "hostname" with max length' => ['value' => 'o12345670123456701234567012345670123456701234567012345670123456', 'className' => Fixture\Hostname::class, 'expectedResult' => 'o12345670123456701234567012345670123456701234567012345670123456'];

        yield 'from string matching format "idn_email"' => ['value' => 'válid@émail.com', 'className' => Fixture\IdnEmailAddress::class, 'expectedResult' => 'válid@émail.com'];

        yield 'from string matching format "ipv4"' => ['value' => '127.0.0.1', 'className' => Fixture\Ipv4::class, 'expectedResult' => '127.0.0.1'];

        yield 'from string matching format "ipv6"' => ['value' => '2001:0db8:85a3:08d3:1319:8a2e:0370:7334', 'className' => Fixture\Ipv6::class, 'expectedResult' => '2001:0db8:85a3:08d3:1319:8a2e:0370:7334'];

        yield 'from string matching format "regex"' => ['value' => '[0-9]{1,3}', 'className' => Fixture\Regex::class, 'expectedResult' => '[0-9]{1,3}'];

        yield 'from string matching format "email"' => ['value' => 'a.valid@email.com', 'className' => Fixture\EmailAddress::class, 'expectedResult' => 'a.valid@email.com'];

        yield 'from string matching format "time"' => ['value' => '20:20:39+00:00', 'className' => Fixture\Time::class, 'expectedResult' => '20:20:39+00:00'];
        yield 'from string matching format "time" without timezone offset' => ['value' => '20:20:39', 'className' => Fixture\Time::class, 'expectedResult' => '20:20:39'];
        yield 'from string matching format "time" with UTC zone designator' => ['value' => '20:20:39Z', 'className' => Fixture\Time::class, 'expectedResult' => '20:20:39Z'];
        yield 'from string matching format "uri"' => ['value' => 'https://www.some-domain.tld', 'className' => Fixture\Uri::class, 'expectedResult' => 'https://www.some-domain.tld'];
        yield 'from string matching format "uuid"' => ['value' => '3cafa54b-f9c3-4470-8c0e-31612cb70f61', 'className' => Fixture\Uuid::class, 'expectedResult' => '3cafa54b-f9c3-4470-8c0e-31612cb70f61'];
    }

    /**
     * @param class-string<object{value:mixed}> $className
     */
    #[DataProvider('instantiate_string_based_object_dataProvider')]
    public function test_instantiate_string_based_object(mixed $value, string $className, string $expectedResult): void
    {
        self::assertSame($expectedResult, instantiate($className, $value)->value);
    }

    public function test_instantiate_interface_object_fails_if_discriminator_mapping_cannot_be_resolved_to_a_className(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Discriminator mapping of type "InterfaceWithDiscriminator" refers to non-existing class "NoClassName"');
        Parser::instantiate(Fixture\InterfaceWithDiscriminator::class, ['t' => 'invalid', '__value' => 'does not matter']);
    }

    public function test_instantiate_interface_object_fails_if_discriminator_key_is_ambiguous(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Discriminator key "type" of type "InterfaceWithAmbiguousDiscriminator" is ambiguous with the property "type" of implementation "Wwwision\Types\Tests\Fixture\ShapeWithPropertyOfNameType"');
        Parser::instantiate(Fixture\InterfaceWithAmbiguousDiscriminator::class, ['type' => Fixture\ShapeWithPropertyOfNameType::class, 'flag' => true]);
    }

    public static function instantiate_interface_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => Fixture\SomeInterface::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received null', 'path' => [], 'expected' => 'interface', 'received' => 'null']]];
        yield 'from object' => ['value' => new stdClass(), 'className' => Fixture\SomeInterface::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing discriminator key "__type"', 'path' => [], 'params' => []]]];
        yield 'from boolean' => ['value' => false, 'className' => Fixture\SomeInterface::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received boolean', 'path' => [], 'expected' => 'interface', 'received' => 'boolean']]];
        yield 'from integer' => ['value' => 1234, 'className' => Fixture\SomeInterface::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received integer', 'path' => [], 'expected' => 'interface', 'received' => 'integer']]];
        yield 'from float' => ['value' => 2.0, 'className' => Fixture\SomeInterface::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received double', 'path' => [], 'expected' => 'interface', 'received' => 'double']]];
        yield 'from array without __type' => ['value' => ['someKey' => 'someValue'], 'className' => Fixture\SomeInterface::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing discriminator key "__type"', 'path' => [], 'params' => []]]];
        yield 'from array with invalid __type' => ['value' => ['__type' => 123], 'className' => Fixture\SomeInterface::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Discriminator key "__type" has to be a string, got: int', 'path' => [], 'params' => []]]];
        yield 'from array with unknown __type' => ['value' => ['__type' => 'NoClassName'], 'className' => Fixture\SomeInterface::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Discriminator key "__type" has to be a valid class name, got: "NoClassName"', 'path' => [], 'params' => []]]];
        yield 'from array with __type that is not an instance of the interface' => ['value' => ['__type' => Fixture\ShapeWithInt::class, 'value' => '123'], 'className' => Fixture\SomeInterface::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'The given "__type" of "Wwwision\\Types\\Tests\\Fixture\\ShapeWithInt" is not an implementation of SomeInterface', 'path' => [], 'params' => []]]];
        yield 'from array with valid __type but invalid remaining values' => ['value' => ['__type' => Fixture\GivenName::class], 'className' => Fixture\SomeInterface::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received array', 'path' => [], 'expected' => 'string', 'received' => 'array']]];
        yield 'from array with valid __type but missing properties' => ['value' => ['__type' => Fixture\FullName::class, 'givenName' => 'John'], 'className' => Fixture\SomeInterface::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Required', 'path' => ['familyName'], 'expected' => 'string', 'received' => 'undefined']]];
        yield 'from array with invalid discriminator property name' => ['value' => ['__type' => Fixture\GivenName::class, '__value' => 'John'], 'className' => Fixture\InterfaceWithDiscriminator::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing discriminator key "t"', 'path' => [], 'params' => []]]];
        yield 'from array with invalid discriminator property value' => ['value' => ['t' => Fixture\GivenName::class, '__value' => 'John'], 'className' => Fixture\InterfaceWithDiscriminator::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Discriminator key "t" has to be one of "implementationA", "implementationB", "empty", "invalid". Got: "Wwwision\\Types\\Tests\\Fixture\\GivenName"', 'path' => [], 'params' => []]]];
    }

    /**
     * @param class-string<object> $className
     * @param array<CoercionIssue[]> $expectedIssues
     */
    #[DataProvider('instantiate_interface_object_failing_dataProvider')]
    public function test_instantiate_interface_object_failing(mixed $value, string $className, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate($className, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
    }

    public static function instantiate_interface_object_dataProvider(): Generator
    {
        yield 'from array with __type and __value' => ['value' => ['__type' => Fixture\GivenName::class, '__value' => 'this is valid'], 'className' => Fixture\SomeInterface::class, 'expectedResult' => '"this is valid"'];
        yield 'from iterable with __type and __value' => ['value' => new ArrayIterator(['__type' => Fixture\GivenName::class, '__value' => 'this is valid']), 'className' => Fixture\SomeInterface::class, 'expectedResult' => '"this is valid"'];
        yield 'from array and remaining values' => ['value' => ['__type' => Fixture\FullName::class, 'givenName' => 'some given name', 'familyName' => 'some family name'], 'className' => Fixture\SomeInterface::class, 'expectedResult' => '{"givenName":"some given name","familyName":"some family name"}'];
        yield 'from valid implementation' => ['value' => Parser::instantiate(Fixture\GivenName::class, 'John'), 'className' => Fixture\SomeInterface::class, 'expectedResult' => '"John"'];

        yield 'with discriminator from array with discriminator property and __value' => ['value' => ['t' => 'implementationA', '__value' => 'this is valid'], 'className' => Fixture\InterfaceWithDiscriminator::class, 'expectedResult' => '{"t":"implementationA","__value":"this is valid"}'];
        yield 'with discriminator from array with discriminator property and no remaining values' => ['value' => ['t' => 'empty'], 'className' => Fixture\InterfaceWithDiscriminator::class, 'expectedResult' => '{"t":"empty"}'];
        yield 'with discriminator from valid implementation' => ['value' => Parser::instantiate(Fixture\ImplementationBOfInterfaceWithDiscriminator::class, 'Valid'), 'className' => Fixture\InterfaceWithDiscriminator::class, 'expectedResult' => '{"t":"implementationB","__value":"Valid"}'];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('instantiate_interface_object_dataProvider')]
    public function test_instantiate_interface_object(mixed $value, string $className, string $expectedResult): void
    {
        $actualResult = (new Normalizer())->toJson(instantiate($className, $value));
        self::assertJsonStringEqualsJsonString($expectedResult, $actualResult);
    }

    public function test_interface_implementationSchemas(): void
    {
        $interfaceSchema = Parser::getSchema(Fixture\SomeInterface::class);
        self::assertInstanceOf(InterfaceSchema::class, $interfaceSchema);

        $implementationSchemaNames = array_map(static fn(Schema $schema) => $schema->getName(), $interfaceSchema->implementationSchemas());
        self::assertSame(['GivenName', 'FamilyName', 'FullName'], $implementationSchemaNames);
    }

    public static function objects_dataProvider(): Generator
    {
        yield 'enum' => ['instance' => Fixture\Title::MR];
        yield 'integer' => ['instance' => Parser::instantiate(Fixture\Age::class, 55)];
        yield 'list' => ['instance' => Parser::instantiate(Fixture\GivenNames::class, ['John', 'Jane', 'Max'])];
        yield 'shape' => ['instance' => Parser::instantiate(Fixture\FullName::class, ['givenName' => 'John', 'familyName' => 'Doe'])];
        yield 'string' => ['instance' => Parser::instantiate(Fixture\GivenName::class, 'Jane')];
    }

    #[DataProvider('objects_dataProvider')]
    public function test_instantiate_returns_same_object_if_it_is_already_a_valid_type(object $instance): void
    {
        self::assertSame($instance, Parser::getSchema($instance::class)->instantiate($instance));
    }

    public function test_instantiate_returns_same_instance_if_object_implements_interface_of_schema(): void
    {
        $instance = Parser::instantiate(Fixture\GivenName::class, 'John');
        self::assertSame($instance, Parser::getSchema(Fixture\SomeInterface::class)->instantiate($instance));
    }

    public function test_interface_schema_discriminator_can_be_changed(): void
    {
        /** @var InterfaceSchema $interfaceSchema */
        $interfaceSchema = Parser::getSchema(Fixture\SomeInterface::class);
        $discriminator = new Discriminator('type', ['givenName' => Fixture\GivenName::class, 'familyName' => Fixture\FamilyName::class]);
        $interfaceSchema = $interfaceSchema->withDiscriminator($discriminator);

        self::assertSame($discriminator, $interfaceSchema->discriminator);
    }

    public function test_oneOfSchema_type(): void
    {
        $mockSubSchemas = [
            $this->getMockBuilder(Schema::class)->getMock(),
            $this->getMockBuilder(Schema::class)->getMock(),
        ];
        $oneOfSchema = new OneOfSchema($mockSubSchemas, null, null);
        $mockSubSchemas[0]->expects(self::once())->method('getName')->willReturn('Type1');
        $mockSubSchemas[1]->expects(self::once())->method('getName')->willReturn('Type2');
        self::assertSame('Type1|Type2', $oneOfSchema->getType());
    }

    public function test_oneOfSchema_isInstance_returns_true_if_value_is_instance_of_one_subSchema(): void
    {
        $mockSubSchemas = [
            $this->getMockBuilder(Schema::class)->getMock(),
            $this->getMockBuilder(Schema::class)->getMock(),
        ];
        $oneOfSchema = new OneOfSchema($mockSubSchemas, null, null);
        $value = 'some value';
        $mockSubSchemas[0]->expects(self::once())->method('isInstance')->with($value)->willReturn(false);
        $mockSubSchemas[1]->expects(self::once())->method('isInstance')->with($value)->willReturn(true);
        self::assertTrue($oneOfSchema->isInstance($value));
    }

    public function test_oneOfSchema_isInstance_returns_false_if_value_is_no_instance_of_any_subSchemas(): void
    {
        $mockSubSchemas = [
            $this->getMockBuilder(Schema::class)->getMock(),
            $this->getMockBuilder(Schema::class)->getMock(),
        ];
        $oneOfSchema = new OneOfSchema($mockSubSchemas, null, null);
        $value = 'some value';
        $mockSubSchemas[0]->expects(self::once())->method('isInstance')->with($value)->willReturn(false);
        $mockSubSchemas[1]->expects(self::once())->method('isInstance')->with($value)->willReturn(false);
        self::assertFalse($oneOfSchema->isInstance($value));
    }

    public static function instantiate_oneOf_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected GivenName|FamilyName, received null', 'path' => [], 'expected' => 'GivenName|FamilyName', 'received' => 'null']]];
        yield 'from object' => ['value' => new stdClass(), 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing discriminator key "__type"', 'path' => [], 'params' => []]]];
        yield 'from boolean' => ['value' => false, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected GivenName|FamilyName, received boolean', 'path' => [], 'expected' => 'GivenName|FamilyName', 'received' => 'boolean']]];
        yield 'from integer' => ['value' => 1234, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected GivenName|FamilyName, received integer', 'path' => [], 'expected' => 'GivenName|FamilyName', 'received' => 'integer']]];
        yield 'from float' => ['value' => 2.0, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected GivenName|FamilyName, received double', 'path' => [], 'expected' => 'GivenName|FamilyName', 'received' => 'double']]];
        yield 'from array without __type' => ['value' => ['someKey' => 'someValue'], 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing discriminator key "__type"', 'path' => [], 'params' => []]]];
        yield 'from array with invalid __type' => ['value' => ['__type' => 123], 'expectedIssues' => [['code' => 'custom', 'message' => 'Discriminator key "__type" has to be a string, got: int', 'path' => [], 'params' => []]]];
        yield 'from array with unknown __type' => ['value' => ['__type' => 'NoClassName'], 'expectedIssues' => [['code' => 'custom', 'message' => 'Discriminator key "__type" has to be a valid class name, got: "NoClassName"', 'path' => [], 'params' => []]]];
        yield 'from array with __type that is not an instance of the union' => ['value' => ['__type' => Fixture\ShapeWithInt::class, 'value' => '123'], 'expectedIssues' => [['code' => 'custom', 'message' => 'The given "__type" of "Wwwision\\Types\\Tests\\Fixture\\ShapeWithInt" is not an implementation of GivenName|FamilyName', 'path' => [], 'params' => []]]];
        yield 'from array with valid __type but invalid remaining values' => ['value' => ['__type' => Fixture\GivenName::class], 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing keys for union of type GivenName|FamilyName', 'path' => [], 'params' => []]]];
        yield 'from array with valid __type but missing properties' => ['value' => ['__type' => Fixture\FullName::class, 'givenName' => 'John'], 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Required', 'path' => ['familyName'], 'expected' => 'string', 'received' => 'undefined']]];
    }

    /**
     * @param array<CoercionIssue[]> $expectedIssues
     */
    #[DataProvider('instantiate_oneOf_failing_dataProvider')]
    public function test_instantiate_oneOf_failing(mixed $value, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        $oneOfSchema = new OneOfSchema([
            Parser::getSchema(Fixture\GivenName::class),
            Parser::getSchema(Fixture\FamilyName::class),
        ], null, null);
        try {
            $oneOfSchema->instantiate($value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
    }

    public function test_instantiate_oneOf_object_with_discriminator_fails_if_discriminator_propertyName_is_invalid(): void
    {
        $this->expectException(CoerceException::class);
        $this->expectExceptionMessage('Failed to cast value of type array to ShapeWithUnionTypeAndDiscriminator: At "givenOrFamilyName": custom (Missing discriminator key "type")');
        Parser::instantiate(Fixture\ShapeWithUnionTypeAndDiscriminator::class, ['givenOrFamilyName' => ['__type' => 'familyName', '__value' => 'does not matter']]);
    }

    public function test_instantiate_oneOf_object_with_discriminator_fails_if_discriminator_value_is_invalid(): void
    {
        $this->expectException(CoerceException::class);
        $this->expectExceptionMessage('Failed to cast value of type array to ShapeWithUnionTypeAndDiscriminator: At "givenOrFamilyName": custom (Discriminator key "type" has to be one of "given", "family", "invalid". Got: "familyName"');
        Parser::instantiate(Fixture\ShapeWithUnionTypeAndDiscriminator::class, ['givenOrFamilyName' => ['type' => 'familyName', '__value' => 'does not matter']]);
    }

    public function test_instantiate_oneOf_object_with_discriminator_fails_if_discriminator_mapping_cannot_be_resolved_to_a_className(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Invalid schema for property "givenOrFamilyName" of type "ShapeWithUnionTypeAndDiscriminator": Discriminator mapping refers to non-existing class "NoClassName"');
        Parser::instantiate(Fixture\ShapeWithUnionTypeAndDiscriminator::class, ['givenOrFamilyName' => ['type' => 'invalid', '__value' => 'does not matter']]);
    }

    public function test_instantiate_oneOf_object_with_discriminator_fails_if_discriminator_is_ambiguous(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Invalid schema for property "someProperty" of type "ShapeWithUnionTypeAndAmbiguousDiscriminator": Discriminator key "type" of type "ShapeWithPropertyOfNameType|FamilyName" is ambiguous with the property "type" of implementation "Wwwision\Types\Tests\Fixture\ShapeWithPropertyOfNameType"');
        Parser::instantiate(Fixture\ShapeWithUnionTypeAndAmbiguousDiscriminator::class, ['someProperty' => ['type' => Fixture\ShapeWithPropertyOfNameType::class, 'flag' => false]]);
    }

    public function test_instantiate_oneOf_object_with_discriminator_without_mapping_fails_if_discriminator_propertyName_is_invalid(): void
    {
        $this->expectException(CoerceException::class);
        $this->expectExceptionMessage('Failed to cast value of type array to ShapeWithUnionTypeAndDiscriminatorWithoutMapping: At "givenOrFamilyName": custom (Missing discriminator key "type")');
        Parser::instantiate(Fixture\ShapeWithUnionTypeAndDiscriminatorWithoutMapping::class, ['givenOrFamilyName' => ['__type' => 'familyName', '__value' => 'does not matter']]);
    }

    public function test_instantiate_oneOf_object_with_discriminator_without_mapping_fails_if_discriminator_value_is_invalid(): void
    {
        $this->expectException(CoerceException::class);
        $this->expectExceptionMessage('Failed to cast value of type array to ShapeWithUnionTypeAndDiscriminatorWithoutMapping: At "givenOrFamilyName": custom (Discriminator key "type" has to be a valid class name, got: "family")');
        Parser::instantiate(Fixture\ShapeWithUnionTypeAndDiscriminatorWithoutMapping::class, ['givenOrFamilyName' => ['type' => 'family', '__value' => 'does not matter']]);
    }

    public function test_oneOf_discriminator_is_set_from_property_attribute(): void
    {
        /** @var ShapeSchema $shapeSchema */
        $shapeSchema = Parser::getSchema(Fixture\ShapeWithUnionTypeAndDiscriminatorWithoutMapping::class);
        $oneOfSchema = $shapeSchema->propertySchemas['givenOrFamilyName'];

        self::assertInstanceOf(OneOfSchema::class, $oneOfSchema);
        self::assertNotNull($oneOfSchema->discriminator);
        self::assertSame('type', $oneOfSchema->discriminator->propertyName);
        self::assertNull($oneOfSchema->discriminator->mapping);
    }

    public static function instantiate_oneOf_dataProvider(): Generator
    {
        yield 'from array with __type and __value' => ['value' => ['__type' => Fixture\GivenName::class, '__value' => 'this is valid'], 'expectedResult' => '"this is valid"'];
        yield 'from iterable with __type and __value' => ['value' => new ArrayIterator(['__type' => Fixture\GivenName::class, '__value' => 'this is valid']), 'expectedResult' => '"this is valid"'];
        yield 'from valid implementation' => ['value' => Parser::instantiate(Fixture\GivenName::class, 'John'), 'expectedResult' => '"John"'];
    }

    #[DataProvider('instantiate_oneOf_dataProvider')]
    public function test_instantiate_oneOf(mixed $value, string $expectedResult): void
    {
        $oneOfSchema = new OneOfSchema([
            Parser::getSchema(Fixture\GivenName::class),
            Parser::getSchema(Fixture\FamilyName::class),
        ], null, null);
        $instance = $oneOfSchema->instantiate($value);
        assert(is_object($instance));
        $actualResult = (new Normalizer())->toJson($instance);
        self::assertJsonStringEqualsJsonString($expectedResult, $actualResult);
    }

    public function test_instantiate_returns_same_instance_if_object_is_a_valid_oneOf_type(): void
    {
        $oneOfSchema = new OneOfSchema([
            Parser::getSchema(Fixture\GivenName::class),
            Parser::getSchema(Fixture\FamilyName::class),
        ], null, null);
        $instance = Parser::instantiate(Fixture\GivenName::class, 'John');
        self::assertSame($instance, $oneOfSchema->instantiate($instance));
    }

    public function test_oneOf_serialization(): void
    {
        $oneOfSchema = new OneOfSchema([
            Parser::getSchema(Fixture\GivenName::class),
            Parser::getSchema(Fixture\FamilyName::class),
        ], null, null);
        $expectedResult = '{
            "description": null,
            "name": "GivenName|FamilyName",
            "subSchemas": [
                {
                    "description": "First name of a person",
                    "maxLength": 20,
                    "minLength": 3,
                    "name": "GivenName",
                    "type": "string"
                },
                {
                    "description": "Last name of a person",
                    "maxLength": 20,
                    "minLength": 3,
                    "name": "FamilyName",
                    "type": "string"
                }
            ],
            "type": "GivenName|FamilyName"
        }';
        self::assertJsonStringEqualsJsonString($expectedResult, json_encode($oneOfSchema, JSON_THROW_ON_ERROR));
    }

    public function test_oneOf_schema_discriminator_can_be_changed(): void
    {
        $mockSubSchemas = [
            Parser::getSchema(Fixture\GivenName::class),
            Parser::getSchema(Fixture\FamilyName::class),
        ];
        $oneOfSchema = new OneOfSchema($mockSubSchemas, null, null);
        $discriminator = new Discriminator('type', ['givenName' => $mockSubSchemas[0]::class, 'familyName' => $mockSubSchemas[1]::class]);
        $oneOfSchema = $oneOfSchema->withDiscriminator($discriminator);

        self::assertSame($discriminator, $oneOfSchema->discriminator);
    }
}
