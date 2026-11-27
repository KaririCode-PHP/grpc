# QD-V433: kariricode/grpc 4.0.0 — compliance with Spec V4.3.3

**Status:** Measured · **Date:** 2026-11-27 · PHP 8.4.28, PHPUnit 11.5.56, PHPStan 2.3.7, Psalm 6.19.1, PHP-CS-Fixer 3.95.27, Infection 0.35.5 (all pinned in `devkit.php`, ADR-004)

Reproduce with `composer check` and `composer security`; each number below
comes from that run. Runtime: kariricode/contract 2.12.1, kariricode/exception
2.0.2, kariricode/http 4.0.0, kariricode/router 4.1.0, psr/clock 1.0.0,
psr/http-factory 1.1.0, psr/http-message 2.0, psr/http-server-handler 1.0.2,
psr/http-server-middleware 1.0.2. The suites also pass with
`composer update --prefer-lowest` (kariricode/contract 2.11.0).

## 1. Gates (§29.1)

| Gate | Command | Result |
|---|---|---|
| Style | `composer cs-check` (PER-CS 2.0) | pass: 0 files to fix |
| Static analysis | `composer phpstan` (level max; src, tests, tools) | pass: 0 errors |
| Static analysis | `composer psalm` (errorLevel 1) | pass: 0 errors |
| Complexity | `composer complexity` | pass: 135 methods within budget, 0 accepted breaches; cognitive complexity 0 errors |
| Unit | `composer test-unit` | 180 tests, 609 assertions, all pass |
| Conformance | `composer test-conformance` | 20 tests, 566 assertions, all pass |
| Coverage | PHPUnit with pcov | lines 100% (288 of 288), methods 100% |
| Mutation | `composer mutation` (MSI and covered MSI ≥ 80) | pass: MSI 99.60%, covered MSI 99.60% (494 mutants: 492 killed, 2 escaped) |
| Security | `composer security` | `composer audit --locked`: 0 advisories (runtime and toolchain); secret scan: clean |
| SBOM | `composer sbom` | CycloneDX 1.5, 9 runtime components |

The 2 escaped mutants are equivalent:

- `ForwardedClientCertificate::certificate()` returns `true` instead of
  `false` on the branch for a missing ext-openssl, which the suite cannot
  unload; either value is "no parsed certificate".
- `Timeout` casts the whole value instead of its digits; PHP's integer cast
  stops at the unit letter, so both give the same number.

## 2. Rules (§29.2), checked by `tests/Conformance`

| Rule | Result |
|---|---|
| One type per file, strict types, namespace | 30 of 30 types |
| Classes final | 24 of 24 classes |
| Exception families (§8.4) | 5 of 5 classes, one family each, on kariricode/exception bases; named constructors only |
| Codes (registry 1900–1999, prefix GRPC) | 21 codes, unique, in the documented ranges, one factory each; the row in CODE-BLOCKS.md of kariricode/exception |
| No echo of input (E-047) | fixed messages; configuration names through `quote()`; fixed statuses for unknown services, methods and refused certificates |
| Zero-argument getters, setters, magic methods (§18) | 0 |
| Static or writable public state (§18) | 0 |
| Untyped constants | 0 |
| Error suppression, eval, debug output | 0 (`Integration\Silently` scopes an error handler instead of `@`) |
| Docblocks C1–C5 (§17) | 30 of 30 types |
| ADRs with the canonical sections | 6 of 6 |
| Imports (§31) | kariricode/router and kariricode/http only in `src/Integration`; `Psr\Clock` for time |

## 3. Accepted deviations

| # | Item | Reason |
|---|---|---|
| 1 | `object` parameters of `GrpcNormalizer::canNormalize()` and `normalize()` | the signatures of kariricode/router's `RequestNormalizer` (its ADR-002) |
| 2 | Soft budget of ten public methods exceeded by `GrpcRequest` (12), `Status` (14) and `InvalidMessageException` (11) | the withers of an immutable request; one factory per common status; one named constructor per code |

## 4. Open items

1. The release tag is created by the GitHub release form and is not signed
   (§32); signing needs the maintainer's key.
2. Streaming calls are described (call types, descriptors, routing) but not
   served; a streaming dispatcher needs the transport's stream (ADR-001).
3. Decompressing gzip and deflate messages is left to the transport
   (ADR-006).
4. `Status::$details` is not encoded as grpc-status-details-bin, which
   needs protobuf (ADR-002).
