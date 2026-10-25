<?php
/**
 * Form labels, permission captions and messages for the IPF objects, English
 *
 * IPF picks up _CO_IMFORUM_<ITEM>_<FIELD> and _CO_IMFORUM_<ITEM>_<FIELD>_DSC
 * automatically as caption and description (icms_ipf_Object::initVar).
 * Labels for weight, SEO and the do* display options come from the core.
 */

// category
define('_CO_IMFORUM_CATEGORY_CAT_ID', 'ID');
define('_CO_IMFORUM_CATEGORY_CAT_TITLE', 'Title');
define('_CO_IMFORUM_CATEGORY_CAT_DESCRIPTION', 'Description');
define('_CO_IMFORUM_CATEGORY_CAT_IMAGE', 'Image');
define('_CO_IMFORUM_CATEGORY_CAT_URL', 'Link');
define('_CO_IMFORUM_CATEGORY_CAT_URL_DSC', 'Optional website shown with the category');

// forum
define('_CO_IMFORUM_FORUM_FORUM_ID', 'ID');
define('_CO_IMFORUM_FORUM_CAT_ID', 'Category');
define('_CO_IMFORUM_FORUM_PARENT_FORUM', 'Parent forum');
define('_CO_IMFORUM_FORUM_FORUM_NAME', 'Name');
define('_CO_IMFORUM_FORUM_FORUM_DESC', 'Description');
define('_CO_IMFORUM_FORUM_FORUM_ACTIVE', 'Active');
define('_CO_IMFORUM_FORUM_FORUM_ACTIVE_DSC', 'Inactive forums are hidden from everyone except administrators');
define('_CO_IMFORUM_FORUM_ALLOW_HTML', 'Allow HTML');
define('_CO_IMFORUM_FORUM_ALLOW_SIG', 'Allow signatures');
define('_CO_IMFORUM_FORUM_ALLOW_SUBJECT_PREFIX', 'Allow subject prefixes');
define('_CO_IMFORUM_FORUM_HOT_THRESHOLD', 'Hot topic threshold');
define('_CO_IMFORUM_FORUM_HOT_THRESHOLD_DSC', 'Number of replies from which a topic is shown as hot');
define('_CO_IMFORUM_FORUM_ATTACH_MAXKB', 'Maximum attachment size (KB)');
define('_CO_IMFORUM_FORUM_ATTACH_EXT', 'Allowed attachment extensions');
define('_CO_IMFORUM_FORUM_ATTACH_EXT_DSC', 'Separate extensions with |, for example zip|jpg|png');
define('_CO_IMFORUM_FORUM_FORUM_TOPICS', 'Topics');
define('_CO_IMFORUM_FORUM_FORUM_POSTS', 'Posts');
define('_CO_IMFORUM_FORUM_FORUM_LAST_POST_ID', 'Last post');
define('_CO_IMFORUM_FORUM_MODERATORS', 'Moderators');
define('_CO_IMFORUM_FORUM_MODERATORS_DSC', 'Members who moderate this forum');

// moderator
define('_CO_IMFORUM_MODERATOR_MODERATOR_ID', 'ID');
define('_CO_IMFORUM_MODERATOR_FORUM_ID', 'Forum');
define('_CO_IMFORUM_MODERATOR_UID', 'Moderator');

// topic
define('_CO_IMFORUM_TOPIC_TOPIC_ID', 'ID');
define('_CO_IMFORUM_TOPIC_FORUM_ID', 'Forum');
define('_CO_IMFORUM_TOPIC_TOPIC_TITLE', 'Title');
define('_CO_IMFORUM_TOPIC_TOPIC_SUBJECT', 'Subject prefix');
define('_CO_IMFORUM_TOPIC_TOPIC_POSTER', 'Started by');
define('_CO_IMFORUM_TOPIC_POSTER_NAME', 'Guest name');
define('_CO_IMFORUM_TOPIC_TOPIC_TIME', 'Started on');
define('_CO_IMFORUM_TOPIC_TOPIC_VIEWS', 'Views');
define('_CO_IMFORUM_TOPIC_TOPIC_STATUS', 'Locked');
define('_CO_IMFORUM_TOPIC_TOPIC_STICKY', 'Sticky');
define('_CO_IMFORUM_TOPIC_TOPIC_DIGEST', 'Featured');
define('_CO_IMFORUM_TOPIC_DIGEST_TIME', 'Featured on');
define('_CO_IMFORUM_TOPIC_APPROVED', 'Approved');
define('_CO_IMFORUM_TOPIC_TOPIC_REPLIES', 'Replies');
define('_CO_IMFORUM_TOPIC_TOPIC_FIRST_POST_ID', 'First post');
define('_CO_IMFORUM_TOPIC_TOPIC_LAST_POST_ID', 'Last post');

// post
define('_CO_IMFORUM_POST_POST_ID', 'ID');
define('_CO_IMFORUM_POST_TOPIC_ID', 'Topic');
define('_CO_IMFORUM_POST_FORUM_ID', 'Forum');
define('_CO_IMFORUM_POST_PID', 'Reply to');
define('_CO_IMFORUM_POST_UID', 'Author');
define('_CO_IMFORUM_POST_POSTER_NAME', 'Guest name');
define('_CO_IMFORUM_POST_POSTER_IP', 'IP address');
define('_CO_IMFORUM_POST_POST_TIME', 'Posted on');
define('_CO_IMFORUM_POST_SUBJECT', 'Subject');
define('_CO_IMFORUM_POST_POST_TEXT', 'Message');
define('_CO_IMFORUM_POST_POST_EDIT', 'Edits');
define('_CO_IMFORUM_POST_ICON', 'Icon');
define('_CO_IMFORUM_POST_ATTACHSIG', 'Attach signature');
define('_CO_IMFORUM_POST_APPROVED', 'Approved');
define('_CO_IMFORUM_POST_POST_KARMA', 'Required karma');
define('_CO_IMFORUM_POST_POST_KARMA_DSC', 'Only members with at least this karma can read the post');
define('_CO_IMFORUM_POST_REQUIRE_REPLY', 'Reply required to read');
define('_CO_IMFORUM_POST_REQUIRE_REPLY_DSC', 'Members only see the message after they replied to the topic');

// attachment
define('_CO_IMFORUM_ATTACHMENT_ATTACH_ID', 'ID');
define('_CO_IMFORUM_ATTACHMENT_POST_ID', 'Post');
define('_CO_IMFORUM_ATTACHMENT_NAME_SAVED', 'Stored file name');
define('_CO_IMFORUM_ATTACHMENT_NAME_DISPLAY', 'File name');
define('_CO_IMFORUM_ATTACHMENT_MIMETYPE', 'MIME type');
define('_CO_IMFORUM_ATTACHMENT_FILESIZE', 'Size (bytes)');
define('_CO_IMFORUM_ATTACHMENT_DOWNLOADS', 'Downloads');
define('_CO_IMFORUM_ATTACHMENT_ONLINE', 'Visible');
define('_CO_IMFORUM_ATTACHMENT_ATTACH_TIME', 'Uploaded on');

// report
define('_CO_IMFORUM_REPORT_REPORT_ID', 'ID');
define('_CO_IMFORUM_REPORT_POST_ID', 'Post');
define('_CO_IMFORUM_REPORT_REPORTER_UID', 'Reported by');
define('_CO_IMFORUM_REPORT_REPORTER_IP', 'IP address');
define('_CO_IMFORUM_REPORT_REPORT_TIME', 'Reported on');
define('_CO_IMFORUM_REPORT_REPORT_TEXT', 'Reason');
define('_CO_IMFORUM_REPORT_REPORT_RESULT', 'Handled');
define('_CO_IMFORUM_REPORT_REPORT_MEMO', 'Moderator note');

// suspension
define('_CO_IMFORUM_SUSPENSION_SUSPENSION_ID', 'ID');
define('_CO_IMFORUM_SUSPENSION_UID', 'Member');
define('_CO_IMFORUM_SUSPENSION_IP', 'IP address');
define('_CO_IMFORUM_SUSPENSION_FORUM_ID', 'Forum');
define('_CO_IMFORUM_SUSPENSION_FORUM_ID_DSC', 'No forum means every forum');
define('_CO_IMFORUM_SUSPENSION_START_TIME', 'From');
define('_CO_IMFORUM_SUSPENSION_END_TIME', 'Until');
define('_CO_IMFORUM_SUSPENSION_DESCRIPTION', 'Reason');

// readmark
define('_CO_IMFORUM_READMARK_READ_ID', 'ID');
define('_CO_IMFORUM_READMARK_READ_TYPE', 'Type');
define('_CO_IMFORUM_READMARK_UID', 'Member');
define('_CO_IMFORUM_READMARK_ITEM_ID', 'Forum or topic');
define('_CO_IMFORUM_READMARK_POST_ID', 'Last read post');
define('_CO_IMFORUM_READMARK_READ_TIME', 'Read on');

// online
define('_CO_IMFORUM_ONLINE_ONLINE_ID', 'ID');
define('_CO_IMFORUM_ONLINE_FORUM_ID', 'Forum');
define('_CO_IMFORUM_ONLINE_TOPIC_ID', 'Topic');
define('_CO_IMFORUM_ONLINE_UID', 'Member');
define('_CO_IMFORUM_ONLINE_UNAME', 'Name');
define('_CO_IMFORUM_ONLINE_IP', 'IP address');
define('_CO_IMFORUM_ONLINE_UPDATED', 'Last seen');

// digest
define('_CO_IMFORUM_DIGEST_DIGEST_ID', 'ID');
define('_CO_IMFORUM_DIGEST_DIGEST_TIME', 'Sent on');
define('_CO_IMFORUM_DIGEST_DIGEST_CONTENT', 'Content');

// archive
define('_CO_IMFORUM_ARCHIVE_ARCHIVE_ID', 'ID');
define('_CO_IMFORUM_ARCHIVE_TOPIC_ID', 'Topic');
define('_CO_IMFORUM_ARCHIVE_POST_ID', 'Post');
define('_CO_IMFORUM_ARCHIVE_POST_TEXT', 'Message');
define('_CO_IMFORUM_ARCHIVE_ARCHIVED_TIME', 'Archived on');

// permtemplate
define('_CO_IMFORUM_PERMTEMPLATE_TEMPLATE_ID', 'ID');
define('_CO_IMFORUM_PERMTEMPLATE_PERM_NAME', 'Permission');
define('_CO_IMFORUM_PERMTEMPLATE_GROUPID', 'Group');

// permissions
define('_CO_IMFORUM_PERM_CATEGORY_ACCESS', 'Can see the category');
define('_CO_IMFORUM_PERM_CATEGORY_ACCESS_DSC', 'Groups that see this category and its forums on the index');
define('_CO_IMFORUM_PERM_FORUM_ACCESS', 'Can see the forum');
define('_CO_IMFORUM_PERM_FORUM_ACCESS_DSC', 'Groups that see this forum in the list');
define('_CO_IMFORUM_PERM_FORUM_VIEW', 'Can read');
define('_CO_IMFORUM_PERM_FORUM_VIEW_DSC', 'Groups that can open topics in this forum');
define('_CO_IMFORUM_PERM_FORUM_POST', 'Can start topics');
define('_CO_IMFORUM_PERM_FORUM_POST_DSC', 'Groups that can start a new topic');
define('_CO_IMFORUM_PERM_FORUM_REPLY', 'Can reply');
define('_CO_IMFORUM_PERM_FORUM_REPLY_DSC', 'Groups that can reply to topics');
define('_CO_IMFORUM_PERM_FORUM_EDIT', 'Can edit own posts');
define('_CO_IMFORUM_PERM_FORUM_EDIT_DSC', 'Groups that can edit their own posts');
define('_CO_IMFORUM_PERM_FORUM_DELETE', 'Can delete own posts');
define('_CO_IMFORUM_PERM_FORUM_DELETE_DSC', 'Groups that can delete their own posts');
define('_CO_IMFORUM_PERM_FORUM_ATTACH', 'Can attach files');
define('_CO_IMFORUM_PERM_FORUM_ATTACH_DSC', 'Groups that can add attachments');
define('_CO_IMFORUM_PERM_FORUM_NOAPPROVE', 'Posts without approval');
define('_CO_IMFORUM_PERM_FORUM_NOAPPROVE_DSC', 'Groups whose posts appear without moderator approval');

// messages
define('_CO_IMFORUM_CATEGORY_NOT_EMPTY', 'This category still holds forums. Move or delete them first.');
define('_CO_IMFORUM_FORUM_NO_PARENT', 'None (top level)');
define('_CO_IMFORUM_FORUM_PARENT_LOOP', 'A forum cannot be placed below itself or below one of its subforums.');
define('_CO_IMFORUM_POST_NO_TOPIC', 'The topic of this post does not exist.');
define('_CO_IMFORUM_REPORT_POST_GONE', '(post deleted)');
define('_CO_IMFORUM_SUSPENSION_ALL_FORUMS', 'Every forum');
define('_CO_IMFORUM_VIEW', 'View');
