# Dynamic and extensible schemas via a Target seam

To support creating schemas that are not backed by a fixed PHP class (and extending existing
class-based ones), each schema delegates its three class-coupled concerns — naming, instance
checking, and the final "build the value" step — to a `Target`. `ClassTarget` reproduces the
original reflection-based behavior; `DynamicTarget` builds a generic container
(`DynamicValue`/`DynamicRecord`/`DynamicList`) instead. One set of schema classes serves both
modes: there is **no** separate `DynamicShapeSchema`, so to consumers of a `Schema` a class-based
and a dynamic schema are the same type with the same interface.

## Considered Options

- **Full class-agnostic rewrite** (pure-data schemas + registry + references + separate
  parse/instantiate services) — prototyped on the parked `feature/v2-overhaul` branch and rejected:
  it was a major, breaking change whose "purity / two-paths / stateful-services" benefits were means
  we had assumed necessary, not actual goals. The dynamic-schema capability did not require it.
- **Parallel `DynamicShapeSchema` twins** — rejected: introduces a second `Schema` implementation
  per kind and duplicates coercion logic; consumers could (and would) end up distinguishing them.

## Consequences

- Non-breaking: the existing reflection path is unchanged (all prior tests pass untouched); this can
  ship as a minor version.
- The seam fits **constructor-based value types** — `StringSchema`, `IntegerSchema`, `FloatSchema`,
  `ListSchema`, `ShapeSchema`. These are dynamically creatable via `DynamicSchema` and extensible via
  `DynamicSchema::extend()`.
- `OneOfSchema` holds no class and needs no Target. `EnumSchema` (value→case *resolution*) and
  `InterfaceSchema` (discriminated *dispatch*) are deliberately left class-bound: their instantiation
  is not construction, so forcing them through `Target::construct()` would distort the abstraction.
  Making those dynamic is a separate, larger feature (dynamic interfaces in particular would
  reintroduce a registry-like schema lookup).
- The dynamic-ness surfaces only in the instantiated *value* (a container vs. a real object), never
  in the schema's type or public interface.
