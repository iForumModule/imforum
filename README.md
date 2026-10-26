# imforum

Community forum module for ImpressCMS 2.0, built on the ImpressCMS Persistable Framework (IPF). imforum is the successor of iForum: same features, new code, and an importer that takes over the data of a running iForum installation.

Status: phase 6 (background tasks), module version 1.0.0 Alpha 6. Members start topics, reply (with a quick reply under the topic), quote, edit and delete their own posts, attach files and follow topics and forums by notification; guests can post where the group permissions allow it, behind a captcha. Posts wait for a moderator when the group lacks forum_noapprove. Moderators of a forum lock, pin and feature topics, move, merge and split them, delete topics, approve waiting posts, handle reports from members and suspend members, all from the front end through `moderate.php`; members report posts through `report.php`. Two autotasks (Systeem > Autotasks, daily by default) send a digest of new topics and posts to members who subscribe to it and clean up old read marks, the visitors list and ended suspensions. Categories, forums, moderators, permissions, reports, suspensions, digests, the archive and pruning are managed from the admin area.

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
ICMS_CORE_PATH=/path/to/impresscms/htdocs composer test:integration   # needs a database, see below
```

The integration tests install the module into an empty database with the core's own updater and then exercise the handlers. They need a core checkout and an empty MySQL or MariaDB database, set with `IMFORUM_TEST_DB_HOST`, `IMFORUM_TEST_DB_NAME`, `IMFORUM_TEST_DB_USER` and `IMFORUM_TEST_DB_PASS` (defaults: `127.0.0.1`, `icms_test`, `icms`, `icms`). Set `IMFORUM_TEST_DB_ENGINE=MyISAM` to test as on a server without InnoDB. Every run drops the tables with prefix `imft_` first, so never point it at a real site.

PHPStan reads the core classes from `ICMS_CORE_PATH`. Without that variable it stops with an error about a missing scan directory.

For a local test site, copy `src/` to `modules/imforum`. A symlink or Windows junction does not work: PHP resolves `__DIR__` to the real folder, and the module takes its name from that folder.

Conventions:

- Classes are named `mod_imforum_<Item>` and `mod_imforum_<Item>Handler` and live in `src/class/<Item>.php` and `src/class/<Item>Handler.php`; the core autoloads them once `object_items` is set.
- Form labels come from `_CO_IMFORUM_<ITEM>_<FIELD>` in `language/*/common.php`.
- English and Dutch ship with the module; other languages go through Crowdin.
- Object item names are single lower-case words that are not SQL reserved words: IPF uses them as table aliases (that is why read marks are called `readmark`).
- Counters such as `forum_posts` and `topic_replies` are recounted by `synchronise()` on the handlers after every change, never incremented by hand.
- Never rename or remove an object var in one release. The core drops columns that are no longer declared before the upgrade functions run, so add the new var and copy the data first, and remove the old var one release later.

## License

MIT, see [LICENSE](LICENSE).
