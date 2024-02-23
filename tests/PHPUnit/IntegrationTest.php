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
use Wwwision\Types\Attributes\FloatBased;
use Wwwision\Types\Attributes\IntegerBased;
use Wwwision\Types\Attributes\ListBased;
use Wwwision\Types\Attributes\StringBased;
use Wwwision\Types\Exception\CoerceException;
use Wwwision\Types\Parser;
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
#[CoversClass(FloatSchema::class)]
#[CoversClass(IntegerSchema::class)]
#[CoversClass(ListSchema::class)]
#[CoversClass(LiteralBooleanSchema::class)]
#[CoversClass(LiteralFloatSchema::class)]
#[CoversClass(LiteralIntegerSchema::class)]
#[CoversClass(LiteralStringSchema::class)]
#[CoversClass(OptionalSchema::class)]
#[CoversClass(ShapeSchema::class)]
#[CoversClass(StringSchema::class)]
#[CoversClass(InterfaceSchema::class)]
#[CoversClass(StringTypeFormat::class)]
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
        $this->expectExceptionMessage('Failed to get schema for class "notAClass" because that class does not exist');
        Parser::getSchema('notAClass');
    }

    public function test_getSchema_throws_if_given_class_is_shape_with_invalid_properties(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to parse constructor argument "someProperty" of class "ShapeWithInvalidObjectProperty": Missing constructor in class "stdClass"');
        Parser::getSchema(ShapeWithInvalidObjectProperty::class);
    }

    /**
     * Note: Currently methods with parameters are not supported, but this can change at some point
     */
    public function test_getSchema_throws_if_given_class_is_interface_with_parameterized_methods(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Method "methodWithParameters" of interface "Wwwision\Types\Tests\PHPUnit\SomeInvalidInterface" has at least one parameter, but this is currently not supported');
        Parser::getSchema(SomeInvalidInterface::class);
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
        yield 'shape with floats' => ['className' => GeoCoordinates::class, 'expectedResult' => '{"type":"object","name":"GeoCoordinates","description":null,"properties":[{"type":"Longitude","name":"longitude","description":null},{"type":"Latitude","name":"latitude","description":null}]}'];

        yield 'interface' => ['className' => SomeInterface::class, 'expectedResult' => '{"description":"SomeInterface description","name":"SomeInterface","properties":[{"description":"Custom description for \"someMethod\"","name":"someMethod","type":"string"},{"description":"Custom description for \"someOtherMethod\"","name":"someOtherMethod","optional":true,"type":"FamilyName"}],"type":"interface"}'];
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
        $literalIntegerSchema = new LiteralIntegerSchema('Some Description');
        self::assertJsonStringEqualsJsonString('{"type":"integer","name":"int","description":"Some Description"}', json_encode($literalIntegerSchema, JSON_THROW_ON_ERROR));
    }

    public function test_getSchema_for_literal_float(): void
    {
        $literalFloatSchema = new LiteralFloatSchema('Some Description');
        self::assertJsonStringEqualsJsonString('{"type":"float","name":"float","description":"Some Description"}', json_encode($literalFloatSchema, JSON_THROW_ON_ERROR));
    }

    public function test_getSchema_for_literal_string(): void
    {
        $literalStringSchema = new LiteralStringSchema('Some Description');
        self::assertJsonStringEqualsJsonString('{"type":"string","name":"string","description":"Some Description"}', json_encode($literalStringSchema, JSON_THROW_ON_ERROR));
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
        $this->expectExceptionMessage('Failed to get schema for class "notAClass" because that class does not exist');
        instantiate('notAClass', null);
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

    #[DataProvider('instantiate_enum_failing_dataProvider')]
    public function test_instantiate_enum_failing(mixed $value, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate(Title::class, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
    }

    public static function instantiate_enum_dataProvider(): Generator
    {
        yield 'from instance' => ['value' => Title::MR, 'expectedResult' => Title::MR];
        yield 'from string matching a case' => ['value' => 'MRS', 'expectedResult' => Title::MRS];
        yield 'from stringable object matching a case' => ['value' => new class {
            public function __toString()
            {
                return 'MISS';
            }
        }, 'expectedResult' => Title::MISS];
        yield 'from already converted instance' => ['value' => Title::MS, 'expectedResult' => Title::MS];
    }

    #[DataProvider('instantiate_enum_dataProvider')]
    public function test_instantiate_enum(mixed $value, Title $expectedResult): void
    {
        self::assertSame($expectedResult, instantiate(Title::class, $value));
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

    #[DataProvider('instantiate_int_backed_enum_failing_dataProvider')]
    public function test_instantiate_int_backed_enum_failing(mixed $value, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate(Number::class, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
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
        yield 'from null' => ['value' => null, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'1\' | \'2\' | \'3\' | \'4\', received null', 'path' => [], 'expected' => 'enum', 'received' => 'null']]];
        yield 'from string that is no case' => ['value' => 'i', 'expectedIssues' => [['code' => 'invalid_enum_value', 'message' => 'Invalid enum value. Expected \'1\' | \'2\' | \'3\' | \'4\', received \'i\'', 'path' => [], 'received' => '\'i\'', 'options' => ['1', '2', '3', '4']]]];
        yield 'from object' => ['value' => new stdClass(), 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'1\' | \'2\' | \'3\' | \'4\', received object', 'path' => [], 'expected' => 'enum', 'received' => 'object']]];
        yield 'from boolean' => ['value' => false, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'1\' | \'2\' | \'3\' | \'4\', received boolean', 'path' => [], 'expected' => 'enum', 'received' => 'boolean']]];
        yield 'from integer that matches no case' => ['value' => 12, 'expectedIssues' => [['code' => 'invalid_enum_value', 'message' => 'Invalid enum value. Expected \'1\' | \'2\' | \'3\' | \'4\', received integer', 'path' => [], 'received' => 'integer', 'options' => ['1', '2', '3', '4']]]];
        yield 'from float without fraction that matches a case' => ['value' => 2.0, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'1\' | \'2\' | \'3\' | \'4\', received double', 'path' => [], 'expected' => 'enum', 'received' => 'double']]];
        yield 'from float with fraction' => ['value' => 2.5, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected \'1\' | \'2\' | \'3\' | \'4\', received double', 'path' => [], 'expected' => 'enum', 'received' => 'double']]];
    }

    #[DataProvider('instantiate_string_backed_enum_failing_dataProvider')]
    public function test_instantiate_string_backed_enum_failing(mixed $value, array $expectedIssues): void
    {
        $exceptionThrown = false;
        $expectedIssuesJson = json_encode($expectedIssues, JSON_THROW_ON_ERROR);
        try {
            instantiate(RomanNumber::class, $value);
        } catch (CoerceException $e) {
            $exceptionThrown = true;
            self::assertJsonStringEqualsJsonString($expectedIssuesJson, json_encode($e, JSON_THROW_ON_ERROR));
        }
        self::assertTrue($exceptionThrown, sprintf('Failed asserting that exception of type "%s" is thrown.', CoerceException::class));
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

    public static function instantiate_float_based_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => Longitude::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected float, received null', 'path' => [], 'expected' => 'float', 'received' => 'null']]];
        yield 'from object' => ['value' => new stdClass(), 'className' => Longitude::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected float, received object', 'path' => [], 'expected' => 'float', 'received' => 'object']]];
        yield 'from boolean' => ['value' => false, 'className' => Longitude::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected float, received boolean', 'path' => [], 'expected' => 'float', 'received' => 'boolean']]];
        yield 'from string' => ['value' => 'not numeric', 'className' => Longitude::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected float, received string', 'path' => [], 'expected' => 'float', 'received' => 'string']]];

        yield 'from float violating minimum' => ['value' => -181.0, 'className' => Longitude::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Number must be greater than or equal to -180.000', 'path' => [], 'type' => 'double', 'minimum' => -180, 'inclusive' => true, 'exact' => false]]];
        yield 'from float with fraction violating minimum' => ['value' => -90.123, 'className' => Latitude::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Number must be greater than or equal to -90.000', 'path' => [], 'type' => 'double', 'minimum' => -90, 'inclusive' => true, 'exact' => false]]];
        yield 'from float violating maximum' => ['value' => 181.0, 'className' => Longitude::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Number must be less than or equal to 180.500', 'path' => [], 'type' => 'double', 'maximum' => 180.5, 'inclusive' => true, 'exact' => false]]];
        yield 'from float with fraction violating maximum' => ['value' => 90.123, 'className' => Latitude::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Number must be less than or equal to 90.000', 'path' => [], 'type' => 'double', 'maximum' => 90, 'inclusive' => true, 'exact' => false]]];

        yield 'from float with fraction violating multiple constraints' => ['value' => 5.34, 'className' => ImpossibleFloat::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Number must be less than or equal to 2.450', 'path' => [], 'type' => 'double', 'maximum' => 2.45, 'inclusive' => true, 'exact' => false], ['code' => 'too_small', 'message' => 'Number must be greater than or equal to 10.230', 'path' => [], 'type' => 'double', 'minimum' => 10.23, 'inclusive' => true, 'exact' => false]]];
    }

    /**
     * @param class-string<object> $className
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
        yield 'from instance' => ['value' => instantiate(Longitude::class, 120), 'className' => Longitude::class, 'expectedResult' => 120.0];
        yield 'from integer that matches constraints' => ['value' => 120, 'className' => Longitude::class, 'expectedResult' => 120];
        yield 'from numeric string that matches constraints' => ['value' => '1', 'className' => Longitude::class, 'expectedResult' => 1];
        yield 'from numeric string with floating point that matches constraints' => ['value' => '1.234', 'className' => Longitude::class, 'expectedResult' => 1.234];
        yield 'from float without fraction' => ['value' => 4.0, 'className' => Longitude::class, 'expectedResult' => 4];
        yield 'from float with fraction' => ['value' => 4.456, 'className' => Longitude::class, 'expectedResult' => 4.456];
    }

    #[DataProvider('instantiate_float_based_object_dataProvider')]
    public function test_instantiate_float_based_object(mixed $value, string $className, float $expectedResult): void
    {
        /** @var class-string<object> $className */
        self::assertSame($expectedResult, instantiate($className, $value)->value);
    }

    public static function instantiate_int_based_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received null', 'path' => [], 'expected' => 'integer', 'received' => 'null']]];
        yield 'from object' => ['value' => new stdClass(), 'className' => Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received object', 'path' => [], 'expected' => 'integer', 'received' => 'object']]];
        yield 'from boolean' => ['value' => false, 'className' => Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received boolean', 'path' => [], 'expected' => 'integer', 'received' => 'boolean']]];
        yield 'from string' => ['value' => 'not numeric', 'className' => Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received string', 'path' => [], 'expected' => 'integer', 'received' => 'string']]];
        yield 'from string with float' => ['value' => '2.0', 'className' => Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received string', 'path' => [], 'expected' => 'integer', 'received' => 'string']]];
        yield 'from float with fraction' => ['value' => 2.5, 'className' => Age::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received double', 'path' => [], 'expected' => 'integer', 'received' => 'double']]];

        yield 'from integer violating minimum' => ['value' => 0, 'className' => Age::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Number must be greater than or equal to 1', 'path' => [], 'type' => 'integer', 'minimum' => 1, 'inclusive' => true, 'exact' => false]]];
        yield 'from integer violating maximum' => ['value' => 121, 'className' => Age::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Number must be less than or equal to 120', 'path' => [], 'type' => 'integer', 'maximum' => 120, 'inclusive' => true, 'exact' => false]]];

        yield 'from integer violating multiple constraints' => ['value' => 5, 'className' => ImpossibleInt::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Number must be less than or equal to 2', 'path' => [], 'type' => 'integer', 'maximum' => 2, 'inclusive' => true, 'exact' => false], ['code' => 'too_small', 'message' => 'Number must be greater than or equal to 10', 'path' => [], 'type' => 'integer', 'minimum' => 10, 'inclusive' => true, 'exact' => false]]];
    }

    /**
     * @param class-string<object> $className
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
        yield 'from null' => ['value' => null, 'className' => FullNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected array, received null', 'path' => [], 'expected' => 'array', 'received' => 'null']]];
        yield 'from object' => ['value' => new stdClass(), 'className' => FullNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected array, received object', 'path' => [], 'expected' => 'array', 'received' => 'object']]];
        yield 'from boolean' => ['value' => false, 'className' => FullNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected array, received boolean', 'path' => [], 'expected' => 'array', 'received' => 'boolean']]];
        yield 'from string' => ['value' => 'some string', 'className' => FullNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected array, received string', 'path' => [], 'expected' => 'array', 'received' => 'string']]];

        yield 'from array with invalid item' => ['value' => [123.45], 'className' => GivenNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received double', 'path' => [0], 'expected' => 'string', 'received' => 'double']]];
        yield 'from array with invalid items' => ['value' => ['Some value', 'Some other value'], 'className' => FullNames::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received string', 'path' => [0], 'expected' => 'object', 'received' => 'string'], ['code' => 'invalid_type', 'message' => 'Expected object, received string', 'path' => [1], 'expected' => 'object', 'received' => 'string']]];
        yield 'from non-assoc array with custom exception' => ['value' => ['https://wwwision.de', 'https://neos.io'], 'className' => UriMap::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Expected associative array with string keys', 'path' => [], 'params' => []]]];
        yield 'from array violating minCount' => ['value' => [], 'className' => FullNames::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Array must contain at least 2 element(s)', 'path' => [], 'type' => 'array', 'minimum' => 2, 'inclusive' => true, 'exact' => false]]];
        yield 'from array violating minCount 2' => ['value' => [['givenName' => 'John', 'familyName' => 'Doe']], 'className' => FullNames::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Array must contain at least 2 element(s)', 'path' => [], 'type' => 'array', 'minimum' => 2, 'inclusive' => true, 'exact' => false]]];
        yield 'from array violating maxCount' => ['value' => ['John', 'Jane', 'Max', 'Jack', 'Fred'], 'className' => GivenNames::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'Array must contain at most 4 element(s)', 'path' => [], 'type' => 'array', 'maximum' => 4, 'inclusive' => true, 'exact' => false]]];
        yield 'from array violating mixCount and maxCount' => ['value' => ['foo', 'bar', 'baz'], 'className' => ImpossibleList::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Array must contain at least 10 element(s)', 'path' => [], 'type' => 'array', 'minimum' => 10, 'inclusive' => true, 'exact' => false], ['code' => 'too_big', 'message' => 'Array must contain at most 2 element(s)', 'path' => [], 'type' => 'array', 'maximum' => 2, 'inclusive' => true, 'exact' => false]]];
        yield 'from array violating mixCount and maxCount and element constraints' => ['value' => ['a', 'bar', 'c'], 'className' => ImpossibleList::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'Array must contain at least 10 element(s)', 'path' => [], 'type' => 'array', 'minimum' => 10, 'inclusive' => true, 'exact' => false], ['code' => 'too_big', 'message' => 'Array must contain at most 2 element(s)', 'path' => [], 'type' => 'array', 'maximum' => 2, 'inclusive' => true, 'exact' => false], ['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => [0], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false], ['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => [2], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false]]];
    }

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
        yield 'from instance' => ['value' => instantiate(GivenNames::class, ['John', 'Jack', 'Jane']), 'className' => GivenNames::class, 'expectedResult' => '["John","Jack","Jane"]'];
        yield 'from strings' => ['value' => ['John', 'Jack', 'Jane'], 'className' => GivenNames::class, 'expectedResult' => '["John","Jack","Jane"]'];
        yield 'map of strings' => ['value' => ['wwwision' => 'https://wwwision.de', 'Neos CMS' => 'https://neos.io'], 'className' => UriMap::class, 'expectedResult' => '{"wwwision":"https://wwwision.de","Neos CMS":"https://neos.io"}'];
    }

    #[DataProvider('instantiate_list_object_dataProvider')]
    public function test_instantiate_list_object(mixed $value, string $className, string $expectedResult): void
    {
        self::assertSame($expectedResult, json_encode(instantiate($className, $value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public static function instantiate_shape_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received null', 'path' => [], 'expected' => 'object', 'received' => 'null']]];
        yield 'from empty object' => ['value' => new stdClass(), 'className' => FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Required', 'path' => ['givenName'], 'expected' => 'string', 'received' => 'undefined'], ['code' => 'invalid_type', 'message' => 'Required', 'path' => ['familyName'], 'expected' => 'string', 'received' => 'undefined']]];
        yield 'from boolean' => ['value' => false, 'className' => FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received boolean', 'path' => [], 'expected' => 'object', 'received' => 'boolean']]];
        yield 'from string' => ['value' => 'some string', 'className' => FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received string', 'path' => [], 'expected' => 'object', 'received' => 'string']]];

        yield 'from array with missing key' => ['value' => ['givenName' => 'Some first name'], 'className' => FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Required', 'path' => ['familyName'], 'expected' => 'string', 'received' => 'undefined']]];
        yield 'from array with missing keys' => ['value' => [], 'className' => FullName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Required', 'path' => ['givenName'], 'expected' => 'string', 'received' => 'undefined'], ['code' => 'invalid_type', 'message' => 'Required', 'path' => ['familyName'], 'expected' => 'string', 'received' => 'undefined']]];
        yield 'from array with additional key' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Some last name', 'additional' => 'not allowed'], 'className' => FullName::class, 'expectedIssues' => [['code' => 'unrecognized_keys', 'message' => 'Unrecognized key(s) in object: \'additional\'', 'path' => [], 'keys' => ['additional']]]];
        yield 'from array with additional keys' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Some last name', 'additional' => 'not allowed', 'another additional' => 'also not allowed'], 'className' => FullName::class, 'expectedIssues' => [['code' => 'unrecognized_keys', 'message' => 'Unrecognized key(s) in object: \'additional\', \'another additional\'', 'path' => [], 'keys' => ['additional', 'another additional']]]];

        yield 'from array with property violating constraint' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Ab'], 'className' => FullName::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => ['familyName'], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false]]];
        yield 'from array with properties violating constraints' => ['value' => ['givenName' => 'Ab', 'familyName' => 'Ab'], 'className' => FullName::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => ['givenName'], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false], ['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => ['familyName'], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false]]];

        yield 'bool from string' => ['value' => ['value' => 'not a bool'], 'className' => ShapeWithBool::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected boolean, received string', 'path' => ['value'], 'expected' => 'boolean', 'received' => 'string']]];
        yield 'bool from int' => ['value' => ['value' => 123], 'className' => ShapeWithBool::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected boolean, received integer', 'path' => ['value'], 'expected' => 'boolean', 'received' => 'integer']]];
        yield 'bool from object' => ['value' => ['value' => new stdClass()], 'className' => ShapeWithBool::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected boolean, received object', 'path' => ['value'], 'expected' => 'boolean', 'received' => 'object']]];
        yield 'string from float' => ['value' => ['value' => 123.45], 'className' => ShapeWithString::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received double', 'path' => ['value'], 'expected' => 'string', 'received' => 'double']]];
        yield 'integer from float' => ['value' => ['value' => 123.45], 'className' => ShapeWithInt::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received double', 'path' => ['value'], 'expected' => 'integer', 'received' => 'double']]];
        yield 'integer from string' => ['value' => ['value' => 'not numeric'], 'className' => ShapeWithInt::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received string', 'path' => ['value'], 'expected' => 'integer', 'received' => 'string']]];
        yield 'integer from object' => ['value' => ['value' => new stdClass()], 'className' => ShapeWithInt::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received object', 'path' => ['value'], 'expected' => 'integer', 'received' => 'object']]];

        yield 'nested shape' => ['value' => ['shapeWithOptionalTypes' => ['stringBased' => '123', 'optionalInt' => 'not an int']], 'className' => NestedShape::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected integer, received string', 'path' => ['shapeWithOptionalTypes', 'optionalInt'], 'expected' => 'integer', 'received' => 'string'], ['code' => 'invalid_type', 'message' => 'Required', 'path' => ['shapeWithBool'], 'expected' => 'object', 'received' => 'undefined']]];
    }

    /**
     * @param class-string<object> $className
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

    public static function instantiate_shape_object_dataProvider(): Generator
    {
        yield 'from array matching all constraints' => ['value' => ['givenName' => 'Some first name', 'familyName' => 'Some last name'], 'className' => FullName::class, 'expectedResult' => '{"givenName":"Some first name","familyName":"Some last name"}'];
        yield 'from iterable matching all constraints' => ['value' => new ArrayIterator(['givenName' => 'Some first name', 'familyName' => 'Some last name']), 'className' => FullName::class, 'expectedResult' => '{"givenName":"Some first name","familyName":"Some last name"}'];
        yield 'from array without optionals' => ['value' => ['stringBased' => 'Some value'], 'className' => ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":"Some value","optionalStringBased":null,"optionalInt":null,"optionalBool":false,"optionalString":null}'];
        yield 'from array with optionals' => ['value' => ['stringBased' => 'Some value', 'optionalString' => 'optionalString value', 'optionalStringBased' => 'oSB value', 'optionalInt' => 42, 'optionalBool' => true], 'className' => ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":"Some value","optionalStringBased":"oSB value","optionalInt":42,"optionalBool":true,"optionalString":"optionalString value"}'];
        yield 'from array with optionals and coercion' => ['value' => ['stringBased' => 'Some value', 'optionalString' => new class { public function __toString() { return 'optionalString value'; }}, 'optionalStringBased' => 'oSB value', 'optionalInt' => '123', 'optionalBool' => 1], 'className' => ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":"Some value","optionalStringBased":"oSB value","optionalInt":123,"optionalBool":true,"optionalString":"optionalString value"}'];
        yield 'from array with optionals and coercion 2' => ['value' => ['stringBased' => 'Some value', 'optionalString' => new class { public function __toString() { return 'optionalString value'; }}, 'optionalStringBased' => 'oSB value', 'optionalInt' => 55.0, 'optionalBool' => '0'], 'className' => ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":"Some value","optionalStringBased":"oSB value","optionalInt":55,"optionalBool":false,"optionalString":"optionalString value"}'];
        yield 'from array with null-values for optionals' => ['value' => ['stringBased' => 'Some value', 'optionalStringBased' => null, 'optionalInt' => null, 'optionalBool' => null, 'optionalString' => null], 'className' => ShapeWithOptionalTypes::class, 'expectedResult' => '{"stringBased":"Some value","optionalStringBased":null,"optionalInt":null,"optionalBool":null,"optionalString":null}'];
        yield 'todo' => ['value' => ['latitude' => 33, 'longitude' => '123.45'], 'className' => GeoCoordinates::class, 'expectedResult' => '{"longitude":{"value":123.45},"latitude":{"value":33}}'];
        $class = new stdClass();
        $class->givenName = 'Some first name';
        $class->familyName = 'Some last name';
        yield 'from stdClass matching all constraints' => ['value' => $class, 'className' => FullName::class, 'expectedResult' => '{"givenName":"Some first name","familyName":"Some last name"}'];
    }

    #[DataProvider('instantiate_shape_object_dataProvider')]
    public function test_instantiate_shape_object(mixed $value, string $className, string $expectedResult): void
    {
        /** @var class-string<object> $className */
        self::assertSame($expectedResult, json_encode(instantiate($className, $value), JSON_THROW_ON_ERROR));
    }

    public static function instantiate_string_based_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => GivenName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received null', 'path' => [], 'expected' => 'string', 'received' => 'null']]];
        yield 'from object' => ['value' => new stdClass(), 'className' => GivenName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received object', 'path' => [], 'expected' => 'string', 'received' => 'object']]];
        yield 'from boolean' => ['value' => false, 'className' => GivenName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received boolean', 'path' => [], 'expected' => 'string', 'received' => 'boolean']]];
        yield 'from float' => ['value' => 2.0, 'className' => GivenName::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected string, received double', 'path' => [], 'expected' => 'string', 'received' => 'double']]];

        yield 'from string violating minLength' => ['value' => 'ab', 'className' => GivenName::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'String must contain at least 3 character(s)', 'path' => [], 'type' => 'string', 'minimum' => 3, 'inclusive' => true, 'exact' => false]]];
        yield 'from string violating maxLength' => ['value' => 'This is a bit too long', 'className' => GivenName::class, 'expectedIssues' => [['code' => 'too_big', 'message' => 'String must contain at most 20 character(s)', 'path' => [], 'type' => 'string', 'maximum' => 20, 'inclusive' => true, 'exact' => false]]];
        yield 'from string violating pattern' => ['value' => 'magic foo', 'className' => NotMagic::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Value does not match regular expression', 'path' => [], 'validation' => 'regex']]];

        yield 'from string violating format "email"' => ['value' => 'not.an@email', 'className' => EmailAddress::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid email', 'path' => [], 'validation' => 'email']]];
        yield 'from string violating format "uri"' => ['value' => 'not.a.uri', 'className' => Uri::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid uri', 'path' => [], 'validation' => 'uri']]];
        yield 'from string violating format "date"' => ['value' => 'not.a.date', 'className' => Date::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid date', 'path' => [], 'validation' => 'date']]];
        yield 'from string violating format "date" because value contains time part' => ['value' => '2025-02-15 13:12:11', 'className' => Date::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid date', 'path' => [], 'validation' => 'date']]];
        yield 'from string custom "date" validation' => ['value' => (new DateTimeImmutable('+1 day'))->format('Y-m-d'), 'className' => Date::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Future dates are not allowed', 'path' => [], 'params' => ['some' => 'param']]]];
        yield 'from string violating format "date_time"' => ['value' => 'not.a.date', 'className' => DateTime::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid date_time', 'path' => [], 'validation' => 'date_time']]];
        yield 'from string violating format "date_time" because time part is missing' => ['value' => '2025-02-15', 'className' => DateTime::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid date_time', 'path' => [], 'validation' => 'date_time']]];
        yield 'from string violating format "uuid"' => ['value' => 'not.a.uuid', 'className' => Uuid::class, 'expectedIssues' => [['code' => 'invalid_string', 'message' => 'Invalid uuid', 'path' => [], 'validation' => 'uuid']]];

        yield 'from string violating multiple constraints' => ['value' => 'invalid', 'className' => ImpossibleString::class, 'expectedIssues' => [['code' => 'too_small', 'message' => 'String must contain at least 10 character(s)', 'path' => [], 'type' => 'string', 'minimum' => 10, 'inclusive' => true, 'exact' => false], ['code' => 'too_big', 'message' => 'String must contain at most 2 character(s)', 'path' => [], 'type' => 'string', 'maximum' => 2, 'inclusive' => true, 'exact' => false], ['code' => 'invalid_string', 'message' => 'Value does not match regular expression', 'path' => [], 'validation' => 'regex'], ['code' => 'invalid_string', 'message' => 'Invalid email', 'path' => [], 'validation' => 'email']]];
    }

    /**
     * @param class-string<object> $className
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
        yield 'from string that matches constraints' => ['value' => 'this is valid', 'className' => GivenName::class, 'expectedResult' => 'this is valid'];
        yield 'from string that matches pattern' => ['value' => 'this is not magic', 'className' => NotMagic::class, 'expectedResult' => 'this is not magic'];
        yield 'from integer' => ['value' => 123, 'className' => NotMagic::class, 'expectedResult' => '123'];
        yield 'from stringable object' => ['value' => new class {
            public function __toString()
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

    public static function instantiate_interface_object_failing_dataProvider(): Generator
    {
        yield 'from null' => ['value' => null, 'className' => SomeInterface::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received null', 'path' => [], 'expected' => 'interface', 'received' => 'null']]];
        yield 'from object' => ['value' => new stdClass(), 'className' => SomeInterface::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing key "__type"', 'path' => [], 'params' => []]]];
        yield 'from boolean' => ['value' => false, 'className' => SomeInterface::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received boolean', 'path' => [], 'expected' => 'interface', 'received' => 'boolean']]];
        yield 'from integer' => ['value' => 1234, 'className' => SomeInterface::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received integer', 'path' => [], 'expected' => 'interface', 'received' => 'integer']]];
        yield 'from float' => ['value' => 2.0, 'className' => SomeInterface::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Expected object, received double', 'path' => [], 'expected' => 'interface', 'received' => 'double']]];
        yield 'from array without __type' => ['value' => ['someKey' => 'someValue'], 'className' => SomeInterface::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing key "__type"', 'path' => [], 'params' => []]]];
        yield 'from array with invalid __type' => ['value' => ['__type' => 123], 'className' => SomeInterface::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Key "__type" has to be a string, got: int', 'path' => [], 'params' => []]]];
        yield 'from array with unknown __type' => ['value' => ['__type' => 'NoClassName'], 'className' => SomeInterface::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Key "__type" has to be a valid class name, got: "NoClassName"', 'path' => [], 'params' => []]]];
        yield 'from array with __type that is not an instance of the interface' => ['value' => ['__type' => ShapeWithInt::class, 'value' => '123'], 'className' => SomeInterface::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'The given "__type" of "Wwwision\\Types\\Tests\\PHPUnit\\ShapeWithInt" is not an implementation of SomeInterface', 'path' => [], 'params' => []]]];
        yield 'from array with valid __type but invalid remaining values' => ['value' => ['__type' => GivenName::class], 'className' => SomeInterface::class, 'expectedIssues' => [['code' => 'custom', 'message' => 'Missing keys for interface of type SomeInterface', 'path' => [], 'params' => []]]];
        yield 'from array with valid __type but missing properties' => ['value' => ['__type' => FullName::class, 'givenName' => 'John'], 'className' => SomeInterface::class, 'expectedIssues' => [['code' => 'invalid_type', 'message' => 'Required', 'path' => ['familyName'], 'expected' => 'string', 'received' => 'undefined']]];
    }

    /**
     * @param class-string<object> $className
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
        yield 'from array with __type and __value' => ['value' => ['__type' => GivenName::class, '__value' => 'this is valid'], 'className' => SomeInterface::class, 'expectedResult' => '"this is valid"'];
        yield 'from array and remaining values' => ['value' => ['__type' => FullName::class, 'givenName' => 'some given name', 'familyName' => 'some family name'], 'className' => SomeInterface::class, 'expectedResult' => '{"givenName":"some given name","familyName":"some family name"}'];
        yield 'from valid implementation' => ['value' => Parser::instantiate(GivenName::class, 'John'), 'className' => SomeInterface::class, 'expectedResult' => '"John"'];
    }

    #[DataProvider('instantiate_interface_object_dataProvider')]
    public function test_instantiate_interface_object(mixed $value, string $className, string $expectedResult): void
    {
        /** @var class-string<object> $className */
        self::assertJsonStringEqualsJsonString($expectedResult, json_encode(instantiate($className, $value), JSON_THROW_ON_ERROR));
    }

    public function test_interface_implementationSchemas(): void
    {
        $interfaceSchema = Parser::getSchema(SomeInterface::class);
        self::assertInstanceOf(InterfaceSchema::class, $interfaceSchema);

        $implementationSchemaNames = array_map(static fn (Schema $schema) => $schema->getName(), $interfaceSchema->implementationSchemas());
        self::assertSame(['GivenName', 'FamilyName', 'FullName'], $implementationSchemaNames);
    }

    public static function objects_dataProvider(): Generator
    {
        yield 'enum' => ['instance' => Title::MR];
        yield 'integer' => ['instance' => Parser::instantiate(Age::class, 55)];
        yield 'list' => ['instance' => Parser::instantiate(GivenNames::class, ['John', 'Jane', 'Max'])];
        yield 'shape' => ['instance' => Parser::instantiate(FullName::class, ['givenName' => 'John', 'familyName' => 'Doe'])];
        yield 'string' => ['instance' => Parser::instantiate(GivenName::class, 'Jane')];
    }

    #[DataProvider('objects_dataProvider')]
    public function test_instantiate_returns_same_object_if_it_is_already_a_valid_type(object $instance): void
    {
        self::assertSame($instance, Parser::getSchema($instance::class)->instantiate($instance));
    }

    public function test_instantiate_returns_same_instance_if_object_implements_interface_of_schema(): void
    {
        $instance = Parser::instantiate(GivenName::class, 'John');
        self::assertSame($instance, Parser::getSchema(SomeInterface::class)->instantiate($instance));
    }
}

#[StringBased(minLength: 3, maxLength: 20)]
#[Description('First name of a person')]
final class GivenName implements SomeInterface, JsonSerializable
{
    private function __construct(public readonly string $value)
    {
    }

    public function someMethod(): string
    {
        return 'bar';
    }

    public function someOtherMethod(): FamilyName
    {
        return instantiate(self::class, $this->value);
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}

#[StringBased(minLength: 3, maxLength: 20)]
#[Description('Last name of a person')]
final class FamilyName implements JsonSerializable, SomeInterface
{
    private function __construct(public readonly string $value)
    {
    }

    public function someMethod(): string
    {
        return 'bar';
    }

    public function someOtherMethod(): FamilyName
    {
        return instantiate(self::class, $this->value);
    }

    public function jsonSerialize(): string
    {
        return $this->value;
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
final class FullName implements SomeInterface
{
    public function __construct(
        #[Description('Overridden given name description')]
        public readonly GivenName $givenName,
        public readonly FamilyName $familyName,
    )
    {
    }

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

#[ListBased(itemClassName: Uri::class)]
final class UriMap implements IteratorAggregate, JsonSerializable
{
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

    public function jsonSerialize(): array
    {
        return $this->entries;
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
final class Uri implements JsonSerializable
{
    private function __construct(public readonly string $value)
    {
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
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

final class NestedShape {
    public function __construct(
        public readonly ShapeWithOptionalTypes $shapeWithOptionalTypes,
        public readonly ShapeWithBool $shapeWithBool,
    ) {
    }
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

#[Description('SomeInterface description')]
interface SomeInterface {
    #[Description('Custom description for "someMethod"')]
    public function someMethod(): string;
    #[Description('Custom description for "someOtherMethod"')]
    public function someOtherMethod(): ?FamilyName;
}

#[FloatBased(minimum: -180.0, maximum: 180.5)]
final class Longitude {
    private function __construct(
        public readonly float $value,
    ) {}
}

#[FloatBased(minimum: -90, maximum: 90)]
final class Latitude {
    private function __construct(
        public readonly float $value,
    ) {}
}

final class GeoCoordinates {
    public function __construct(
        public readonly Longitude $longitude,
        public readonly Latitude $latitude
    ) {}
}


interface SomeInvalidInterface {
    public function methodWithParameters(string $param = null): string;
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
    private function __construct(private readonly array $items) {}
}