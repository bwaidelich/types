<?php

declare(strict_types=1);

namespace Wwwision\Types\Tests\PHPUnit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wwwision\Types\DynamicSchema;
use Wwwision\Types\Options;
use Wwwision\Types\Parser;
use Wwwision\Types\Schema\Dynamic\DynamicList;
use Wwwision\Types\Schema\Dynamic\DynamicRecord;
use Wwwision\Types\Schema\Dynamic\DynamicValue;
use Wwwision\Types\Schema\Dynamic\ShapeExtender;
use Wwwision\Types\Schema\IntegerSchema;
use Wwwision\Types\Schema\ListSchema;
use Wwwision\Types\Schema\Schema;
use Wwwision\Types\Schema\ShapeSchema;
use Wwwision\Types\Schema\StringSchema;
use Wwwision\Types\Schema\Target\ClassTarget;
use Wwwision\Types\Schema\Target\DynamicTarget;
use Wwwision\Types\Tests\Fixture;

use function Wwwision\Types\instantiate;

require_once __DIR__ . '/../Fixture/Fixture.php';

#[CoversClass(DynamicSchema::class)]
#[CoversClass(DynamicTarget::class)]
#[CoversClass(ClassTarget::class)]
#[CoversClass(DynamicRecord::class)]
#[CoversClass(DynamicValue::class)]
#[CoversClass(DynamicList::class)]
#[CoversClass(ShapeExtender::class)]
#[CoversClass(StringSchema::class)]
#[CoversClass(ShapeSchema::class)]
#[CoversClass(IntegerSchema::class)]
#[CoversClass(ListSchema::class)]
#[CoversClass(Parser::class)]
final class DynamicSchemaTest extends TestCase
{
    public function test_class_based_instantiation_is_unchanged(): void
    {
        $familyName = instantiate(Fixture\FamilyName::class, 'Doe');
        self::assertInstanceOf(Fixture\FamilyName::class, $familyName);
        self::assertSame('Doe', $familyName->value);

        $fullName = instantiate(Fixture\FullName::class, ['givenName' => 'Jane', 'familyName' => 'Doe']);
        self::assertInstanceOf(Fixture\FullName::class, $fullName);
        self::assertSame('Jane', $fullName->givenName->value);
    }

    public function test_class_based_and_dynamic_schemas_share_the_same_implementation(): void
    {
        $classBased = Parser::getSchema(Fixture\FullName::class);
        $dynamic = DynamicSchema::shape('Point', [
            'x' => DynamicSchema::string('X'),
            'y' => DynamicSchema::string('Y'),
        ]);
        // No separate "DynamicShapeSchema" type – both are the very same class.
        self::assertInstanceOf(ShapeSchema::class, $classBased);
        self::assertInstanceOf(ShapeSchema::class, $dynamic);
        // ...and a consumer that only knows the Schema interface sees an identical surface.
        self::assertSame(['type', 'name', 'description', 'properties'], array_keys($classBased->jsonSerialize()));
        self::assertSame(['type', 'name', 'description', 'properties'], array_keys($dynamic->jsonSerialize()));
        self::assertSame('object', $dynamic->getType());
        self::assertSame('Point', $dynamic->getName());
    }

    public function test_dynamic_shape_instantiates_to_a_record_reusing_real_value_objects(): void
    {
        $schema = DynamicSchema::shape('Greeting', [
            'name' => Parser::getSchema(Fixture\FamilyName::class),
        ]);
        $result = $schema->instantiate(['name' => 'Doe'], Options::create());

        self::assertInstanceOf(DynamicRecord::class, $result);
        // the inherited, class-based property is still a real, validated value object
        $name = $result->get('name');
        self::assertInstanceOf(Fixture\FamilyName::class, $name);
        self::assertSame('Doe', $name->value);
    }

    public function test_dynamic_scalar_validates_and_wraps(): void
    {
        $schema = DynamicSchema::string('Code', minLength: 2, maxLength: 4);
        $result = $schema->instantiate('AB', Options::create());
        self::assertInstanceOf(DynamicValue::class, $result);
        self::assertSame('AB', $result->value);
    }

    public function test_extending_a_class_based_shape_keeps_inherited_value_objects(): void
    {
        $base = Parser::getSchema(Fixture\FullName::class);
        self::assertInstanceOf(ShapeSchema::class, $base);

        $extended = DynamicSchema::extend($base, 'ExtendedFullName')
            ->withProperty('nickname', DynamicSchema::string('Nickname'))
            ->build();

        $result = $extended->instantiate(['givenName' => 'Jane', 'familyName' => 'Doe', 'nickname' => 'JJ'], Options::create());

        self::assertInstanceOf(DynamicRecord::class, $result);
        self::assertInstanceOf(Fixture\GivenName::class, $result->get('givenName'));
        self::assertInstanceOf(Fixture\FamilyName::class, $result->get('familyName'));
        $nickname = $result->get('nickname');
        self::assertInstanceOf(DynamicValue::class, $nickname);
        self::assertSame('JJ', $nickname->value);
    }

    public function test_dynamic_record_exposes_its_schema_and_is_iterable(): void
    {
        $schema = DynamicSchema::shape('Coordinate', [
            'latitude' => DynamicSchema::float('Latitude'),
            'longitude' => DynamicSchema::float('Longitude'),
        ]);
        $record = $schema->instantiate(['latitude' => 51.5, 'longitude' => -0.1], Options::create());
        self::assertInstanceOf(DynamicRecord::class, $record);

        // it carries its schema, so consumers can introspect names/types/descriptions
        self::assertSame($schema, $record->getSchema());
        self::assertSame(['latitude', 'longitude'], array_keys($record->getSchema()->propertySchemas));
        self::assertSame(['latitude', 'longitude'], $record->propertyNames());

        // ...and it is iterable as propertyName => value
        $collected = [];
        foreach ($record as $name => $value) {
            self::assertInstanceOf(DynamicValue::class, $value);
            $collected[$name] = $value->value;
        }
        self::assertSame(['latitude' => 51.5, 'longitude' => -0.1], $collected);
    }

    public function test_dynamic_value_and_list_expose_their_schema(): void
    {
        $listSchema = DynamicSchema::list('Tags', DynamicSchema::string('Tag'));
        $list = $listSchema->instantiate(['a', 'b'], Options::create());
        self::assertInstanceOf(DynamicList::class, $list);
        self::assertSame($listSchema, $list->getSchema());

        $first = $listSchema->instantiate(['x'], Options::create());
        self::assertInstanceOf(DynamicList::class, $first);
        foreach ($first as $tag) {
            self::assertInstanceOf(DynamicValue::class, $tag);
            self::assertSame('Tag', $tag->getSchema()->getName());
        }
    }

    public function test_dynamic_record_is_immutable(): void
    {
        $record = DynamicSchema::shape('Point', ['x' => DynamicSchema::integer('X')])
            ->instantiate(['x' => 1], Options::create());
        self::assertInstanceOf(DynamicRecord::class, $record);
        $this->expectException(\LogicException::class);
        $record->__set('x', 2);
    }

    public function test_dynamic_integer_validates_and_wraps(): void
    {
        $schema = DynamicSchema::integer('Quantity', minimum: 1, maximum: 10);
        $result = $schema->instantiate('5', Options::create());
        self::assertInstanceOf(DynamicValue::class, $result);
        self::assertSame(5, $result->value);
    }

    public function test_dynamic_list_of_real_value_objects(): void
    {
        $schema = DynamicSchema::list('FamilyNames', Parser::getSchema(Fixture\FamilyName::class), minCount: 1);
        $result = $schema->instantiate(['Doe', 'Roe'], Options::create());
        self::assertInstanceOf(DynamicList::class, $result);
        self::assertCount(2, $result);
        foreach ($result as $item) {
            self::assertInstanceOf(Fixture\FamilyName::class, $item);
        }
    }

    public function test_a_consumer_typed_against_schema_treats_both_identically(): void
    {
        $describe = static fn(Schema $schema): string => $schema->getType() . ':' . $schema->getName();
        self::assertSame('object:FullName', $describe(Parser::getSchema(Fixture\FullName::class)));
        self::assertSame('object:Point', $describe(DynamicSchema::shape('Point', [])));
    }
}
