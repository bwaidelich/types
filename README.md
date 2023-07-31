Library to narrow the scope of your PHP types with JSON Schema based [attributes](#attributes) allowing for validating
and mapping unknown data.

| Why this package might be for you                                                                        | Why this package might NOT be for you                                                  |
|----------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------|
| Extends the PHP type system                                                                              | Uses reflection at runtime (see [performance considerations](#performance))            |
| Great [integrations](#integrations)                                                                      | Partly unconventional [best practices](#best-practices)                                |
| Simple [Generics](#list-generic-array)                                                                   | Static class, i.e. global (namespaced) `instantiate()` method                          |
| No need to implement interfaces or extend base classes                                                   | Very young project – I certainly would be skeptical if I hadn't written this myself ;) |
| Small footprint (just one public function/class and a couple of [3rd party dependencies](#dependencies)) | You just don't like me.. pff.. whateva                                                 |

## Usage

This package can be installed via [composer](https://getcomposer.org):

```bash
composer require wwwision/types
```

Afterward, three steps are required to profit from the type safety of this package.

Given, you have the following `Contact` entity:

```php
class Contact {
    public function __construct(public string $name, public int $age) {}
}
```

This class has a couple of issues:

* The values are mutable, so every part of the system can just change them without control (`$contact->name = '
  changed';)
* The values of `$name` and `$age` are unbound – this makes the type very fragile. For example, you could specify a name
  with thousands of characters or a negative number for the age, possibly breaking at the integration level
* There is no human readably type information – is the $name supposed to be a full name, just the given name or a family
  name, ...?

### 0. Create classes for your Value Objects

> **Note**
> This list is 0-based because that part is slightly out of scope, it is merely a general recommendation

```php
final class ContactName {
    public function __construct(public string $value) {}
}
final class ContactAge {
    public function __construct(public int $value) {}
}
```

### 1. Add attributes

By adding one of the provided [attributes](#attributes), schema information and documentation can be added to type
classes:

```php
#[Description('The full name of a contact, e.g. "John Doe"')]
#[StringBased(minLength: 1, maxLength: 200)]
final class ContactName {
    public function __construct(public string $value) {}
}

#[Description('The current age of a contact, e.g. 45')]
#[IntegerBased(minimum: 1, maximum: 130)]
final class ContactAge {
    public function __construct(public int $value) {}
}
```

> **Note**
> In most cases it makes sense to specify an upper bound for your types because that allows you to re-use that at "the
> edges" (e.g. for frontend validation and database schemas)

### 2. Make constructor private and classes immutable

By making constructors private, validation can be enforced providing confidence that the objects don't violate their
allowed range.
See [best practices](#best-practices) for more details.

```php
#[Description('The full name of a contact, e.g. "John Doe"')]
#[StringBased(minLength: 1, maxLength: 200)]
final class ContactName {
    private function __construct(public readonly string $value) {}
}

#[Description('The current age of a contact, e.g. 45')]
#[IntegerBased(minimum: 1, maximum: 130)]
final class ContactAge {
    private function __construct(public readonly int $value) {}
}

final class Contact {
    public function __construct(
        public readonly ContactName $name,
        public readonly ContactAge $age,
    ) {}
}
```

### 3. Use `instantiate()` to create instances

With private constructors in place, the `instantiate()` function should be used to create new instances of the affected
classes:

```php
// ...
instantiate(Contact::class, ['name' => 'John Doe', 'age' => 45]);
```

> **Note**
> In practice you'll realize that you hardly need to create new Entity/Value Object instances within your application
> logic but mostly in the infrastructure layer. E.g. a `DatabaseContactRepository` might return a `Contacts` object.

<details>
<summary><h4>Example: Database integration</h4></summary>

```php
// ...

#[ListBased(itemClassName: Contact::class)]
final class Contacts implements IteratorAggregate {
    private function __construct(private readonly array $contacts) {}
    
    public function getIterator() : Traversable {
        return new ArrayIterator($this->contacts);
    }
}

interface ContactRepository {
    public function findByName(ContactName $name): Contacts;
}

final class DatabaseContactRepository implements ContactRepository {

    public function __construct(private readonly PDO $pdo) {}

    public function findByName(ContactName $name): Contacts
    {
        $statement = $this->pdo->prepare('SELECT name, age FROM contacts WHERE name = :name');
        $statement->execute(['name' => $name->value]);
        return instantiate(Contacts::class, $statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
```

</details>

## Best practices

In order to gain the most with this package, a couple of rules should be considered:

### Private constructors

In order to allow data to be validated everywhere, there must be no way to instantiate
an [Integer-](#integerbased), [String-](#stringbased) or [ListBased](#listbased) class other than with the
provided `instantiate()` method.

Therefore, constructors of Value Objects should be private:

```php
#[StringBased]
final class SomeValueObject {
    private function __construct(public readonly string $value) {}
}
```

> **Note**
> For Shapes (i.e. composite) objects that rule doesn't apply, because all of their properties are valid if the above
> rule is followed:

```php
// ...

final class SomeComposite {
    public function __construct(
        public readonly SomeValueObject $alreadyValidated,
        public readonly bool $neverInvalid,
    ) {}
}

// this is fine:
instantiate(SomeComposite::class, ['alreadyValidated' => 'some value', 'neverInvalid' => true]);

// and so is this:
new SomeComposite(instantiate(SomeValueObject::class, 'some value'), true);
```

### Final classes

In my opinion, classes in PHP should be final by default. For the core domain types this is especially true because
inheritance could lead to invalid schemas and failing validation.
Instead, _composition_ should be used where it applies.

### Immutability

In order to guarantee the correctness of the types, there should be no way to change a value without re-applying
validation.
The easiest way to achieve this, is to make those types immutable – and this comes with some other benefits as well.

The `readonly` keyword can be used on properties (with PHP 8.2+ even on the class itself) to ensure immutability on the
PHP type level.

If types should be updatable from the outside, ...

* a **new instance** should be returned
* and it should **not call the private constructor** but use `instantiate()` in order to apply validation

```php
#[StringBased(format: StringTypeFormat::date, pattern: '^1980')]
final class Date {
    private function __construct(public readonly string $value) {}
    
    public function add(\DateInterval $interval): self
    {
        return instantiate(self::class, \DateTimeImmutable::createFromFormat('Y-m-d', $this->value)->add($interval)->format('Y-m-d'));
    }
}

$date = instantiate(Date::class, '1980-12-30');
$date = $date->add(new \DateInterval('P1D'));

// this is fine
assert($date->value === '1980-12-31');

// this is not because of the "pattern"
$date = $date->add(new \DateInterval('P1D'));

// Exception: InvalidArgumentException: Failed to instantiate Date: Value "1981-01-01" does not match the regular expression "/^1980/"

```

## Attributes

### Description

The `Description` attribute allows you to add some domain specific documentation to classes and parameters.

<details>
<summary><h4>Example: Class with description</h4></summary>

```php
#[Description('This is a description for this class')]
final class SomeClass {

    public function __construct(
        #[Description('This is some overridden description for this parameter')]
        public readonly bool $someProperty,
    ) {}
}

assert(Parser::getSchema(SomeClass::class)->overriddenPropertyDescription('someProperty') === 'This is some overridden description for this parameter');
```

</details>

### IntegerBased

With the `IntegerBased` attribute you can create Value Objects that represent an integer.
It has the optional arguments

* `minimum` – to specify the allowed _minimum_ value
* `maximum` – to specify the allowed _maximum_ value

<details>
<summary><h4>Example</h4></summary>

```php
#[IntegerBased(minimum: 0, maximum: 123)]
final class SomeIntBased {
    private function __construct(public readonly int $value) {}
}

instantiate(SomeIntBased::class, '-5');

// Exception: InvalidArgumentException: Failed to instantiate SomeIntBased: Value -5 falls below the allowed minimum value of 0
```

</details>

### StringBased

With the `StringBased` attribute you can create Value Objects that represent a string.
It has the optional arguments

* `minLength` – to specify the allowed _minimum_ length of the string
* `maxLength` – to specify the allowed _maximum_ length of the string
* `pattern` – to specify a regular expression that the string has to match
* `format` – one of the predefined formats the string has to satisfy (this is a subset of
  the [JSON Schema string format](https://json-schema.org/understanding-json-schema/reference/string.html#format))

<details>
<summary><h4>Example: String Value Object with min and max length constraints</h4></summary>

```php
#[StringBased(minLength: 1, maxLength: 200)]
final class GivenName {
    private function __construct(public readonly string $value) {}
}

instantiate(GivenName::class, '');

// Exception: InvalidArgumentException: Failed to instantiate GivenName: Value "" does not have the required minimum length of 1 characters
```

</details>

<details>
<summary><h4>Example: String Value Object with format and pattern constraints</h4></summary>

Just like with JSON Schema, `format` and `pattern` can be _combined_ to further narrow the type:

```php
#[StringBased(format: StringTypeFormat::email, pattern: '@your.org$')]
final class EmployeeEmailAddress {
    private function __construct(public readonly string $value) {}
}

instantiate(EmployeeEmailAddress::class, 'not@your.org.localhost');

// Exception: InvalidArgumentException: Failed to instantiate EmployeeEmailAddress: Value "not@your.org.localhost" does not match the regular expression "/@your.org$/"
```

</details>

### ListBased

With the `ListBased` attribute you can create generic lists (i.e. collections, arrays, sets, ...) of the
specified `itemClassName`.
It has the optional arguments

* `minCount` – to specify how many items the list has to contain _at least_
* `maxCount` – to specify how many items the list has to contain _at most_

<details>
<summary><h4>Example: Simple generic array</h4></summary>

```php
#[StringBased]
final class Hobby {
    private function __construct(public readonly string $value) {}
}

#[ListBased(itemClassName: Hobby::class)]
final class Hobbies implements IteratorAggregate {
    private function __construct(private readonly array $hobbies) {}
    
    public function getIterator() : Traversable {
        return new ArrayIterator($this->hobbies);
    }
}

instantiate(Hobbies::class, ['Soccer', 'Ping Pong', 'Guitar']);
```

</details>

<details>
<summary><h4>Example: More verbose generic array with type hints and min and max count constraints</h4></summary>

The following example shows a more realistic implementation of a List, with:

* An `@implements` annotation that allows IDEs and static type analyzers to improve the DX
* A [Description](#description) attribute
* `minCount` and `maxCount` validation
* `Countable` and `JsonSerializable` implementation (just as an example, this is not required for the validation to
  work)

```php
// ...

/**
 * @implements IteratorAggregate<Hobby> 
 */
#[Description('A list of hobbies')]
#[ListBased(itemClassName: Hobby::class, minCount: 1, maxCount: 3)]
final class HobbiesAdvanced implements IteratorAggregate, Countable, JsonSerializable {
    /** @param array<Hobby> $hobbies */
    private function __construct(private readonly array $hobbies) {}
    
    public function getIterator() : Traversable {
        return new ArrayIterator($this->hobbies);
    }
    
    public function count(): int {
        return count($this->hobbies);
    }
    
    public function jsonSerialize() : array {
        return array_values($this->hobbies);
    }
}

instantiate(HobbiesAdvanced::class, ['Soccer', 'Ping Pong', 'Guitar', 'Gaming']);

// Exception: InvalidArgumentException: Failed to instantiate HobbiesAdvanced: Number of elements (4) is more than allowed max count of 3
```

</details>

## Composite types

The examples above demonstrate how to create very specific Value Objects with strict validation and introspection.


<details>
<summary><h4>Example: Complex composite object</h4></summary>

```php
#[StringBased]
final class GivenName {
    private function __construct(public readonly string $value) {}
}

#[StringBased]
final class FamilyName {
    private function __construct(public readonly string $value) {}
}

final class FullName {
    public function __construct(
        public readonly GivenName $givenName,
        public readonly FamilyName $familyName,
    ) {}
}

#[Description('honorific title of a person')]
enum HonorificTitle
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

#[Description('A contact in the system')]
final class Contact {
    public function __construct(
        public readonly HonorificTitle $title,
        public readonly FullName $name,
        #[Description('Whether the contact is registered or not')]
        public bool $isRegistered = false,
    ) {}
}

// Create a Contact instance from an array
$person = instantiate(Contact::class, ['title' => 'MRS', 'name' => ['givenName' => 'Jane', 'familyName' => 'Doe']]);
assert($person->name->familyName->value === 'Doe');
assert($person->isRegistered === false);

// Retrieve the schema for the Contact class
$schema = Parser::getSchema(Contact::class);
assert($schema->getDescription() === 'A contact in the system');
assert($schema->propertySchemas['isRegistered']->getDescription() === 'Whether the contact is registered or not');
```

</details>

## Integrations

The declarative approach of this library allows for some interesting integrations.
So far, the following two exist – Feel free to create another one and I will gladly add it to this list:

* [types/graphql](https://github.com/bwaidelich/types-graphql) – to create GraphQL schemas from PHP types
* [types/glossary](https://github.com/bwaidelich/types-glossary) – to create Markdown glossaries for all relevant PHP types

## Dependencies

This package currently relies on the following 3rd party libraries:

* [webmozart/assert](https://packagist.org/packages/webmozart/assert) – to simplify type and value assertions
* [ramsey/uuid](https://packagist.org/packages/ramsey/uuid) – for the `StringTypeFormat::uuid` check

...and has the following DEV-requirements:

* [roave/security-advisories](https://packagist.org/packages/roave/security-advisories) – to detect vulnerabilities in
  dependant packages
* [phpstan/phpstan](https://packagist.org/packages/phpstan/phpstan) – for static code analysis
* [squizlabs/php_codesniffer](https://packagist.org/packages/squizlabs/php_codesniffer) – for code style analysis
* [phpunit/phpunit](https://packagist.org/packages/phpunit/phpunit) – for unit and integration tests
* [phpbench/phpbench](https://packagist.org/packages/phpbench/phpbench) – for performance benchmarks

## Performance

This package uses Reflection in order to introspect types. So it comes with a performance hit.
Fortunately the performance of Reflection in PHP is not as bad as its reputation and while you can certainly measure a
difference, I doubt that it will have a notable effect in practice – unless you are dealing with extremely time critical
applications like realtime trading in which case you should not be using PHP in the first place... And you should
probably reconsider your life choices in general :)

Nevertheless, this package contains a runtime cache for all reflected classes. So if you return a
huge [list of the same type](#list-generic-array), the performance impact should be minimal.
I am measuring performance of the API via [PHPBench](https://github.com/phpbench/phpbench) to avoid regressions, and I
might add further caches if performance turns out to become an issue.

## Contribution

Contributions in the form of [issues](https://github.com/bwaidelich/types/issues), [pull requests](https://github.com/bwaidelich/types/pulls) or [discussions](https://github.com/bwaidelich/types/discussions) are highly appreciated

## License

See [LICENSE](./LICENSE)