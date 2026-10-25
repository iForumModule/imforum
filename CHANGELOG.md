# Changelog

## 1.0.0 Alpha (unreleased)

- Phase 0: module skeleton for ImpressCMS 2.0 in IPF mode, install and update routines, English and Dutch language files, CI on PHP 8.2 to 8.5.
- Phase 1: data model. 13 IPF objects with handlers (category, forum, moderator, topic, post, attachment, report, suspension, readmark, online, digest, archive, permtemplate), group permissions on categories and forums, counters and cascading deletes through handler events, indexes in `imforum_db_upgrade_1()`, labels in English and Dutch, integration tests against MySQL 5.5, MySQL 5.7 (MyISAM) and MariaDB 10.11.
