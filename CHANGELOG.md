# Changelog — waffle-commons/serializer

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [0.1.0-beta7] — 2026-11

**Theme: first release — compiled DTO normalizers and content negotiation (API-02).**

### Added
- Initial scaffold from `waffle-commons/component-template`: CI pipeline (Mago, Igor, PHPUnit,
  TruffleHog, SBOM, Psalm taint analysis), Dependabot, Mago zero-baseline configuration.
- **DTO normalizers (API-02, RFC-016).** `Serializer` turns `#[Dto]` objects into JSON and JSON request bodies
  into `#[Dto]` objects, always through the constructor so `set` hooks validate every value. It honours property
  hooks and asymmetric visibility, and handles backed enums, nested DTOs and `#[ListOf]` lists. It reads DTOs
  through `waffle-commons/utils`' `DtoShapeReader`, the reader the OpenAPI generator shares.
- `Compiler\NormalizerCompiler` — generates one reflection-free normalizer class per DTO plus an index
  (`NormalizerProviderInterface`), deterministic and Mago-clean. `Normalizer\ShapeNormalizerProvider` interprets
  the shapes by reflection for development; both run every check through `Normalizer\Payload` and agree byte
  for byte.
- `Json\JsonCodec` — bounded decoding (1 MiB and depth 64 by default) and UTF-8 encoding that keeps float fractions.
- Exceptions mapped by the error handler: `InvalidPayloadException` (422, with the field path such as
  `lines[2].quantity`), `MalformedPayloadException` (400), `PayloadTooLargeException` (413),
  `NormalizerException` (500), `NotAcceptableException` (406).
- `Command\CompileCommand` — `serializer:compile [--source=…] [--output=…] [--namespace=…]` scans for `#[Dto]`
  classes and writes their compiled normalizers atomically; it removes only the files a previous run generated.
- `Normalizer\CompiledNormalizerLoader` — the `WAFFLE_AOT=1` fast path: the compiled index, backed by the
  interpreted provider (`Normalizer\FallbackNormalizerProvider`) for any DTO the build did not cover; the
  interpreted provider, with a logged reason, whenever the flag is off or the build is missing or broken.
- `Middleware\ContentNegotiationMiddleware` — `Accept` negotiation for a JSON API (RFC 9110 precedence and
  weights): a request that rules JSON out gets a 406, every other response varies on `Accept`.
- `Serializer` implements the contracts' `DtoSerializerInterface`, through which the framework's argument
  resolver and response converter, and a queue driver, use it; every exception implements
  `SerializerExceptionInterface`. `serialize()` reports a value JSON cannot encode as a `NormalizerException`
  instead of a raw `JsonException`.

### Dependencies
- Requires `waffle-commons/utils` (the DTO-shape reader); the `mago guard` perimeter permits it.
- `psr/log`, `psr/http-message`, `psr/http-server-middleware` and `psr/http-server-handler` are direct
  requirements; `nyholm/psr7` is a development dependency for the component's own tests.
