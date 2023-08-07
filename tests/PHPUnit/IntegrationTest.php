<?php

declare(strict_types=1);

namespace Wwwision\Types\Tests\PHPUnit;

use ArrayIterator;
use DateTimeImmutable;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Traversable;
use Wwwision\Types\Attributes\Description;
use Wwwision\Types\Attributes\IntegerBased;
use Wwwision\Types\Attributes\ListBased;
use Wwwision\Types\Attributes\StringBased;
use Wwwision\Types\Parser;
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
use Wwwision\Types\Schema\StringTypeFormat;
use function json_encode;
use function Wwwision\Types\instantiate;
use const JSON_THROW_ON_ERROR;

#[CoversClass(Parser::class)]
#[CoversClass(Description::class)]
#[CoversClass(IntegerBased::class)]
#[CoversClass(ListBased::class)]
#[CoversClass(StringBased::class)]
#[CoversClass(EnumCaseSchema::class)]
#[CoversClass(EnumSchema::class)]
#[CoversClass(IntegerSchema::class)]
#[CoversClass(ListSchema::class)]
#[CoversClass(LiteralBooleanSchema::class)]
#[CoversClass(LiteralIntegerSchema::class)]
#[CoversClass(LiteralStringSchema::class)]
#[CoversClass(OptionalSchema::class)]
#[CoversClass(ShapeSchema::class)]
#[CoversClass(StringSchema::class)]
#[CoversClass(StringTypeFormat::class)]
#[CoversClass(instantiate::class)]
final class IntegrationTest extends TestCase
{

    public function test_getSchema_throws_if_className_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to get schema for empty class name');
        Parser::getSchema('');
    }

    public function test_getSchema_throws_if_className_is_not_a_className(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to get schema for class ""notAClass"" because that class does not exist');
        Parser::getSchema('notAClass');
    }

    public function test_getSchema_throws_if_given_class_is_shape_with_invalid_properties(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to determine base type for "someProperty": Missing constructor in class "stdClass"');
        Parser::getSchema(ShapeWithInvalidObjectProperty::class);
    }

    public static function getSchema_dataProvider(): Generator
    {
        yield 'enum' => ['className' => Title::class, 'expectedResult' => '{"type":"enum","name":"Title","description":"honorific title of a person","cases":[{"type":"string","description":"for men, regardless of marital status, who do not have another professional or academic title","name":"MR","value":"MR"},{"type":"string","description":"for married women who do not have another professional or academic title","name":"MRS","value":"MRS"},{"type":"string","description":"for girls, unmarried women and married women who continue to use their maiden name","name":"MISS","value":"MISS"},{"type":"string","description":"for women, regardless of marital status or when marital status is unknown","name":"MS","value":"MS"},{"type":"string","description":"for any other title that does not match the above","name":"OTHER","value":"OTHER"}]}'];
        yield 'int backed enum' => ['className' => Number::class, 'expectedResult' => '{"type":"enum","name":"Number","description":"A number","cases":[{"type":"integer","description":"The number 1","name":"ONE","value":1},{"type":"integer","description":null,"name":"TWO","value":2},{"type":"integer","description":null,"name":"THREE","value":3}]}'];
        yield 'string backed enum' => ['className' => RomanNumber::class, 'expectedResult' => '{"type":"enum","name":"RomanNumber","description":null,"cases":[{"type":"string","description":null,"name":"I","value":"1"},{"type":"string","description":"random description","name":"II","value":"2"},{"type":"string","description":null,"name":"III","value":"3"},{"type":"string","description":null,"name":"IV","value":"4"}]}'];

        yield 'integer based object' => ['className' => Age::class, 'expectedResult' => '{"type":"integer","name":"Age","description":"The age of a person in years","minimum":1,"maximum":120}'];
        yield 'list object' => ['className' => FullNames::class, 'expectedResult' => '{"type":"array","name":"FullNames","description":null,"itemType":"FullName","minCount":2,"maxCount":5}'];
        yield 'shape object' => ['className' => FullName::class, 'expectedResult' => '{"type":"object","name":"FullName","description":"First and last name of a person","properties":[{"type":"GivenName","name":"givenName","description":"First name of a person"},{"type":"FamilyName","name":"familyName","description":"Last name of a person"}]}'];
        yield 'shape object with optional properties' => ['className' => ShapeWithOptionalTypes::class, 'expectedResult' => '{"type":"object","name":"ShapeWithOptionalTypes","description":null,"properties":[{"type":"FamilyName","name":"stringBased","description":"Last name of a person"},{"type":"FamilyName","name":"optionalStringBased","description":"Last name of a person","optional":true},{"type":"int","name":"optionalInt","description":"Some description","optional":true},{"type":"boolean","name":"optionalBool","description":null,"optional":true},{"type":"string","name":"optionalString","description":null,"optional":true}]}'];

        yield 'string based object' => ['className' => GivenName::class, 'expectedResult' => '{"type":"string","name":"GivenName","description":"First name of a person","minLength":3,"maxLength":20}'];
        yield 'string based object with format' => ['className' => EmailAddress::class, 'expectedResult' => '{"type":"string","name":"EmailAddress","description":null,"format":"email"}'];
        yield 'string based object with pattern' => ['className' => NotMagic::class, 'expectedResult' => '{"type":"string","name":"NotMagic","description":null,"pattern":"^(?!magic).*"}'];

        yield 'shape with bool' => ['className' => ShapeWithBool::class, 'expectedResult' => '{"type":"object","name":"ShapeWithBool","description":null,"properties":[{"type":"boolean","name":"value","description":"Description for literal bool"}]}'];
        yield 'shape with int' => ['className' => ShapeWithInt::class, 'expectedResult' => '{"type":"object","name":"ShapeWithInt","description":null,"properties":[{"type":"int","name":"value","description":"Description for literal int"}]}'];
        yield 'shape with string' => ['className' => ShapeWithString::class, 'expectedResult' => '{"type":"object","name":"ShapeWithString","description":null,"properties":[{"type":"string","name":"value","description":"Description for literal string"}]}'];
    }

    #[DataProvider('getSchema_dataProvider')]
    public function test_getSchema(string $className, string $expectedResult): void
    {
        $schema = Parser::getSchema($className);
        self::assertJsonStringEqualsJsonString($expectedResult, json_encode($schema, JSON_THROW_ON_ERROR));
    }

    public function test_getSchema_for_shape_object_allows_to_retrieve_overridden_property_descriptions(): void
    {
        $schema = Parser::getSchema(ShapeWithOptionalTypes::class);
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
        $literalBooleanSchema = new LiteralIntegerSchema('Some Description');
        self::assertJsonStringEqualsJsonString('{"type":"integer","name":"int","description":"Some Description"}', json_encode($literalBooleanSchema, JSON_THROW_ON_ERROR));
    }

    public function test_getSchema_for_literal_string(): void
    {
        $literalBooleanSchema = new LiteralStringSchema('Some Description');
        self::assertJsonStringEqualsJsonString('{"type":"string","name":"string","description":"Some Description"}', json_encode($literalBooleanSchema, JSON_THROW_ON_ERROR));
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


    public function test_instantiate_throws_if_className_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to get schema for empty class name');
        instantiate('', null);
    }

    public function test_instantiate_throws_if_className_is_not_a_className(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to get schema for class ""notAClass"" because that class does not exist');
        instantiate('notAClass', null);
    }

    public static function instantiate_enum_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'expectedExceptionMessage' => 'Failed to instantiate Title: Value of type null cannot be casted to string backed enum'];
        yield 'from string that is no case' => ['value' => 'mr', 'expectedExceptionMessage' => 'Failed to instantiate Title: Value "mr" is not a valid enum case'];
        yield 'from object' => ['value' => new stdClass(), 'expectedExceptionMessage' => 'Failed to instantiate Title: Value of type stdClass cannot be casted to string backed enum'];
        yield 'from boolean' => ['value' => true, 'expectedExceptionMessage' => 'Failed to instantiate Title: Value of type bool cannot be casted to string backed enum'];
        yield 'from integer' => ['value' => 3, 'expectedExceptionMessage' => 'Failed to instantiate Title: Value 3 is not a valid enum case'];
        yield 'from float without fraction' => ['value' => 2.0, 'expectedExceptionMessage' => 'Failed to instantiate Title: Value of type float cannot be casted to string backed enum'];
        yield 'from float with fraction' => ['value' => 2.5, 'expectedExceptionMessage' => 'Failed to instantiate Title: Value of type float cannot be casted to string backed enum'];
    }

    #[DataProvider('instantiate_enum_failing_dataProvider')]
    public function test_instantiate_enum_failing(mixed $value, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        instantiate(Title::class, $value);
    }

    public static function instantiate_enum_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => Title::MR, 'expectedResult' => Title::MR];
        yield 'from string matching a case' => ['value' => 'MRS', 'expectedResult' => Title::MRS];
        yield 'from stringable object matching a case' => ['value' => new class {
            function __toString()
            {
                return 'MISS';
            }
        }, 'expectedResult' => Title::MISS];
    }

    #[DataProvider('instantiate_enum_dataProvider')]
    public function test_instantiate_enum(mixed $value, Title $expectedResult): void
    {
        self::assertSame($expectedResult, instantiate(Title::class, $value));
    }

    public static function instantiate_int_backed_enum_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'expectedExceptionMessage' => 'Failed to instantiate Number: Value of type null cannot be casted to int backed enum'];
        yield 'from string' => ['value' => 'TWO', 'expectedExceptionMessage' => 'Failed to instantiate Number: Value "TWO" cannot be casted to int backed enum'];
        yield 'from object' => ['value' => new stdClass(), 'expectedExceptionMessage' => 'Failed to instantiate Number: Value of type stdClass cannot be casted to int backed enum'];
        yield 'from float with fraction' => ['value' => 2.5, 'expectedExceptionMessage' => 'Failed to instantiate Number: Value of type 2.500 cannot be casted to int backed enum'];
        yield 'from float with fraction 2' => ['value' => 2.345678, 'expectedExceptionMessage' => 'Failed to instantiate Number: Value of type 2.346 cannot be casted to int backed enum'];
        yield 'from int that matches no case' => ['value' => 5, 'expectedExceptionMessage' => 'Failed to instantiate Number: Value 5 is not a valid enum case'];
    }

    #[DataProvider('instantiate_int_backed_enum_failing_dataProvider')]
    public function test_instantiate_int_backed_enum_failing(mixed $value, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        instantiate(Number::class, $value);
    }

    public static function instantiate_int_backed_enum_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => Number::ONE, 'expectedResult' => Number::ONE];
        yield 'from numeric string' => ['value' => '2', 'expectedResult' => Number::TWO];
        yield 'from integer' => ['value' => 3, 'expectedResult' => Number::THREE];
        yield 'from float without fraction' => ['value' => 1.0, 'expectedResult' => Number::ONE];
    }

    #[DataProvider('instantiate_int_backed_enum_dataProvider')]
    public function test_instantiate_int_backed_enum(mixed $value, Number $expectedResult): void
    {
        self::assertSame($expectedResult, instantiate(Number::class, $value));
    }

    public static function instantiate_string_backed_enum_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'expectedExceptionMessage' => 'Failed to instantiate RomanNumber: Value of type null cannot be casted to string backed enum'];
        yield 'from string that is no case' => ['value' => 'i', 'expectedExceptionMessage' => 'Failed to instantiate RomanNumber: Value "i" is not a valid enum case'];
        yield 'from object' => ['value' => new stdClass(), 'expectedExceptionMessage' => 'Failed to instantiate RomanNumber: Value of type stdClass cannot be casted to string backed enum'];
        yield 'from boolean' => ['value' => false, 'expectedExceptionMessage' => 'Failed to instantiate RomanNumber: Value of type bool cannot be casted to string backed enum'];
        yield 'from integer that matches no case' => ['value' => 12, 'expectedExceptionMessage' => 'Failed to instantiate RomanNumber: Value 12 is not a valid enum case'];
        yield 'from float without fraction that matches a case' => ['value' => 2.0, 'expectedExceptionMessage' => 'Failed to instantiate RomanNumber: Value of type float cannot be casted to string backed enum'];
        yield 'from float with fraction' => ['value' => 2.5, 'expectedExceptionMessage' => 'Failed to instantiate RomanNumber: Value of type float cannot be casted to string backed enum'];
    }

    #[DataProvider('instantiate_string_backed_enum_failing_dataProvider')]
    public function test_instantiate_string_backed_enum_failing(mixed $value, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        instantiate(RomanNumber::class, $value);
    }

    public static function instantiate_string_backed_enum_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => RomanNumber::I, 'expectedResult' => RomanNumber::I];
        yield 'from string that is a case' => ['value' => '2', 'expectedResult' => RomanNumber::II];
        yield 'from integer matching a case' => ['value' => 4, 'expectedResult' => RomanNumber::IV];
    }

    #[DataProvider('instantiate_string_backed_enum_dataProvider')]
    public function test_instantiate_string_backed_enum(mixed $value, RomanNumber $expectedResult): void
    {
        self::assertSame($expectedResult, instantiate(RomanNumber::class, $value));
    }

    public static function instantiate_int_based_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => Age::class, 'expectedExceptionMessage' => 'Failed to instantiate Age: Value of type null cannot be casted to int'];
        yield 'from object' => ['value' => new stdClass(), 'className' => Age::class, 'expectedExceptionMessage' => 'Failed to instantiate Age: Value of type stdClass cannot be casted to int'];
        yield 'from boolean' => ['value' => false, 'className' => Age::class, 'expectedExceptionMessage' => 'Failed to instantiate Age: Value of type bool cannot be casted to int'];
        yield 'from string' => ['value' => 'not numeric', 'className' => Age::class, 'expectedExceptionMessage' => 'Failed to instantiate Age: Value "not numeric" cannot be casted to int'];
        yield 'from string with float' => ['value' => '2.0', 'className' => Age::class, 'expectedExceptionMessage' => 'Failed to instantiate Age: Value "2.0" cannot be casted to int'];
        yield 'from float with fraction' => ['value' => 2.5, 'className' => Age::class, 'expectedExceptionMessage' => 'Failed to instantiate Age: Value 2.500 cannot be casted to int'];

        yield 'from integer violating minimum' => ['value' => 0, 'className' => Age::class, 'expectedExceptionMessage' => 'Failed to instantiate Age: Value 0 falls below the allowed minimum value of 1'];
        yield 'from integer violating maximum' => ['value' => 121, 'className' => Age::class, 'expectedExceptionMessage' => 'Failed to instantiate Age: Value 121 exceeds the allowed maximum value of 120'];
    }

    #[DataProvider('instantiate_int_based_object_failing_dataProvider')]
    public function test_instantiate_int_based_object_failing(mixed $value, string $className, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        /** @var class-string<object> $className */
        instantiate($className, $value);
    }

    public static function instantiate_int_based_object_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => instantiate(Age::class, 120), 'className' => Age::class, 'expectedResult' => 120];
        yield 'from integer that matches constraints' => ['value' => 120, 'className' => Age::class, 'expectedResult' => 120];
        yield 'from numeric string that matches constraints' => ['value' => '1', 'className' => Age::class, 'expectedResult' => 1];
        yield 'from float without fraction' => ['value' => 4.0, 'className' => Age::class, 'expectedResult' => 4];
    }

    #[DataProvider('instantiate_int_based_object_dataProvider')]
    public function test_instantiate_int_based_object(mixed $value, string $className, int $expectedResult): void
    {
        /** @var class-string<object> $className */
        self::assertSame($expectedResult, instantiate($className, $value)->value);
    }

    public static function instantiate_list_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => FullNames::class, 'expectedExceptionMessage' => 'Failed to instantiate FullNames: Non-iterable value of type null cannot be casted to list of FullName'];
        yield 'from object' => ['value' => new stdClass(), 'className' => FullNames::class, 'expectedExceptionMessage' => 'Failed to instantiate FullNames: Non-iterable value of type stdClass cannot be casted to list of FullName'];
        yield 'from boolean' => ['value' => false, 'className' => FullNames::class, 'expectedExceptionMessage' => 'Failed to instantiate FullNames: Non-iterable value of type bool cannot be casted to list of FullName'];
        yield 'from string' => ['value' => 'some string', 'className' => FullNames::class, 'expectedExceptionMessage' => 'Failed to instantiate FullNames: Non-iterable value of type string cannot be casted to list of FullName'];

        yield 'from array with invalid item' => ['value' => ['Some value'], 'className' => FullNames::class, 'expectedExceptionMessage' => 'Failed to instantiate FullNames: At key "0": Non-iterable value of type string cannot be casted to instance of FullName'];
        yield 'from array with invalid item 2' => ['value' => [123], 'className' => GivenNames::class, 'expectedExceptionMessage' => 'Failed to instantiate GivenNames: At key "0": Value of type int cannot be casted to string'];
        yield 'from array violating minCount' => ['value' => [], 'className' => FullNames::class, 'expectedExceptionMessage' => 'Failed to instantiate FullNames: Number of elements (0) is less than allowed min count of 2'];
        yield 'from array violating minCount 2' => ['value' => [['givenName' => 'John', 'familyName' => 'Doe']], 'className' => FullNames::class, 'expectedExceptionMessage' => 'Failed to instantiate FullNames: Number of elements (1) is less than allowed min count of 2'];
        yield 'from array violating maxCount' => ['value' => ['John', 'Jane', 'Max', 'Jack', 'Fred'], 'className' => GivenNames::class, 'expectedExceptionMessage' => 'Failed to instantiate GivenNames: Number of elements (5) is more than allowed max count of 4'];
    }

    #[DataProvider('instantiate_list_object_failing_dataProvider')]
    public function test_instantiate_list_object_failing(mixed $value, string $className, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        /** @var class-string<object> $className */
        instantiate($className, $value);
    }

    public static function instantiate_list_object_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => instantiate(GivenNames::class, ['John', 'Jack', 'Jane']), 'className' => GivenNames::class, 'expectedResult' => '[{"value":"John"},{"value":"Jack"},{"value":"Jane"}]'];
        yield 'from strings' => ['value' => ['John', 'Jack', 'Jane'], 'className' => GivenNames::class, 'expectedResult' => '[{"value":"John"},{"value":"Jack"},{"value":"Jane"}]'];
    }

    #[DataProvider('instantiate_list_object_dataProvider')]
    public function test_instantiate_list_object(mixed $value, string $className, string $expectedResult): void
    {
        self::assertSame($expectedResult, json_encode(instantiate($className, $value), JSON_THROW_ON_ERROR));
    }

    public static function instantiate_shape_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => FullName::class, 'expectedExceptionMessage' => 'Failed to instantiate FullName: Non-iterable value of type null cannot be casted to instance of FullName'];
        yield 'from empty object' => ['value' => new stdClass(), 'className' => FullName::class, 'expectedExceptionMessage' => 'Failed to instantiate FullName: Missing property "givenName"'];
        yield 'from boolean' => ['value' => false, 'className' => FullName::class, 'expectedExceptionMessage' => 'Failed to instantiate FullName: Non-iterable value of type bool cannot be casted to instance of FullName'];
        yield 'from string' => ['value' => 'some string', 'className' => FullName::class, 'expectedExceptionMessage' => 'Failed to instantiate FullName: Non-iterable value of type string cannot be casted to instance of FullName'];

        yield 'from array with missing key' => ['value' => ['givenName' => 'Some first name'], 'className' => FullName::class, 'expectedExceptionMessage' => 'Failed to instantiate FullName: Missing property "familyName"'];
        yield 'from array with missing keys' => ['value' => [], 'className' => FullName::class, 'expectedExceptionMessage' => 'Failed to instantiate FullName: Missing property "givenName"'];
        yield 'from array with additional key' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Some last name', 'additional' => 'not allowed'], 'className' => FullName::class, 'expectedExceptionMessage' => 'Failed to instantiate FullName: Unknown property "additional"'];
        yield 'from array with additional keys' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Some last name', 'additional' => 'not allowed', 'another additional' => 'also not allowed'], 'className' => FullName::class, 'expectedExceptionMessage' => 'Failed to instantiate FullName: Unknown properties "additional", "another additional"'];

        yield 'from array with property violating constraints' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Ab'], 'className' => FullName::class, 'expectedExceptionMessage' => 'Failed to instantiate FullName: At property "familyName": Value "Ab" does not have the required minimum length of 3 characters'];
        yield 'from array with property violating constraints 2' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Ab'], 'className' => FullName::class, 'expectedExceptionMessage' => 'Failed to instantiate FullName: At property "familyName": Value "Ab" does not have the required minimum length of 3 characters'];

        yield 'bool from string' => ['value' => ['value' => 'not a bool'], 'className' => ShapeWithBool::class, 'expectedExceptionMessage' => 'Failed to instantiate ShapeWithBool: At property "value": Value "not a bool" cannot be casted to boolean'];
        yield 'bool from int' => ['value' => ['value' => 123], 'className' => ShapeWithBool::class, 'expectedExceptionMessage' => 'Failed to instantiate ShapeWithBool: At property "value": Value 123 cannot be casted to boolean'];
        yield 'bool from object' => ['value' => ['value' => new stdClass()], 'className' => ShapeWithBool::class, 'expectedExceptionMessage' => 'Failed to instantiate ShapeWithBool: At property "value": Value of type stdClass cannot be casted to boolean'];
        yield 'string from float' => ['value' => ['value' => 123.45], 'className' => ShapeWithString::class, 'expectedExceptionMessage' => 'Failed to instantiate ShapeWithString: At property "value": Value of type float cannot be casted to string'];
        yield 'integer from float' => ['value' => ['value' => 123.45], 'className' => ShapeWithInt::class, 'expectedExceptionMessage' => 'Failed to instantiate ShapeWithInt: At property "value": Value 123.450 cannot be casted to integer'];
        yield 'integer from string' => ['value' => ['value' => 'not numeric'], 'className' => ShapeWithInt::class, 'expectedExceptionMessage' => 'Failed to instantiate ShapeWithInt: At property "value": Value "not numeric" cannot be casted to integer'];
        yield 'integer from object' => ['value' => ['value' => new stdClass()], 'className' => ShapeWithInt::class, 'expectedExceptionMessage' => 'Failed to instantiate ShapeWithInt: At property "value": Value of type stdClass cannot be casted to integer'];
    }

    #[DataProvider('instantiate_shape_object_failing_dataProvider')]
    public function test_instantiate_shape_object_failing(mixed $value, string $className, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        /** @var class-string<object> $className */
        instantiate($className, $value);
    }

    public static function instantiate_shape_object_dataProvider(): Generator
    {
        yield 'from array matching all constraints' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Some last name'], 'className' => FullName::class, 'expectedResult' => '{"givenName":{"value":"Some first name"},"familyName":{"value":"Some last name"}}'];
        yield 'from iterable matching all constraints' => ['value' => new ArrayIterator(['givenName' => 'Some first name', 'familyName' => 'Some last name']), 'className' => FullName::class, 'expectedResult' => '{"givenName":{"value":"Some first name"},"familyName":{"value":"Some last name"}}'];
        yield 'from array without optionals' => ['value' => ['stringBased' => 'Some value'], 'className' => ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":{"value":"Some value"},"optionalStringBased":null,"optionalInt":null,"optionalBool":false,"optionalString":null}'];
        yield 'from array with optionals' => ['value' => ['stringBased' => 'Some value', 'optionalString' => 'optionalString value', 'optionalStringBased' => 'oSB value', 'optionalInt' => 42, 'optionalBool' => true], 'className' => ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":{"value":"Some value"},"optionalStringBased":{"value":"oSB value"},"optionalInt":42,"optionalBool":true,"optionalString":"optionalString value"}'];
        yield 'from array with optionals and coercion' => ['value' => ['stringBased' => 'Some value', 'optionalString' => new class { function __toString() { return 'optionalString value'; }}, 'optionalStringBased' => 'oSB value', 'optionalInt' => '123', 'optionalBool' => 1], 'className' => ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":{"value":"Some value"},"optionalStringBased":{"value":"oSB value"},"optionalInt":123,"optionalBool":true,"optionalString":"optionalString value"}'];
        yield 'from array with optionals and coercion 2' => ['value' => ['stringBased' => 'Some value', 'optionalString' => new class { function __toString() { return 'optionalString value'; }}, 'optionalStringBased' => 'oSB value', 'optionalInt' => 55.0, 'optionalBool' => '0'], 'className' => ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":{"value":"Some value"},"optionalStringBased":{"value":"oSB value"},"optionalInt":55,"optionalBool":false,"optionalString":"optionalString value"}'];
        yield 'from array with null-values for optionals' => ['value' => ['stringBased' => 'Some value', 'optionalStringBased' => null, 'optionalInt' => null, 'optionalBool' => null, 'optionalString' => null], 'className' => ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":{"value":"Some value"},"optionalStringBased":null,"optionalInt":null,"optionalBool":null,"optionalString":null}'];
        $class = new stdClass();
        $class->givenName = 'Some first name';
        $class->familyName = 'Some last name';
        yield 'from stdClass matching all constraints' => ['value' => $class, 'className' => FullName::class, 'expectedResult' => '{"givenName":{"value":"Some first name"},"familyName":{"value":"Some last name"}}'];
    }

    #[DataProvider('instantiate_shape_object_dataProvider')]
    public function test_instantiate_shape_object(mixed $value, string $className, string $expectedResult): void
    {
        /** @var class-string<object> $className */
        self::assertSame($expectedResult, json_encode(instantiate($className, $value), JSON_THROW_ON_ERROR));
    }

    public static function instantiate_string_based_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => GivenName::class, 'expectedExceptionMessage' => 'Failed to instantiate GivenName: Value of type null cannot be casted to string'];
        yield 'from object' => ['value' => new stdClass(), 'className' => GivenName::class, 'expectedExceptionMessage' => 'Failed to instantiate GivenName: Value of type stdClass cannot be casted to string'];
        yield 'from boolean' => ['value' => false, 'className' => GivenName::class, 'expectedExceptionMessage' => 'Failed to instantiate GivenName: Value of type bool cannot be casted to string'];
        yield 'from integer' => ['value' => 1234, 'className' => GivenName::class, 'expectedExceptionMessage' => 'Failed to instantiate GivenName: Value of type int cannot be casted to string'];
        yield 'from float' => ['value' => 2.0, 'className' => GivenName::class, 'expectedExceptionMessage' => 'Failed to instantiate GivenName: Value of type float cannot be casted to string'];

        yield 'from string violating minLength' => ['value' => 'ab', 'className' => GivenName::class, 'expectedExceptionMessage' => 'Failed to instantiate GivenName: Value "ab" does not have the required minimum length of 3 characters'];
        yield 'from string violating maxLength' => ['value' => 'This is a bit too long', 'className' => GivenName::class, 'expectedExceptionMessage' => 'Failed to instantiate GivenName: Value "This is a bit too long" exceeds the allowed maximum length of 20 characters'];
        yield 'from string violating pattern' => ['value' => 'magic foo', 'className' => NotMagic::class, 'expectedExceptionMessage' => 'Failed to instantiate NotMagic: Value "magic foo" does not match the regular expression "/^(?!magic).*/"'];

        yield 'from string violating format "email"' => ['value' => 'not.an@email', 'className' => EmailAddress::class, 'expectedExceptionMessage' => 'Failed to instantiate EmailAddress: Value "not.an@email" does not match format "email"'];
        yield 'from string violating format "uri"' => ['value' => 'not.a.uri', 'className' => Uri::class, 'expectedExceptionMessage' => 'Failed to instantiate Uri: Value "not.a.uri" does not match format "uri"'];
        yield 'from string violating format "date"' => ['value' => 'not.a.date', 'className' => Date::class, 'expectedExceptionMessage' => 'Failed to instantiate Date: Value "not.a.date" does not match format "date"'];
        yield 'from string custom "date" validation' => ['value' => (new DateTimeImmutable('+1 day'))->format('Y-m-d'), 'className' => Date::class, 'expectedExceptionMessage' => 'Failed to instantiate Date: Future dates are not allowed'];
        yield 'from string violating format "date_time"' => ['value' => 'not.a.date', 'className' => DateTime::class, 'expectedExceptionMessage' => 'Failed to instantiate DateTime: Value "not.a.date" does not match format "date_time"'];
        yield 'from string violating format "uuid"' => ['value' => 'not.a.uuid', 'className' => Uuid::class, 'expectedExceptionMessage' => 'Failed to instantiate Uuid: Value "not.a.uuid" does not match format "uuid"'];
    }

    #[DataProvider('instantiate_string_based_object_failing_dataProvider')]
    public function test_instantiate_string_based_object_failing(mixed $value, string $className, string $expectedExceptionMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        /** @var class-string<object> $className */
        instantiate($className, $value);
    }

    public static function instantiate_string_based_object_dataProvider(): Generator
    {
        yield 'from string that matches constraints' => ['value' => 'this is valid', 'className' => GivenName::class, 'expectedResult' => 'this is valid'];
        yield 'from string that matches pattern' => ['value' => 'this is not magic', 'className' => NotMagic::class, 'expectedResult' => 'this is not magic'];
        yield 'from stringable object' => ['value' => new class {
            function __toString()
            {
                return 'from object';
            }
        }, 'className' => GivenName::class, 'expectedResult' => 'from object'];

        yield 'from string matching format "email"' => ['value' => 'a.valid@email.com', 'className' => EmailAddress::class, 'expectedResult' => 'a.valid@email.com'];
        yield 'from string matching format "uri"' => ['value' => 'https://www.some-domain.tld', 'className' => Uri::class, 'expectedResult' => 'https://www.some-domain.tld'];
        yield 'from string matching format "date"' => ['value' => '1980-12-13', 'className' => Date::class, 'expectedResult' => '1980-12-13'];
        yield 'from string matching format "date_time"' => ['value' => '2018-11-13T20:20:39+00:00', 'className' => DateTime::class, 'expectedResult' => '2018-11-13T20:20:39+00:00'];
        yield 'from string matching format "uuid"' => ['value' => '3cafa54b-f9c3-4470-8c0e-31612cb70f61', 'className' => Uuid::class, 'expectedResult' => '3cafa54b-f9c3-4470-8c0e-31612cb70f61'];
    }

    #[DataProvider('instantiate_string_based_object_dataProvider')]
    public function test_instantiate_string_based_object(mixed $value, string $className, string $expectedResult): void
    {
        /** @var class-string<object> $className */
        self::assertSame($expectedResult, instantiate($className, $value)->value);
    }
}

#[StringBased(minLength: 3, maxLength: 20)]
#[Description('First name of a person')]
final class GivenName
{
    private function __construct(public readonly string $value)
    {
    }
}

#[StringBased(minLength: 3, maxLength: 20)]
#[Description('Last name of a person')]
final class FamilyName
{
    private function __construct(public readonly string $value)
    {
    }
}

#[IntegerBased(minimum: 1, maximum: 120)]
#[Description('The age of a person in years')]
final class Age
{
    private function __construct(public readonly int $value)
    {
    }
}

#[Description('First and last name of a person')]
final class FullName
{
    public function __construct(
        #[Description('Overridden given name description')]
        public readonly GivenName $givenName,
        public readonly FamilyName $familyName,
    )
    {
    }
}

/** @implements IteratorAggregate<FullName> */
#[ListBased(itemClassName: FullName::class, minCount: 2, maxCount: 5)]
final class FullNames implements IteratorAggregate
{

    private array $fullNames;

    /** @param array<FullName> $fullNames */
    private function __construct(FullName... $fullNames)
    {
        $this->fullNames = $fullNames;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->fullNames);
    }
}

#[ListBased(itemClassName: GivenName::class, maxCount: 4)]
final class GivenNames implements IteratorAggregate, JsonSerializable
{

    /** @param array<GivenName> $givenNames */
    private function __construct(private readonly array $givenNames)
    {
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->givenNames);
    }

    public function jsonSerialize(): array
    {
        return $this->givenNames;
    }
}

#[StringBased(pattern: '^(?!magic).*')]
final class NotMagic
{
    private function __construct(public readonly string $value)
    {
    }
}

#[StringBased(format: StringTypeFormat::email)]
final class EmailAddress
{
    private function __construct(public readonly string $value)
    {
    }
}

#[StringBased(format: StringTypeFormat::uri)]
final class Uri
{
    private function __construct(public readonly string $value)
    {
    }
}

#[StringBased(format: StringTypeFormat::date)]
final class Date
{
    private function __construct(public readonly string $value)
    {
        $now = new DateTimeImmutable();
        if (DateTimeImmutable::createFromFormat('Y-m-d', $this->value) > $now) {
            throw new InvalidArgumentException('Future dates are not allowed');
        }
    }
}

#[StringBased(format: StringTypeFormat::date_time)]
final class DateTime
{
    private function __construct(public readonly string $value)
    {
    }
}

#[StringBased(format: StringTypeFormat::uuid)]
final class Uuid
{
    private function __construct(public readonly string $value)
    {
    }
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

final class ShapeWithOptionalTypes
{
    public function __construct(
        public readonly FamilyName $stringBased,
        public readonly ?FamilyName $optionalStringBased = null,
        #[Description('Some description')]
        public readonly ?int $optionalInt = null,
        public readonly ?bool $optionalBool = false,
        public readonly ?string $optionalString = null,
    ) {
    }
}

final class ShapeWithInvalidObjectProperty {
    public function __construct(
        public readonly stdClass $someProperty,
    ) {
    }
}

final class ShapeWithBool {
    private function __construct(
        #[Description('Description for literal bool')]
        public readonly bool $value,
    ) {}
}

final class ShapeWithInt {
    private function __construct(
        #[Description('Description for literal int')]
        public readonly int $value,
    ) {}
}

final class ShapeWithString {
    private function __construct(
        #[Description('Description for literal string')]
        public readonly string $value,
    ) {}
}