# imforum

Community forum module for ImpressCMS 2.0, built on the ImpressCMS Persistable Framework (IPF). imforum is the successor of iForum: same features, new code, and an importer that takes over the data of a running iForum installation.

Status: phase 0 (foundation). The module installs and updates, but has no forum functions yet.

## Requirements

- ImpressCMS 2.0
- PHP 8.2 to 8.5
- MySQL 5.5.3 or newer (utf8mb4); any storage engine, imforum does not rely on transactions or foreign keys

## Installation

Copy `src/` to `modules/imforum` in your ImpressCMS installation and install the module from System → Modules. The directory must be called `imforum`: IPF derives the table and class names from it, so the module cannot be cloned under another name.

## Development

```sh
composer install
composer lint      # php -l on every file
composer test      # PHPUnit
ICMS_CORE_PATH=/path/to/impresscms/htdocs composer analyse   # PHPStan, needs a core checkout
```

Conventions:

- Classes are named `mod_imforum_<Item>` and `mod_imforum_<Item>Handler` and live in `src/class/<Item>.php` and `src/class/<Item>Handler.php`; the core autoloads them once `object_items` is set.
- Form labels come from `_CO_IMFORUM_<ITEM>_<FIELD>` in `language/*/common.php`.
- English and Dutch ship with the module; other languages go through Crowdin.
- Never rename or remove an object var in one release. The core drops columns that are no longer declared before the upgrade functions run, so add the new var and copy the data first, and remove the old var one release later.

## License

MIT, see [LICENSE](LICENSE).
