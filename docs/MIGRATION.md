# Moving from iForum to imforum

imforum runs next to iForum while you import, so the old forum keeps working until you are satisfied. iForum is never changed by the import.

## Before you start

- ImpressCMS 2.0 on PHP 8.2 or newer and MySQL 5.5.3 or newer, with the PDO driver (`XOOPS_DB_TYPE` is `pdo.mysql`).
- A backup of the database and of `uploads/iforum`.
- The tables of imforum are created as utf8mb4. On MySQL 5.5 the check refuses tables in another character set and says how to convert them.

## Steps

1. Copy the folder `modules/imforum` of the release to your site and install it in System > Modules. Leave iForum installed.
2. Open imforum > Import and press **Check**. It writes nothing. Fix what it reports (missing attachment files are warnings, missing tables are errors).
3. Press **Import**. Every run first empties the imforum tables, so an interrupted run is simply repeated. All ids stay the same.
4. Press **Verify**. It compares row counts, posts per forum and per topic, a checksum of 200 random posts, the rights and the files.
5. Walk through the forum as a member and as a guest. Posts, forums and attachments have the same ids as before.
6. Set up the old addresses (next section), then uninstall iForum.

## Old addresses

Links to `modules/iforum/viewtopic.php?topic_id=12` keep working through a stub module:

1. Uninstall iForum in System > Modules and delete the folder `modules/iforum`.
2. Copy the folder `modules/iforum` of the release in its place. It holds six small scripts and no data. Do **not** install it in System > Modules: the scripts redirect without the module being installed.
3. Open an old address. It answers with a permanent redirect (301) to the same page of imforum.

| Old | New |
| --- | --- |
| `index.php` | `index.php` |
| `viewforum.php?forum=7&start=20` | `forum.php?forum=7&start=20` |
| `viewtopic.php?topic_id=12` | `topic.php?topic=12` |
| `viewtopic.php?topic_id=12&post_id=99` | `topic.php?topic=12&post=99` |
| `viewtopic.php?post_id=99` | the topic of that post, jumping to it |
| `viewtopic.php?topic_id=12&viewmode=thread` | `topic.php?topic=12&mode=tree` |
| `viewall.php?type=unread` or `digest` | `viewall.php?type=unread` or `digest`; other types show the latest topics |
| `viewpost.php` | `viewall.php` |
| `rss.php?f=5` | `rss.php?forum=5` (several forums, or none: all forums) |

Sort and filter options of the old lists (`sortname`, `sortorder`, `since`) are dropped. Without the stub folder the old addresses give a 404; a web server rule can do the same job:

```apache
RewriteEngine On
RewriteCond %{QUERY_STRING} (?:^|&)topic_id=([0-9]+)
RewriteRule ^modules/iforum/viewtopic\.php$ /modules/imforum/topic.php?topic=%1 [R=301,L]
RewriteCond %{QUERY_STRING} (?:^|&)forum=([0-9]+)
RewriteRule ^modules/iforum/viewforum\.php$ /modules/imforum/forum.php?forum=%1 [R=301,L]
```

Links inside imported posts are not rewritten; they use the old addresses and so go through the redirects.

## Rolling back

Until you uninstall iForum nothing is lost: **Roll back** on the import page empties the imforum tables and removes the copied files, rights and subscriptions. After uninstalling iForum, restore the database backup to return.

## Translations

English and Dutch ship with the module. Other languages come from Crowdin (`crowdin.yml` maps the codes to the ImpressCMS folder names); download them into `language/<name>/`. A missing file falls back to English.
