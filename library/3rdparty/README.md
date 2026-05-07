# cevian/library/3rdparty

Composer-managed 3rd-party libraries used by Cevian modules.

## Install

```bash
cd cevian/library/3rdparty
composer install
```

This creates `vendor/` with all dependencies. Cevian's autoloader
(`library/ckvsoft/autoload.php`) automatically picks up
`vendor/autoload.php` when present, so modules can use these libs
without their own `require_once`.

## What's in here

| Library                                        | Used by      | Purpose                          |
| ---------------------------------------------- | ------------ | -------------------------------- |
| `dompdf/dompdf`                                | registration | PDF invoice generation           |
| `hakito/php-stuzza-eps-banktransfer`           | registration | EPS Austrian bank transfer       |

`rmccue/requests` is pulled in transitively by Hakito's package.

## Why here and not in each module

Multiple modules may need the same library (dompdf is general-purpose
for any module that produces PDFs). Centralising avoids duplicate
copies, simplifies updates, and lets the framework's autoloader serve
them all.

## Updating

```bash
composer update            # pick up newer minor/patch versions
composer require foo/bar   # add a new library
```

After update, test the affected modules — major version bumps may
break module code.

## Why is `vendor/` gitignored

Composer-managed code shouldn't be in the source tree; deployment
runs `composer install` instead. `composer.json` is committed so
versions stay reproducible.
