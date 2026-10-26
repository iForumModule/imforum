<?php
/**
 * The form to write, edit or delete a post, built on the IPF secure form
 *
 * The form takes its fields from the Post object. Fields the forum or the
 * visitor may not use are hidden before the form is built, and post.php reads
 * only the fields it knows, so a field added by hand to the request changes
 * nothing.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_PostForm {

	/**
	 * @param array<string, mixed> $values what the form starts with
	 */
	public static function build(
		mod_imforum_Post $form_post,
		mod_imforum_Forum $forum,
		?mod_imforum_Topic $topic,
		?mod_imforum_Post $existing,
		string $mode,
		array $values,
		mod_imforum_PermissionService $permissions,
		bool $captcha
	): icms_ipf_form_Secure {
		$member = $permissions->getUid() > 0;
		$startsTopic = $mode === 'new' || ($mode === 'edit' && $topic !== null && $existing !== null
			&& (int) $topic->getVar('topic_first_post_id', 'e') === (int) $existing->id());

		$hidden = ['icon'];
		if ($member || $mode === 'edit') {
			$hidden[] = 'poster_name';
		}
		if (!(int) $forum->getVar('allow_html', 'e')) {
			$hidden[] = 'dohtml';
		}
		if (!$member || !(int) $forum->getVar('allow_sig', 'e')) {
			$hidden[] = 'attachsig';
		}
		if (!$member) {
			array_push($hidden, 'post_karma', 'require_reply');
		}
		$form_post->hideFieldFromForm($hidden);

		foreach (['subject', 'post_text', 'poster_name'] as $field) {
			$form_post->setVar($field, is_string($values[$field] ?? null) ? $values[$field] : '');
		}
		// the text is stored as typed, so the form must escape it or a post could close the textarea
		$form_post->setVar('post_text', htmlspecialchars((string) $form_post->getVar('post_text', 'n'), ENT_QUOTES, 'UTF-8'));
		foreach (['attachsig', 'require_reply', 'dohtml', 'dosmiley', 'doxcode', 'doimage', 'dobr'] as $field) {
			$form_post->setVar($field, !empty($values[$field]) ? 1 : 0);
		}
		$form_post->setVar('post_karma', max(0, (int) ($values['post_karma'] ?? 0)));

		$caption = match ($mode) {
			'new' => _MD_IMFORUM_NEW_TOPIC,
			'reply' => _MD_IMFORUM_REPLY,
			default => _MD_IMFORUM_EDIT,
		};
		$form = new icms_ipf_form_Secure($form_post, 'imforum_post', $caption, mod_imforum_View::url('post'), null, $mode === 'edit' ? _MD_IMFORUM_SAVE : _MD_IMFORUM_POST);
		$form->addCustomButton('preview_button', _MD_IMFORUM_PREVIEW);

		self::add($form, new icms_form_elements_Hidden('do', $mode));
		self::add($form, new icms_form_elements_Hidden('forum', (string) $forum->id()));
		if ($topic !== null) {
			self::add($form, new icms_form_elements_Hidden('topic', (string) $topic->id()));
		}
		if ($existing !== null && $mode === 'edit') {
			self::add($form, new icms_form_elements_Hidden('post', (string) $existing->id()));
		}
		if ($mode === 'reply' && !empty($values['pid'])) {
			self::add($form, new icms_form_elements_Hidden('pid', (string) (int) $values['pid']));
		}

		$prefixes = mod_imforum_PostService::getPrefixes();
		if ($startsTopic && $prefixes && (int) $forum->getVar('allow_subject_prefix', 'e') === 1) {
			$select = new icms_form_elements_Select(_MD_IMFORUM_PREFIX, 'prefix', (int) ($values['prefix'] ?? 0));
			$select->addOption('0', _MD_IMFORUM_PREFIX_NONE);
			$select->addOptionArray($prefixes);
			self::add($form, $select);
		}

		if ($permissions->canAttach($forum) && (int) mod_imforum_Settings::get('attach_max_files') > 0) {
			$uploader = new mod_imforum_Uploader($forum);
			$note = sprintf(_MD_IMFORUM_ATTACH_NOTE, (int) $forum->getVar('attach_maxkb', 'e'), htmlspecialchars(implode(', ', $uploader->getAcceptedExtensions()), ENT_QUOTES, 'UTF-8'));
			$limit = max(1, (int) mod_imforum_Settings::get('attach_max_files'));
			$kept = 0;
			if ($existing !== null && $mode === 'edit') {
				$attachments = icms_getModuleHandler('attachment', IMFORUM_DIRNAME)->getByPosts([(int) $existing->id()])[(int) $existing->id()] ?? [];
				$kept = count($attachments);
				if ($attachments) {
					$box = new icms_form_elements_Checkbox(_MD_IMFORUM_ATTACH_REMOVE, 'remove_attachment');
					foreach ($attachments as $attachment) {
						$box->addOption((string) $attachment->id(), htmlspecialchars((string) $attachment->getVar('name_display', 'n'), ENT_QUOTES, 'UTF-8'));
					}
					self::add($form, $box);
				}
			}
			for ($i = 0; $i < max(0, $limit - $kept); $i++) {
				$file = new icms_form_elements_File($i === 0 ? _MD_IMFORUM_ATTACH : '', 'attachment_' . $i, (int) $forum->getVar('attach_maxkb', 'e') * 1024);
				if ($i === 0) {
					$file->setDescription($note);
				}
				self::add($form, $file);
			}
		}

		if ($captcha) {
			self::add($form, new icms_form_elements_Captcha(_MD_IMFORUM_CAPTCHA, 'icmscaptcha', true));
		}
		return $form;
	}

	/**
	 * icms_ipf_form_Base::addElement() takes its element by reference
	 */
	private static function add(icms_ipf_form_Base $form, icms_form_Element $element): void {
		$form->addElement($element);
	}
}
