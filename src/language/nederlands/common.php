<?php
/**
 * Formulierlabels, rechtnamen en meldingen voor de IPF-objecten, Nederlands
 *
 * IPF gebruikt _CO_IMFORUM_<ITEM>_<VELD> en _CO_IMFORUM_<ITEM>_<VELD>_DSC
 * automatisch als label en uitleg (icms_ipf_Object::initVar).
 * Labels voor gewicht, SEO en de do*-weergaveopties komen uit de core.
 */

// category
define('_CO_IMFORUM_CATEGORY_CAT_ID', 'ID');
define('_CO_IMFORUM_CATEGORY_CAT_TITLE', 'Titel');
define('_CO_IMFORUM_CATEGORY_CAT_DESCRIPTION', 'Beschrijving');
define('_CO_IMFORUM_CATEGORY_CAT_IMAGE', 'Afbeelding');
define('_CO_IMFORUM_CATEGORY_CAT_URL', 'Link');
define('_CO_IMFORUM_CATEGORY_CAT_URL_DSC', 'Optionele website die bij de categorie getoond wordt');

// forum
define('_CO_IMFORUM_FORUM_FORUM_ID', 'ID');
define('_CO_IMFORUM_FORUM_CAT_ID', 'Categorie');
define('_CO_IMFORUM_FORUM_PARENT_FORUM', 'Bovenliggend forum');
define('_CO_IMFORUM_FORUM_FORUM_NAME', 'Naam');
define('_CO_IMFORUM_FORUM_FORUM_DESC', 'Beschrijving');
define('_CO_IMFORUM_FORUM_FORUM_ACTIVE', 'Actief');
define('_CO_IMFORUM_FORUM_FORUM_ACTIVE_DSC', 'Inactieve forums zijn voor iedereen behalve beheerders verborgen');
define('_CO_IMFORUM_FORUM_ALLOW_HTML', 'HTML toestaan');
define('_CO_IMFORUM_FORUM_ALLOW_SIG', 'Handtekeningen toestaan');
define('_CO_IMFORUM_FORUM_ALLOW_SUBJECT_PREFIX', 'Onderwerp-prefixen toestaan');
define('_CO_IMFORUM_FORUM_HOT_THRESHOLD', 'Drempel populair onderwerp');
define('_CO_IMFORUM_FORUM_HOT_THRESHOLD_DSC', 'Aantal reacties vanaf wanneer een onderwerp als populair getoond wordt');
define('_CO_IMFORUM_FORUM_ATTACH_MAXKB', 'Maximale grootte bijlage (KB)');
define('_CO_IMFORUM_FORUM_ATTACH_EXT', 'Toegestane extensies voor bijlagen');
define('_CO_IMFORUM_FORUM_ATTACH_EXT_DSC', 'Scheid extensies met |, bijvoorbeeld zip|jpg|png');
define('_CO_IMFORUM_FORUM_FORUM_TOPICS', 'Onderwerpen');
define('_CO_IMFORUM_FORUM_FORUM_POSTS', 'Berichten');
define('_CO_IMFORUM_FORUM_FORUM_LAST_POST_ID', 'Laatste bericht');
define('_CO_IMFORUM_FORUM_MODERATORS', 'Moderatoren');
define('_CO_IMFORUM_FORUM_MODERATORS_DSC', 'Leden die dit forum modereren');

// moderator
define('_CO_IMFORUM_MODERATOR_MODERATOR_ID', 'ID');
define('_CO_IMFORUM_MODERATOR_FORUM_ID', 'Forum');
define('_CO_IMFORUM_MODERATOR_UID', 'Moderator');

// topic
define('_CO_IMFORUM_TOPIC_TOPIC_ID', 'ID');
define('_CO_IMFORUM_TOPIC_FORUM_ID', 'Forum');
define('_CO_IMFORUM_TOPIC_TOPIC_TITLE', 'Titel');
define('_CO_IMFORUM_TOPIC_TOPIC_SUBJECT', 'Onderwerp-prefix');
define('_CO_IMFORUM_TOPIC_TOPIC_POSTER', 'Gestart door');
define('_CO_IMFORUM_TOPIC_POSTER_NAME', 'Naam gast');
define('_CO_IMFORUM_TOPIC_TOPIC_TIME', 'Gestart op');
define('_CO_IMFORUM_TOPIC_TOPIC_VIEWS', 'Weergaven');
define('_CO_IMFORUM_TOPIC_TOPIC_STATUS', 'Gesloten');
define('_CO_IMFORUM_TOPIC_TOPIC_STICKY', 'Vastgezet');
define('_CO_IMFORUM_TOPIC_TOPIC_DIGEST', 'Uitgelicht');
define('_CO_IMFORUM_TOPIC_DIGEST_TIME', 'Uitgelicht op');
define('_CO_IMFORUM_TOPIC_APPROVED', 'Goedgekeurd');
define('_CO_IMFORUM_TOPIC_TOPIC_REPLIES', 'Reacties');
define('_CO_IMFORUM_TOPIC_TOPIC_FIRST_POST_ID', 'Eerste bericht');
define('_CO_IMFORUM_TOPIC_TOPIC_LAST_POST_ID', 'Laatste bericht');

// post
define('_CO_IMFORUM_POST_POST_ID', 'ID');
define('_CO_IMFORUM_POST_TOPIC_ID', 'Onderwerp');
define('_CO_IMFORUM_POST_FORUM_ID', 'Forum');
define('_CO_IMFORUM_POST_PID', 'Reactie op');
define('_CO_IMFORUM_POST_UID', 'Auteur');
define('_CO_IMFORUM_POST_POSTER_NAME', 'Naam gast');
define('_CO_IMFORUM_POST_POSTER_IP', 'IP-adres');
define('_CO_IMFORUM_POST_POST_TIME', 'Geplaatst op');
define('_CO_IMFORUM_POST_SUBJECT', 'Onderwerp');
define('_CO_IMFORUM_POST_POST_TEXT', 'Bericht');
define('_CO_IMFORUM_POST_POST_EDIT', 'Bewerkingen');
define('_CO_IMFORUM_POST_ICON', 'Icoon');
define('_CO_IMFORUM_POST_ATTACHSIG', 'Handtekening toevoegen');
define('_CO_IMFORUM_POST_APPROVED', 'Goedgekeurd');
define('_CO_IMFORUM_POST_POST_KARMA', 'Vereiste karma');
define('_CO_IMFORUM_POST_POST_KARMA_DSC', 'Alleen leden met minstens deze karma kunnen het bericht lezen');
define('_CO_IMFORUM_POST_REQUIRE_REPLY', 'Reageren verplicht om te lezen');
define('_CO_IMFORUM_POST_REQUIRE_REPLY_DSC', 'Leden zien het bericht pas nadat ze op het onderwerp gereageerd hebben');

// attachment
define('_CO_IMFORUM_ATTACHMENT_ATTACH_ID', 'ID');
define('_CO_IMFORUM_ATTACHMENT_POST_ID', 'Bericht');
define('_CO_IMFORUM_ATTACHMENT_NAME_SAVED', 'Opgeslagen bestandsnaam');
define('_CO_IMFORUM_ATTACHMENT_NAME_DISPLAY', 'Bestandsnaam');
define('_CO_IMFORUM_ATTACHMENT_MIMETYPE', 'MIME-type');
define('_CO_IMFORUM_ATTACHMENT_FILESIZE', 'Grootte (bytes)');
define('_CO_IMFORUM_ATTACHMENT_DOWNLOADS', 'Downloads');
define('_CO_IMFORUM_ATTACHMENT_ONLINE', 'Zichtbaar');
define('_CO_IMFORUM_ATTACHMENT_ATTACH_TIME', 'Geüpload op');

// report
define('_CO_IMFORUM_REPORT_REPORT_ID', 'ID');
define('_CO_IMFORUM_REPORT_POST_ID', 'Bericht');
define('_CO_IMFORUM_REPORT_REPORTER_UID', 'Gemeld door');
define('_CO_IMFORUM_REPORT_REPORTER_IP', 'IP-adres');
define('_CO_IMFORUM_REPORT_REPORT_TIME', 'Gemeld op');
define('_CO_IMFORUM_REPORT_REPORT_TEXT', 'Reden');
define('_CO_IMFORUM_REPORT_REPORT_RESULT', 'Afgehandeld');
define('_CO_IMFORUM_REPORT_REPORT_MEMO', 'Notitie moderator');

// suspension
define('_CO_IMFORUM_SUSPENSION_SUSPENSION_ID', 'ID');
define('_CO_IMFORUM_SUSPENSION_UID', 'Lid');
define('_CO_IMFORUM_SUSPENSION_IP', 'IP-adres');
define('_CO_IMFORUM_SUSPENSION_FORUM_ID', 'Forum');
define('_CO_IMFORUM_SUSPENSION_FORUM_ID_DSC', 'Geen forum betekent alle forums');
define('_CO_IMFORUM_SUSPENSION_START_TIME', 'Vanaf');
define('_CO_IMFORUM_SUSPENSION_END_TIME', 'Tot');
define('_CO_IMFORUM_SUSPENSION_DESCRIPTION', 'Reden');

// readmark
define('_CO_IMFORUM_READMARK_READ_ID', 'ID');
define('_CO_IMFORUM_READMARK_READ_TYPE', 'Type');
define('_CO_IMFORUM_READMARK_UID', 'Lid');
define('_CO_IMFORUM_READMARK_ITEM_ID', 'Forum of onderwerp');
define('_CO_IMFORUM_READMARK_POST_ID', 'Laatst gelezen bericht');
define('_CO_IMFORUM_READMARK_READ_TIME', 'Gelezen op');

// online
define('_CO_IMFORUM_ONLINE_ONLINE_ID', 'ID');
define('_CO_IMFORUM_ONLINE_FORUM_ID', 'Forum');
define('_CO_IMFORUM_ONLINE_TOPIC_ID', 'Onderwerp');
define('_CO_IMFORUM_ONLINE_UID', 'Lid');
define('_CO_IMFORUM_ONLINE_UNAME', 'Naam');
define('_CO_IMFORUM_ONLINE_IP', 'IP-adres');
define('_CO_IMFORUM_ONLINE_UPDATED', 'Laatst gezien');

// digest
define('_CO_IMFORUM_DIGEST_DIGEST_ID', 'ID');
define('_CO_IMFORUM_DIGEST_DIGEST_TIME', 'Verstuurd op');
define('_CO_IMFORUM_DIGEST_DIGEST_CONTENT', 'Inhoud');

// archive
define('_CO_IMFORUM_ARCHIVE_ARCHIVE_ID', 'ID');
define('_CO_IMFORUM_ARCHIVE_TOPIC_ID', 'Onderwerp');
define('_CO_IMFORUM_ARCHIVE_POST_ID', 'Bericht');
define('_CO_IMFORUM_ARCHIVE_POST_TEXT', 'Bericht');
define('_CO_IMFORUM_ARCHIVE_ARCHIVED_TIME', 'Gearchiveerd op');

// permtemplate
define('_CO_IMFORUM_PERMTEMPLATE_TEMPLATE_ID', 'ID');
define('_CO_IMFORUM_PERMTEMPLATE_PERM_NAME', 'Recht');
define('_CO_IMFORUM_PERMTEMPLATE_GROUPID', 'Groep');

// permissions
define('_CO_IMFORUM_PERM_CATEGORY_ACCESS', 'Kan de categorie zien');
define('_CO_IMFORUM_PERM_CATEGORY_ACCESS_DSC', 'Groepen die deze categorie en haar forums op de index zien');
define('_CO_IMFORUM_PERM_FORUM_ACCESS', 'Kan het forum zien');
define('_CO_IMFORUM_PERM_FORUM_ACCESS_DSC', 'Groepen die dit forum in de lijst zien');
define('_CO_IMFORUM_PERM_FORUM_VIEW', 'Kan lezen');
define('_CO_IMFORUM_PERM_FORUM_VIEW_DSC', 'Groepen die onderwerpen in dit forum kunnen openen');
define('_CO_IMFORUM_PERM_FORUM_POST', 'Kan onderwerpen starten');
define('_CO_IMFORUM_PERM_FORUM_POST_DSC', 'Groepen die een nieuw onderwerp kunnen starten');
define('_CO_IMFORUM_PERM_FORUM_REPLY', 'Kan reageren');
define('_CO_IMFORUM_PERM_FORUM_REPLY_DSC', 'Groepen die op onderwerpen kunnen reageren');
define('_CO_IMFORUM_PERM_FORUM_EDIT', 'Kan eigen berichten bewerken');
define('_CO_IMFORUM_PERM_FORUM_EDIT_DSC', 'Groepen die hun eigen berichten kunnen bewerken');
define('_CO_IMFORUM_PERM_FORUM_DELETE', 'Kan eigen berichten verwijderen');
define('_CO_IMFORUM_PERM_FORUM_DELETE_DSC', 'Groepen die hun eigen berichten kunnen verwijderen');
define('_CO_IMFORUM_PERM_FORUM_ATTACH', 'Kan bestanden bijvoegen');
define('_CO_IMFORUM_PERM_FORUM_ATTACH_DSC', 'Groepen die bijlagen kunnen toevoegen');
define('_CO_IMFORUM_PERM_FORUM_NOAPPROVE', 'Berichten zonder goedkeuring');
define('_CO_IMFORUM_PERM_FORUM_NOAPPROVE_DSC', 'Groepen waarvan berichten verschijnen zonder goedkeuring door een moderator');

// messages
define('_CO_IMFORUM_CATEGORY_NOT_EMPTY', 'Deze categorie bevat nog forums. Verplaats of verwijder die eerst.');
define('_CO_IMFORUM_FORUM_NO_PARENT', 'Geen (hoogste niveau)');
define('_CO_IMFORUM_FORUM_PARENT_LOOP', 'Een forum kan niet onder zichzelf of onder een van zijn subforums geplaatst worden.');
define('_CO_IMFORUM_POST_NO_TOPIC', 'Het onderwerp van dit bericht bestaat niet.');
define('_CO_IMFORUM_REPORT_POST_GONE', '(bericht verwijderd)');
define('_CO_IMFORUM_SUSPENSION_ALL_FORUMS', 'Alle forums');
define('_CO_IMFORUM_VIEW', 'Bekijken');
