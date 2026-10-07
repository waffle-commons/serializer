# Changelog — waffle-commons/serializer

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [Unreleased]

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
  `NormalizerException` (500).

### Dependencies
- Requires `waffle-commons/utils` (the DTO-shape reader); the `mago guard` perimeter permits it.
