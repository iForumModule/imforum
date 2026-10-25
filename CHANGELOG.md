# Changelog

## 1.0.0 Alpha (unreleased)

- Phase 0: module skeleton for ImpressCMS 2.0 in IPF mode, install and update routines, English and Dutch language files, CI on PHP 8.2 to 8.5.
- Phase 1: data model. 13 IPF objects with handlers (category, forum, moderator, topic, post, attachment, report, suspension, readmark, online, digest, archive, permtemplate), group permissions on categories and forums, counters and cascading deletes through handler events, indexes in `imforum_db_upgrade_1()`, labels in English and Dutch, integration tests against MySQL 5.5, MySQL 5.7 (MyISAM) and MariaDB 10.11.
- Phase 2: administration. Admin screens on `icms_ipf_view_Table` and `getSecureForm()` for categories and forums (moderators and group permissions on the forum form), a permission template that new forums start with and that can be applied to existing forums, reports, suspensions, digests, the archive, prune (with a preview and a confirmation) and a full recount. Every operation that writes runs only on POST with a valid security token, deletion confirmations included. Works around three core issues: a missing admin menu on the first request after the menu cache is rebuilt, `count(null)` when a permission select is left empty, and the date picker failing when a form is built before `icms_cp_header()`.
