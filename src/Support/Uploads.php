<?php

declare(strict_types=1);

/**
 * Photo uploads.
 *
 * Written deliberately unlike the fightnights version, which takes the file
 * extension straight from the browser-supplied filename and trusts it. That is
 * an open hole on that site: upload shell.php, get a .php file in a directory
 * Apache will happily execute. Not repeating it here.
 *
 * Three defences, because any one of them can be wrong:
 *   1. The extension must be one we allow.
 *   2. getimagesize() must agree it is really that kind of image — a PHP script
 *      renamed to .jpg fails here.
 *   3. An .htaccess in the upload directory refuses to execute anything at all,
 *      so even a file that somehow gets through is inert.
 *
 * The stored name is random, so a caller can never choose a path, overwrite
 * another upload, or guess what else is in there.
 */
class Uploads
{
    /** Extension => the image types getimagesize() may report for it. */
    private const ALLOWED = [
        'jpg'  => [IMAGETYPE_JPEG],
        'jpeg' => [IMAGETYPE_JPEG],
        'png'  => [IMAGETYPE_PNG],
        'gif'  => [IMAGETYPE_GIF],
        'webp' => [IMAGETYPE_WEBP],
    ];

    /**
     * A backstop, not the everyday limit.
     *
     * public/js/shrink-upload.js resizes in the browser first, so a normal phone
     * photo arrives well under a megabyte. This catches the cases it could not
     * handle — an old browser, or a file that is not really an image.
     *
     * PHP's own upload_max_filesize still applies and is lower by default (2M).
     * When that is what rejects a file the error arrives as UPLOAD_ERR_INI_SIZE,
     * which is why the message below names it rather than saying "try again".
     */
    private const MAX_BYTES = 25 * 1024 * 1024;

    /**
     * Stores an uploaded file and returns its public path, or null when there
     * was no file. Throws RuntimeException with a readable message when the
     * file is present but unacceptable, so the form can say why.
     */
    public static function store($uploadedFile, string $dir = 'uploads'): ?string
    {
        if ($uploadedFile === null || $uploadedFile->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException(match ($uploadedFile->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                    'That file is larger than the server accepts ('
                    . ini_get('upload_max_filesize') . '). Raise upload_max_filesize '
                    . 'and post_max_size in php.ini.',
                UPLOAD_ERR_PARTIAL  => 'The upload was cut off part way. Try again.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                    'The server could not write the file. Check the temp directory.',
                default => 'That file did not upload cleanly. Try again.',
            });
        }
        if ($uploadedFile->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('That image is over 8MB. Please shrink it first.');
        }

        $ext = strtolower(pathinfo((string) $uploadedFile->getClientFilename(), PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            throw new RuntimeException('Images only — jpg, png, gif or webp.');
        }

        // The extension is a claim; this is the check. A .php renamed to .jpg
        // has no image header and dies here.
        $tmp = $uploadedFile->getStream()->getMetadata('uri');
        $info = @getimagesize($tmp);
        if ($info === false || !in_array($info[2], self::ALLOWED[$ext], true)) {
            throw new RuntimeException('That file is not really a ' . $ext . ' image.');
        }

        $root = dirname(__DIR__, 2) . '/public/' . trim($dir, '/');
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }
        self::protect($root);

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadedFile->moveTo($root . '/' . $name);

        return '/' . trim($dir, '/') . '/' . $name;
    }

    /**
     * Refuses execution inside the upload directory.
     *
     * Belt and braces: the checks above should mean nothing executable ever
     * lands here, but if one is ever wrong this is what makes it harmless.
     * Written on first upload so a fresh deploy cannot forget it.
     */
    private static function protect(string $dir): void
    {
        $file = $dir . '/.htaccess';
        if (is_file($file)) {
            return;
        }
        file_put_contents($file, <<<'HT'
# Uploads are data, never code. Nothing in here is ever executed, whatever it
# claims to be — see src/Support/Uploads.php.
php_flag engine off
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .pht .phar
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .pht .phar
<FilesMatch "\.(?i:php|phtml|phar|pht|cgi|pl|py|sh|htaccess)$">
    Require all denied
</FilesMatch>
HT);
    }

    /** Deletes a stored upload. Ignores anything outside the uploads folder. */
    public static function delete(?string $publicPath): void
    {
        if (!$publicPath || !str_starts_with($publicPath, '/uploads/')) {
            return;
        }
        $file = dirname(__DIR__, 2) . '/public' . $publicPath;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
