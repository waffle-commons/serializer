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
> **Status:** In development for `0.1.0-beta7` — roadmap `[API-02]` (RFC-016). Normalizers are compiled per DTO at build time; `data/`'s Hydrator stays DB-only.

Compiled, reflection-free DTO serialization and Accept-header content negotiation for the Waffle framework.

## 📦 Installation

```bash
composer require waffle-commons/serializer
```

## 🧭 Architectural boundary (`mago guard`)

`Waffle\Commons\Serializer` depends only on `waffle-commons/contracts` (plus `waffle-commons/utils`
for shared primitives) and the PSR interfaces — never on a sibling component's concrete classes. Every
interface it implements lands in `waffle-commons/contracts` first.

## 🧵 Worker safety

Services are resident in FrankenPHP worker mode: no mutable state may survive a request. Anything that
holds per-request state implements `ResettableInterface` directly; `composer igor` must report 0 KO.

## 🧪 Testing

```bash
composer mago    # formatter, linter, analyzer, guard — clean means zero output
composer tests   # PHPUnit 12.5, line coverage ≥ 95 %
composer igor    # worker-safety audit
```

## 📚 Documentation

Full documentation tree: [waffle-commons/documentation](https://github.com/waffle-commons/documentation)

## 📄 License

MIT — see [`LICENSE.md`](./LICENSE.md).
