Library to narrow the scope of your PHP types with JSON Schema inspired [attributes](#attributes) allowing for validating
and mapping unknown data.

| Why this package might be for you                                                                        | Why this package might NOT be for you                                                  |
|----------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------|
| Extends the PHP type system                                                                              | Uses reflection at runtime (see [performance considerations](#performance))            |
| Great [integrations](#integrations)                                                                      | Partly unconventional [best practices](#best-practices)                                |
| Simple [Generics](#generics)                                                                             | Static class, i.e. global (namespaced) `instantiate()` method                          |
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

* The values are mutable, so every part of the system can just change them without control (`$contact->name = 'changed';`)
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
<summary><b>Example: Database integration</b></summary>

```php
// ...

#[ListBased(itemClassName: Contact::class)]
final class Contacts implements IteratorAggregate {
    private function __construct(private readonly array $contacts) {}
    
    public function getIterator() : Traversable {
        yield from $this->contacts;
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

### All state fields in the constructor

This package uses reflection to parse the constructors of involved classes. Therefore the constructor should contain every variable that makes up the internal state (IMO that's a good practice anyways).
In general you should only allow state changes through the constructor and it's a good idea to mark DTO classes as `readonly`

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

// Exception: Failed to cast string of "1981-01-01" to Date: invalid_string (Value does not match regular expression)
```

## Attributes

### Description

The `Description` attribute allows you to add some domain specific documentation to classes and parameters.

<details>
<summary><b>Example: Class with description</b></summary>

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
* `examples`- to provide valid example values (since version [1.7](https://github.com/bwaidelich/types/releases/tag/1.7.0))

<details>
<summary><b>Example</b></summary>

```php
#[IntegerBased(minimum: 0, maximum: 123, examples: [0, 22, 123])]
final class SomeIntBased {
    private function __construct(public readonly int $value) {}
}

instantiate(SomeIntBased::class, '-5');

// Exception: Failed to cast string of "-5" to SomeIntBased: too_small (Number must be greater than or equal to 0)
```

</details>

### FloatBased

Starting with version [1.2](https://github.com/bwaidelich/types/releases/tag/1.2.0)

With the `FloatBased` attribute you can create Value Objects that represent a floating point number (aka double).
It has the optional arguments

* `minimum` – to specify the allowed _minimum_ value (as integer or float)
* `maximum` – to specify the allowed _maximum_ value (as integer or float)
* `examples`- to provide valid example values (since version [1.7](https://github.com/bwaidelich/types/releases/tag/1.7.0))

<details>
<summary><b>Example</b></summary>

```php
#[FloatBased(minimum: 12.34, maximum: 30, examples: [23.345, 6, 7.89])]
final class SomeFloatBased {
    private function __construct(public readonly float $value) {}
}

instantiate(SomeFloatBased::class, 12);

// Exception: Failed to cast integer value of 12 to SomeFloatBased: too_small (Number must be greater than or equal to 12.340)
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
* `examples`- to provide valid example values (since version [1.7](https://github.com/bwaidelich/types/releases/tag/1.7.0))

<details>
<summary><b>Example: String Value Object with min and max length constraints</b></summary>

```php
#[StringBased(minLength: 1, maxLength: 200)]
final class GivenName {
    private function __construct(public readonly string $value) {}
}

instantiate(GivenName::class, '');

// Exception: Failed to cast string of "" to GivenName: too_small (String must contain at least 1 character(s))
```

</details>

<details>
<summary><b>Example: String Value Object with format and pattern constraints</b></summary>

Just like with JSON Schema, `format` and `pattern` can be _combined_ to further narrow the type:

```php
#[StringBased(format: StringTypeFormat::email, pattern: '@your.org$', examples: ['john.doe@your.org', 'jane.doe@your.org'])]
final class EmployeeEmailAddress {
    private function __construct(public readonly string $value) {}
}

instantiate(EmployeeEmailAddress::class, 'not@your.org.localhost');

// Exception: Failed to cast string of "not@your.org.localhost" to EmployeeEmailAddress: invalid_string (Value does not match regular expression)
```

</details>

### ListBased

With the `ListBased` attribute you can create generic lists (i.e. collections, arrays, sets, ...) of the
specified `itemClassName`.
It has the optional arguments

* `minCount` – to specify how many items the list has to contain _at least_
* `maxCount` – to specify how many items the list has to contain _at most_

<details>
<summary><b>Example: Simple generic array</b></summary>

```php
#[StringBased]
final class Hobby {
    private function __construct(public readonly string $value) {}
}

#[ListBased(itemClassName: Hobby::class)]
final class Hobbies implements IteratorAggregate {
    private function __construct(private readonly array $hobbies) {}
    
    public function getIterator() : Traversable {
        yield from $this->hobbies;
    }
}

instantiate(Hobbies::class, ['Soccer', 'Ping Pong', 'Guitar']);
```

</details>

<details>
<summary><b>Example: More verbose generic array with type hints and min and max count constraints</b></summary>

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
        yield from $this->hobbies;
    }
    
    public function count(): int {
        return count($this->hobbies);
    }
    
    public function jsonSerialize() : array {
        return array_values($this->hobbies);
    }
}

instantiate(HobbiesAdvanced::class, ['Soccer', 'Ping Pong', 'Guitar', 'Gaming']);

// Exception: Failed to cast value of type array to HobbiesAdvanced: too_big (Array must contain at most 3 element(s))
```

</details>

## Composite types

The examples above demonstrate how to create very specific Value Objects with strict validation and introspection.
Those Value Objects can be composed into composite types (aka shape).

<details>
<summary><b>Example: Complex composite object</b></summary>

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

### Ignore unrecognized keys

Just like single-value objects, composite objects can be instantiated from unstructured input:

```php
final class Composite {
    public function __construct(
        public readonly string $property1,
        public readonly bool $property2
    ) {}
}
$instance = instantiate(Composite::class, ['property1' => 'foo', 'property2' => 'true']);
assert($instance instanceof Composite);
```

This will fail, if the input contains keys that do not map to a property of the target class:

```php
// ...
try {
    instantiate(Composite::class, ['property1' => 'foo', 'property2' => 'true', 'unknownProperty' => 'bar']);
} catch (CoerceException $e) {
    $exception = $e->getMessage();
}
assert($exception === 'Failed to cast value of type array to Composite: unrecognized_keys (Unrecognized key(s) in object: \'unknownProperty\')');
```

Sometimes it can be useful to ignore those unknown properties instead, e.g. when consuming 3rd party apis. Starting with version [1.8](https://github.com/bwaidelich/types/releases/tag/1.8.0), the `ignoreUnrecognizedKeys` option can be specified to achieve that:

```php
// ...
use Wwwision\Types\Options;

$instance = instantiate(Composite::class, ['property1' => 'foo', 'property2' => 'true', 'unknownProperty' => 'bar'], Options::create(ignoreUnrecognizedKeys: true));
assert($instance instanceof Composite);
```

## Generics

Generics won't make it into PHP most likely (see this [video from Brent](https://www.youtube.com/watch?v=JtmRG5lCENA) that explains why that is the case).

The [ListBased](#listbased) attribute allows for relatively easily creation of type-safe collections of a specific item type.

Currently you still have to create a custom class for that, but I don't think that this is a big problem because mostly a common collection class won't fit all the specific requirements.
For example: `PostResults` could provide different functions and implementations than a `Posts` set (the former might be unbound, the latter might have a `minCount` constraint etc).

### Further thoughts

I'm thinking about adding a more generic (no pun intended) way to allow for common classes without having to specify the `itemClassName` in the attribute but at instantiation time, maybe something along the lines of

```php (no test)
#[Generic('TKey', 'TValue')]
final class Collection {
    // ...
}

// won't work as of now:
$posts = generic(Collection::class, $dbRows, TKey: Types::int(), TValue: Types::classOf(Post::class));
```

But it adds some more oddities and I currently don't really need it becaused of the reasons mentioned above.

## Interfaces

Starting with version [1.1](https://github.com/bwaidelich/types/releases/tag/1.1.0), this package allows to refer to interface types.

In order to instantiate an object via its interface, the instance class name has to be specified via the `__type` key (with version [1.4+](https://github.com/bwaidelich/types/releases/tag/1.4.0) the name of this key can be configured, see [Discriminator](#Discriminator))
All remaining array items will be used as usual. For simple objects, that only expect a single scalar value, the `__value` key can be specified additionally:

```php
interface SimpleOrComplexObject {
    public function render(): string;
}

#[StringBased]
final class SimpleObject implements SimpleOrComplexObject {
    private function __construct(private readonly string $value) {}
    public function render(): string {
        return $this->value;
    }
}

final class ComplexObject implements SimpleOrComplexObject {
    private function __construct(private readonly string $prefix, private readonly string $suffix) {}
    public function render(): string {
        return $this->prefix . $this->suffix;
    }
}

$simpleObject = instantiate(SimpleOrComplexObject::class, ['__type' => SimpleObject::class, '__value' => 'Some value']);
assert($simpleObject instanceof SimpleObject);

$complexObject = instantiate(SimpleOrComplexObject::class, ['__type' => ComplexObject::class, 'prefix' => 'Prefix', 'suffix' => 'Suffix']);
assert($complexObject instanceof ComplexObject);
```

Especially when working with [generic lists](#list-generic-array), it can be useful to allow for polymorphism, i.e. allow the list to contain any instance of an interface:

<details>
<summary><b>Example: Generic list of interfaces</b></summary>

```php
// ...

#[ListBased(itemClassName: SimpleOrComplexObject::class)]
final class SimpleOrComplexObjects implements IteratorAggregate {
    public function __construct(private readonly array $objects) {}
    
    public function getIterator() : Traversable{
        yield from $this->objects;
    }
    
    public function map(Closure $closure): array
    {
        return array_map($closure, $this->objects);
    }
}

$objects = instantiate(SimpleOrComplexObjects::class, [
    ['__type' => SimpleObject::class, '__value' => 'Simple'],
    ['__type' => ComplexObject::class, 'prefix' => 'Com', 'suffix' => 'plex'],
]);

assert($objects->map(fn (SimpleOrComplexObject $o) => $o->render()) === ['Simple', 'Complex']);
```

</details>

## Union types

Starting with version [1.4](https://github.com/bwaidelich/types/releases/tag/1.4.0), this package allows to refer to union types (aka "oneOf").

Like with [interfaces](#Interfaces), to instantiate object-based union types, the concrete type has to be specified via the `__type` key:

```php
#[StringBased]
final class GivenName {
    private function __construct(public readonly string $value) {}
}
#[StringBased]
final class FamilyName {
    private function __construct(public readonly string $value) {}
}

final class ShapeWithUnionType {
    private function __construct(
        public readonly GivenName|FamilyName $givenOrFamilyName
    ) {}
}

$instance = instantiate(ShapeWithUnionType::class, ['givenOrFamilyName' => ['__type' => FamilyName::class, '__value' => 'Doe']]);
assert($instance instanceof ShapeWithUnionType);
assert($instance->givenOrFamilyName instanceof FamilyName);
```

For simple union types, the type discrimination is not required of course:

```php
final class ShapeWithSimpleUnionType {
    private function __construct(
        public readonly string|int $stringOrInteger
    ) {}
}

$instance = instantiate(ShapeWithSimpleUnionType::class, ['stringOrInteger' => 123]);
assert($instance instanceof ShapeWithSimpleUnionType);
assert(is_int($instance->stringOrInteger));
```

### Discriminator

By default, in order to instantiate an instance of an interface or union type, the target class has to be specified via the `__type` discriminator (see example above).
Starting with version [1.4](https://github.com/bwaidelich/types/releases/tag/1.4.0), the name of this discriminator key can be changed with the `Discriminator` attribute.
Additionally, the mapping from the type value to the fully qualified class name can be specified (optional).

This can be done on the interface level:

```php
#[Discriminator(propertyName: 'type', mapping: ['given' => GivenName::class, 'family' => FamilyName::class])]
interface SomeInterface {}

#[StringBased]
final class GivenName implements SomeInterface {
    private function __construct(public readonly string $value) {}
}
#[StringBased]
final class FamilyName implements SomeInterface {
    private function __construct(public readonly string $value) {}
}

$instance = instantiate(SomeInterface::class, ['type' => 'given', '__value' => 'Jane']);
assert($instance instanceof GivenName);
```

...and on interface or union type parameters:

```php
final class SomeClass {
  public function __construct(
    #[Discriminator(propertyName: 'type', mapping: ['given' => GivenName::class, 'family' => FamilyName::class])]
    public readonly GivenName|FamilyName $givenOrFamilyName,
  ) {}
}

#[StringBased]
final class GivenName {
    private function __construct(public readonly string $value) {}
}
#[StringBased]
final class FamilyName {
    private function __construct(public readonly string $value) {}
}

$instance = instantiate(SomeClass::class, ['givenOrFamilyName' => ['type' => 'family', '__value' => 'Doe']]);
assert($instance instanceof SomeClass);
assert($instance->givenOrFamilyName instanceof FamilyName);
```

> **Note**
> If a `Discriminator` attribute exists on the parameter as well as on the respective interface within a Shape object,
> the parameter attribute overrules the one on the interface

## Error handling

Errors that occur during the instantiation of objects lead to an `InvalidArgumentException` to be thrown.
That exception contains a human-readable error message that can be helpful to debug any errors, for example:

> Failed to instantiate FullNames: At key "0": At property "givenName": Value "a" does not have the required minimum length of 3 characters

Starting with version [1.2](https://github.com/bwaidelich/types/releases/tag/1.2.0), the more specific `CoerceException` is thrown with an improved exception message that collects all failures:

> Failed to cast value of type array to FullNames: At "0.givenName": too_small (String must contain at least 3 character(s)). At "1.familyName": invalid_type (Required)

In addition, the exception contains a property `issues` that allows for programmatic parsing and/or rewriting of the error messages.
The exception itself is JSON-serializable and the above example would be equivalent to:

```json
[
  {
    "code": "too_small",
    "message": "String must contain at least 3 character(s)",
    "path": [0, "givenName"],
    "type": "string",
    "minimum": 3,
    "inclusive": true,
    "exact": false
  },
  {
    "code": "invalid_type",
    "message": "Required",
    "path": [1, "familyName"],
    "expected": "string",
    "received": "undefined"
  }
]
```

> **Note**
> If the syntax is familiar to you, that's no surpise. It is inspired (and in fact almost completely compatible) with the issue format
> of the fantastic [Zod library](https://zod.dev/ERROR_HANDLING)

## Serialization

This package promotes the heavy usage of dedicated value objects for a greater type-safety. When it comes to serializing those objects (e.g. to transmit them to a database or API) this comes at a cost:
The default behavior of PHPs built-in [json_encode](https://www.php.net/manual/en/function.json-encode.php) function will, by default, just include all properties of a class.

For the simple type-based objects this is not feasible as it turns the simple value into an associative array of `['value' => <the-actual-value>]` instead of the desired simple representation of `<the-actual-value>`.

### Example:

```php
#[StringBased]
final class Name {
    private function __construct(public readonly string $value) {}
}
$instance = instantiate(Name::class, 'John Doe');
$serialized = json_encode($instance);
assert($serialized === '{"value":"John Doe"}'); // This should preferably just be serialized to the value itself ("John Doe")
```

Also, [Discriminator](#Discriminator) details will be lost.

Starting with version [1.4](https://github.com/bwaidelich/types/releases/tag/1.4.0), this package provides a dedicated `Normalizer` that can be used to encode types:

```php
// ...
$instance = instantiate(Name::class, 'John Doe');

$normalized = (new Normalizer())->normalize($instance);
assert($normalized === 'John Doe');

$normalizedJson = (new Normalizer())->toJson($instance);
assert($normalizedJson === '"John Doe"');
```

<details>
<summary><b>Example: Complex type with type discrimination</b></summary>

```php
#[StringBased]
final class GivenName {
    private function __construct(public readonly string $value) {}
}
#[StringBased]
final class FamilyName {
    private function __construct(public readonly string $value) {}
}

#[Discriminator(propertyName: 'type', mapping: ['email' => EmailAddress::class, 'phone' => PhoneNumber::class])]
interface ContactInformation {}

#[StringBased(format: StringTypeFormat::email)]
final class EmailAddress implements ContactInformation {
    public function __construct(public readonly string $value) {}
}

enum PhoneNumberType {
    case PERSONAL;
    case WORK;
    case OTHER;
}

final class PhoneNumber implements ContactInformation {
    public function __construct(
        public readonly PhoneNumberType $phoneNumberType,
        public readonly string $number,
    ) {}
}

#[ListBased(itemClassName: ContactInformation::class)]
final class ContactOptions implements IteratorAggregate {
    private function __construct(private readonly array $options) {}
    
    public function getIterator() : Traversable {
        yield from $this->options;
    }
}

final class Contact {
    public function __construct(
        public readonly GivenName $givenName,
        public readonly FamilyName $familyName,
        public readonly ContactOptions $contactOptions,
    ) {}
}

$input = ['givenName' => 'Jane', 'familyName' => 'Doe', 'contactOptions' => [['type' => 'email', '__value' => 'jane.doe@example.com'], ['type' => 'phone', 'phoneNumberType' => 'PERSONAL', 'number' => '1234567']]];
$instance = instantiate(Contact::class, $input);
$normalized = (new Normalizer())->normalize($instance);

assert($normalized === $input);
```

</details>

## Integrations

The declarative approach of this library allows for some interesting integrations.
So far, the following two exist – Feel free to create another one and I will gladly add it to this list:

* [types/graphql](https://github.com/bwaidelich/types-graphql) – to create GraphQL schemas from PHP types
* [types/glossary](https://github.com/bwaidelich/types-glossary) – to create Markdown glossaries for all relevant PHP types
* [types/openapi](https://github.com/bwaidelich/types-openapi) – to declare and serve OpenAPI compatible HTTP APIs

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
