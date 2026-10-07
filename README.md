[![Discord](https://img.shields.io/discord/755288001592033391?logo=discord)](https://discord.gg/eKgywnfXr2)
[![PHP Version Require](http://poser.pugx.org/waffle-commons/serializer/require/php)](https://packagist.org/packages/waffle-commons/serializer)
[![PHP CI](https://github.com/waffle-commons/serializer/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/serializer/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/serializer/graph/badge.svg)](https://codecov.io/gh/waffle-commons/serializer)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/serializer/v)](https://packagist.org/packages/waffle-commons/serializer)
[![Latest Unstable Version](http://poser.pugx.org/waffle-commons/serializer/v/unstable)](https://packagist.org/packages/waffle-commons/serializer)
[![Total Downloads](https://img.shields.io/packagist/dt/waffle-commons/serializer.svg)](https://packagist.org/packages/waffle-commons/serializer)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/serializer)](https://github.com/waffle-commons/serializer/blob/main/LICENSE.md)

Waffle Serializer Component
==========================

> **Release:** _unreleased_ &nbsp;|&nbsp; [`CHANGELOG.md`](./CHANGELOG.md)
> **Status:** In development for `0.1.0-beta7` — roadmap `[API-02]` (RFC-016). The DTO normalizers are in, compiled per DTO at build time or interpreted in development. Still to come: the `serializer:compile` command, the `Accept` negotiation middleware, the DTO response converter and the kernel wiring. `data/`'s Hydrator stays DB-only.

Compiled, reflection-free DTO serialization and Accept-header content negotiation for the Waffle framework.

## 🧩 What it does

It turns `#[Dto]` objects into JSON and JSON request bodies into `#[Dto]` objects, at the HTTP boundary. It is
**not** a general-purpose serializer: it handles exactly the DTO shapes that `waffle-commons/utils`'
`DtoShapeReader` reads, the same shapes the OpenAPI generator describes.

```php
use Waffle\Commons\Serializer\Serializer;

$serializer = new Serializer($normalizers);           // a NormalizerProviderInterface, see below

$order = $serializer->deserialize($body, OrderInput::class); // JSON → DTO, through the constructor
$json = $serializer->serialize($receipt);                     // DTO → JSON
```

- **Hydration always goes through the constructor**, with named arguments, so `set` hooks validate every value.
  Nothing writes a property directly.
- **Out:** the readable properties (`get` hooks run, write-only virtual properties are skipped). **In:** the
  constructor parameters. A `password` parameter can come in while a `passwordHash` property goes out.
- **Types:** `int`, `float` (an integer is accepted), `string`, `bool`, backed enums (as their backing value),
  nested `#[Dto]`s and lists declared with `#[ListOf]`, each optionally nullable.
- **Keys** are the property and parameter names, unchanged. An unknown key is ignored. An absent key takes the
  parameter's default, `null` for a nullable parameter without one, and is an error otherwise. `null` values are
  written out; a DTO without readable properties is `{}`; floats keep their fraction (`2.0`).

## ⚙️ Compiled and interpreted normalizers

| Provider | When | How |
| :--- | :--- | :--- |
| The generated index (`NormalizerCompiler`) | production | One generated `final readonly` class per DTO: plain property reads out, a plain constructor call in. No reflection at request time. |
| `Normalizer\ShapeNormalizerProvider` | development | Reads the DTO shape on demand and normalizes by reflection. No build step. |

Both run every check through `Normalizer\Payload`, so they accept, reject and word errors identically. The test
suite holds them to it on every case. `NormalizerCompiler::compile()` returns the generated files (file name →
source), one class per file, deterministic, and written to pass Mago's linter and analyzer like hand-written code.

## 🚦 Errors

| Exception | Status | When |
| :--- | :--- | :--- |
| `Exception\InvalidPayloadException` | 422 | A field is missing, mistyped, not one of an enum's values, or rejected by a `set` hook. `getField()` is its path from the root: `email`, `shipping.city`, `lines[2].quantity`. |
| `Exception\MalformedPayloadException` | 400 | The body is not JSON, or nests deeper than the codec's limit (64 by default). |
| `Exception\PayloadTooLargeException` | 413 | The body is longer than the codec's limit (1 MiB by default); it is not parsed. |
| `Exception\NormalizerException` | 500 | A class has no normalizer, or is a `#[Dto]` the serializer cannot represent: a wiring mistake. |

The error handler renders them; a 422 shows the message and the field, as for any validation failure.

## 📦 Installation

```bash
composer require waffle-commons/serializer
```

## 🧭 Architectural boundary (`mago guard`)

`Waffle\Commons\Serializer` depends only on `waffle-commons/contracts`, `waffle-commons/utils` (the
DTO-shape reader it shares with the OpenAPI generator) and the PSR interfaces — never on a sibling component's
concrete classes. An interface another component consumes lands in `waffle-commons/contracts` first;
`DtoNormalizerInterface` and `NormalizerProviderInterface` are this component's own seam between its runtime and
the code it generates.

## 🧵 Worker safety

Services are resident in FrankenPHP worker mode: no mutable state may survive a request. Anything that
holds per-request state implements `ResettableInterface` directly; `composer igor` must report 0 KO.

## 🧪 Testing

```bash
composer mago    # formatter, linter, analyzer, guard — clean means zero output
composer tests   # PHPUnit 12.5, line coverage ≥ 95 %
composer igor    # worker-safety audit
```

`tests/compiled/` holds the compiler's output for the fixture matrix (golden files). The test suite compares it
byte for byte, and Mago lints and analyzes it with no test-only exception. After a deliberate change to the
compiler, regenerate it with `WAFFLE_UPDATE_GOLDEN=1 composer tests` and review the diff.

## 📚 Documentation

Full documentation tree: [waffle-commons/documentation](https://github.com/waffle-commons/documentation)

## 📄 License

MIT — see [`LICENSE.md`](./LICENSE.md).
