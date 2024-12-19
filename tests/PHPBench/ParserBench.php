<?php

declare(strict_types=1);

namespace Wwwision\Types\Tests\PHPBench;

use Generator;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use Wwwision\Types\Parser;
use Wwwision\Types\Tests\Fixture;

require_once __DIR__ . '/../Fixture/Fixture.php';

final class ParserBench
{
    public function class_names(): Generator
    {
        yield 'Enum' => ['className' => Fixture\Title::class, 'input' => 'MR'];
        yield 'IntegerBased' => ['className' => Fixture\Age::class, 'input' => 55];
        yield 'List' => ['className' => Fixture\GivenNames::class, 'input' => ['John', 'Max', 'Jane']];
        yield 'Shape' => ['className' => Fixture\FullName::class, 'input' => ['givenName' => 'Jane', 'familyName' => 'Doe']];
        yield 'StringBased' => ['className' => Fixture\GivenName::class, 'input' => 'Some value'];
    }

    /**
     * @param array{className: class-string, input: mixed} $params
     */
    #[Revs(100)]
    #[Iterations(3)]
    #[ParamProviders('class_names')]
    public function bench_getSchema(array $params): void
    {
        Parser::getSchema($params['className']);
    }

    /**
     * @param array{className: class-string, input: mixed} $params
     */
    #[Revs(100)]
    #[Iterations(3)]
    #[ParamProviders('class_names')]
    public function bench_instantiate(array $params): void
    {
        Parser::instantiate($params['className'], $params['input']);
    }

}
