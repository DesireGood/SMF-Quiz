<?php

declare(strict_types=1);

namespace Quiz\Controller;

use Quiz\Traits\HasErrorHandling;

/**
 * AjaxController
 *
 * Handles all AJAX/XHR actions for the Quiz modification:
 * - Image directory listing
 * - Image upload
 *
 * All methods send their own headers and terminate execution via die().
 * They must be called from within the SMF request lifecycle.
 *
 * @package Quiz\Controller
 */
final class AjaxController
{
    use HasErrorHandling;

    /**
     * Allowed image MIME-type prefixes
     *
     * @var array<string>
     */
    private const ALLOWED_MIME_PREFIXES = ['image/'];

    /**
     * Dispatch an AJAX sub-action
     *
     * @param string $subAction Sub-action name from the query string
     * @return void
     */
    public function dispatch(string $subAction): void
    {
        if (!allowedTo('quiz_admin')) {
            $this->sendJsonError('permission_denied', 'You do not have permission to perform this action.');
            return;
        }

        match ($subAction) {
            'imageList'   => $this->imageList(),
            'imageUpload' => $this->imageUpload(),
            default       => $this->sendJsonError('invalid_action', 'Unknown AJAX action.'),
        };
    }

    /**
     * Return a list of images in the quiz images folder as XML
     *
     * @return void
     */
    public function imageList(): void
    {
        global $boarddir;

        $imageFolder = $this->sanitizeFolderName($_GET['imageFolder'] ?? '');
        $path = $boarddir . '/Themes/default/images/quiz_images/' . $imageFolder;

        if (!is_dir($path)) {
            header('Content-Type: text/xml');
            echo '<files/>';
            die();
        }

        $files = [];
        $handle = opendir($path);
        if ($handle !== false) {
            while (($file = readdir($handle)) !== false) {
                if ($file !== '.' && $file !== '..') {
                    $files[] = htmlspecialchars($file, ENT_XML1, 'UTF-8');
                }
            }
            closedir($handle);
        }

        sort($files);

        header('Content-Type: text/xml');
        echo '<files>';
        foreach ($files as $file) {
            echo '<file>' . $file . '</file>';
        }
        echo '</files>';
        die();
    }

    /**
     * Handle an image file upload
     *
     * @return void
     */
    public function imageUpload(): void
    {
        global $boarddir;

        $error    = '';
        $msg      = '';
        $fileName = '';
        $fileKey  = 'fileToUpload';

        if (!empty($_FILES[$fileKey]['error'])) {
            $error = $this->uploadErrorMessage((int)$_FILES[$fileKey]['error']);
        } elseif (empty($_FILES[$fileKey]['tmp_name']) || $_FILES[$fileKey]['tmp_name'] === 'none') {
            $error = 'No file was uploaded.';
        } elseif (!$this->isAllowedImageType($_FILES[$fileKey]['type'] ?? '')) {
            $error = 'The uploaded file is not an image. Please upload a valid image file.';
            @unlink($_FILES[$fileKey]['tmp_name']);
        } else {
            $imageFolder = $this->sanitizeFolderName($_GET['imageFolder'] ?? '');
            $originalName = basename($_FILES[$fileKey]['name']);
            $safeFileName = preg_replace('/[^a-zA-Z0-9_.\-]/', '_', $originalName);
            $destination  = $boarddir . '/Themes/default/images/quiz_images/'
                . ($imageFolder !== '' ? $imageFolder . '/' : '')
                . $safeFileName;

            if (file_exists($destination)) {
                $error = 'Filename already exists at destination.';
            } elseif (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $destination)) {
                $error = 'Failed to move the uploaded file.';
            } else {
                @chmod($destination, 0644);
                $msg      = 'File uploaded successfully.';
                $fileName = $safeFileName;
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'error'    => $error,
            'msg'      => $msg,
            'filename' => $fileName,
        ], JSON_THROW_ON_ERROR);
        die();
    }

    /**
     * Send a JSON error response and terminate
     *
     * @param string $code Machine-readable error code
     * @param string $message Human-readable message
     * @return void
     */
    private function sendJsonError(string $code, string $message): void
    {
        header('Content-Type: application/json');
        echo json_encode(['error' => $code, 'message' => $message], JSON_THROW_ON_ERROR);
        die();
    }

    /**
     * Map a PHP upload error code to a human-readable message
     *
     * @param int $code PHP $_FILES error code
     * @return string
     */
    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'The file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the MAX_FILE_SIZE directive in the form.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
            default               => 'Unknown upload error.',
        };
    }

    /**
     * Check whether a MIME type is an allowed image type
     *
     * @param string $mimeType MIME type string
     * @return bool
     */
    private function isAllowedImageType(string $mimeType): bool
    {
        foreach (self::ALLOWED_MIME_PREFIXES as $prefix) {
            if (str_starts_with($mimeType, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sanitise a folder name to prevent path traversal attacks
     *
     * @param string $folder Raw folder name from user input
     * @return string Safe folder name (empty string if invalid)
     */
    private function sanitizeFolderName(string $folder): string
    {
        // Strip everything that is not alphanumeric, underscore, hyphen, or forward slash
        $safe = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $folder);

        // Collapse consecutive slashes and remove leading/trailing slashes
        $safe = trim((string)preg_replace('/\/+/', '/', $safe), '/');

        // Disallow path traversal — check for '..' after normalization
        if (str_contains($safe, '..')) {
            return '';
        }

        return $safe;
    }
}
