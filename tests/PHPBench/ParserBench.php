<?php

declare(strict_types=1);

namespace Wwwision\Types\Tests\PHPBench;

use ArrayIterator;
use DateTimeImmutable;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use stdClass;
use Traversable;
use Wwwision\Types\Attributes\Description;
use Wwwision\Types\Attributes\IntegerBased;
use Wwwision\Types\Attributes\ListBased;
use Wwwision\Types\Attributes\StringBased;
use Wwwision\Types\Parser;
use Wwwision\Types\Schema\StringTypeFormat;

final class ParserBench
{

    public function class_names(): Generator
    {
        yield 'Enum' => ['className' => Title::class, 'input' => 'MR'];
        yield 'IntegerBased' => ['className' => Age::class, 'input' => 55];
        yield 'List' => ['className' => GivenNames::class, 'input' => ['John', 'Max', 'Jane']];
        yield 'Shape' => ['className' => FullName::class, 'input' => ['givenName' => 'Jane', 'familyName' => 'Doe']];
        yield 'StringBased' => ['className' => GivenName::class, 'input' => 'Some value'];
    }

    #[Revs(100)]
    #[Iterations(3)]
    #[ParamProviders('class_names')]
    public function bench_getSchema(array $params): void
    {
        Parser::getSchema($params['className']);
    }

    #[Revs(100)]
    #[Iterations(3)]
    #[ParamProviders('class_names')]
    public function bench_instantiate(array $params): void
    {
        Parser::instantiate($params['className'], $params['input']);
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