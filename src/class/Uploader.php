<?php
/**
 * Accepts files attached to a post
 *
 * The extension is checked against the list of the forum and against a list
 * of extensions that are never allowed. The file is stored under a random
 * name without extension, so a web server cannot run it whatever it is called,
 * and attachment.php serves it as a download.
 *
 * @copyright	The iForum project
 * @license		MIT, see LICENSE
 * @package		imforum
 * @since		1.0
 */

defined('ICMS_ROOT_PATH') || die('ImpressCMS root path not defined');

include_once dirname(__DIR__) . '/include/common.php';

class mod_imforum_Uploader {

	/** Never accepted, whatever an administrator puts in attach_ext */
	public const BLOCKED = [
		'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'inc', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash',
		'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'scr', 'vbs', 'jar', 'js', 'htm', 'html', 'xhtml', 'svg', 'htaccess', 'htpasswd', 'ini',
	];

	private mod_imforum_Forum $forum;

	public function __construct(mod_imforum_Forum $forum) {
		$this->forum = $forum;
	}

	/**
	 * Why a file is refused, or null when it is fine
	 *
	 * Does not touch the file: the name and size come from the upload.
	 */
	public function check(string $name, int $size): ?string {
		$extension = self::extensionOf($name);
		if ($extension === '' || in_array($extension, self::BLOCKED, true) || !in_array($extension, $this->forum->getAllowedExtensions(), true)) {
			return sprintf(_MD_IMFORUM_ERR_ATTACH_EXT, htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), htmlspecialchars(implode(', ', $this->getAcceptedExtensions()), ENT_QUOTES, 'UTF-8'));
		}
		$maxKb = (int) $this->forum->getVar('attach_maxkb', 'e');
		if ($size <= 0 || $size > $maxKb * 1024) {
			return sprintf(_MD_IMFORUM_ERR_ATTACH_SIZE, htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $maxKb);
		}
		return null;
	}

	/**
	 * The extensions of the forum that are not blocked
	 *
	 * @return list<string>
	 */
	public function getAcceptedExtensions(): array {
		return array_values(array_diff($this->forum->getAllowedExtensions(), self::BLOCKED));
	}

	public static function extensionOf(string $name): string {
		$dot = strrpos($name, '.');
		return $dot === false ? '' : strtolower(substr($name, $dot + 1));
	}

	/**
	 * Store one uploaded file for a post
	 *
	 * @param string $field name of the file input in $_FILES
	 * @return array{attachment: ?mod_imforum_Attachment, error: ?string}
	 */
	public function store(string $field, int $postId): array {
		$upload = $_FILES[$field] ?? null;
		if (!is_array($upload) || !is_string($upload['name'] ?? null)) {
			return ['attachment' => null, 'error' => null];
		}
		if ((int) ($upload['error'] ?? 0) === UPLOAD_ERR_NO_FILE || $upload['name'] === '') {
			return ['attachment' => null, 'error' => null];
		}
		$name = basename(str_replace('\\', '/', $upload['name']));
		if ($error = $this->check($name, (int) ($upload['size'] ?? 0))) {
			return ['attachment' => null, 'error' => $error];
		}
		if (!imforum_create_upload_folder()) {
			return ['attachment' => null, 'error' => _MD_IMFORUM_ERR_ATTACH_FAILED];
		}

		$extension = self::extensionOf($name);
		$mimeTypes = icms_Utils::mimetypes();
		$uploader = new icms_file_MediaUploadHandler(rtrim(IMFORUM_UPLOAD_PATH, '/'), [(string) ($mimeTypes[$extension] ?? '')], (int) $this->forum->getVar('attach_maxkb', 'e') * 1024);
		$saved = bin2hex(random_bytes(16));
		if (!$uploader->fetchMedia($field)) {
			return ['attachment' => null, 'error' => _MD_IMFORUM_ERR_ATTACH_FAILED];
		}
		$uploader->setTargetFileName($saved);
		if (!$uploader->upload()) {
			return ['attachment' => null, 'error' => _MD_IMFORUM_ERR_ATTACH_FAILED];
		}

		$path = IMFORUM_UPLOAD_PATH . $saved;
		$type = in_array($extension, ['gif', 'jpg', 'jpeg', 'png', 'webp'], true) ? self::imageMime($path) : null;
		if (in_array($extension, ['gif', 'jpg', 'jpeg', 'png', 'webp'], true) && $type === null) {
			@unlink($path);
			return ['attachment' => null, 'error' => sprintf(_MD_IMFORUM_ERR_ATTACH_IMAGE, htmlspecialchars($name, ENT_QUOTES, 'UTF-8'))];
		}

		$handler = icms_getModuleHandler('attachment', IMFORUM_DIRNAME);
		$attachment = $handler->create();
		$attachment->setVar('post_id', $postId);
		$attachment->setVar('name_saved', $saved);
		$attachment->setVar('name_display', $name);
		$attachment->setVar('mimetype', $type ?? 'application/octet-stream');
		$attachment->setVar('filesize', (int) filesize($path));
		if (!$handler->insert($attachment, true)) {
			@unlink($path);
			return ['attachment' => null, 'error' => _MD_IMFORUM_ERR_ATTACH_FAILED];
		}
		return ['attachment' => $attachment, 'error' => null];
	}

	/**
	 * The type of an image by its content, or null when it is not a picture we serve
	 */
	private static function imageMime(string $path): ?string {
		$info = @getimagesize($path);
		$mime = is_array($info) ? $info['mime'] : '';
		return in_array($mime, ['image/gif', 'image/jpeg', 'image/png', 'image/webp'], true) ? $mime : null;
	}
}
