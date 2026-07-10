<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

/**
 * Handles quiz image AJAX requests.
 */
function quizImageUpload(): void
{
    global $context;

    $action = (string) ($_GET['sa'] ?? '');
    if ($action === '') {
        die();
    }

    if (!allowedTo('quiz_admin')) {
        $context['quiz_error'] = 'cannot_admin';
        die();
    }

    $handler = match ($action) {
        'imageList' => 'GetImages',
        'imageUpload' => 'ImageUpload',
        default => null,
    };

    if ($handler !== null) {
        $handler();
    }

    die();
}

/**
 * Returns quiz images from the requested folder as XML.
 */
function GetImages(): void
{
    global $boarddir;

    header('Content-Type: text/xml');

    $imageFolder = trim((string) ($_GET['imageFolder'] ?? ''), '/');
    $path = $boarddir . '/Themes/default/images/quiz_images/' . $imageFolder;
    $dirHandle = @opendir($path);

    if ($dirHandle === false) {
        die("Unable to open $path");
    }

    $files = [];
    while (($file = readdir($dirHandle)) !== false) {
        if ($file !== '.' && $file !== '..') {
            $files[] = $file;
        }
    }

    sort($files);

    echo '<files>';
    foreach ($files as $file) {
        echo '<file>', $file, '</file>';
    }
    echo '</files>';

    closedir($dirHandle);
}

/**
 * Uploads a quiz image and returns the upload result payload.
 */
function ImageUpload(): void
{
    global $boarddir;

    $error = '';
    $msg = '';
    $fileName = '';
    $fileElementName = 'fileToUpload';
    $upload = $_FILES[$fileElementName] ?? [];
    $uploadError = (int) ($upload['error'] ?? 0);
    $tmpName = (string) ($upload['tmp_name'] ?? '');
    $mimeType = (string) ($upload['type'] ?? '');
    $fileName = (string) ($upload['name'] ?? '');

    if ($uploadError !== 0) {
        $error = match ($uploadError) {
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'No file was uploaded.',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk',
            8 => 'File upload stopped by extension',
            default => 'No error code available',
        };
    } elseif ($tmpName === '' || $tmpName === 'none') {
        $error = 'No file was uploaded..';
    } elseif (!preg_match('/image/', $mimeType)) {
        $msg = 'The uploaded file is not an image please upload a valid file';
        @unlink($tmpName);
    } else {
        $msg .= ' File Name: ' . $fileName . ', ';
        $fileSize = @filesize($tmpName);
        $msg .= ' File Size: ' . ($fileSize === false ? 0 : $fileSize);

        $imageFolder = trim((string) ($_GET['imageFolder'] ?? ''), '/');
        if ($imageFolder !== '') {
            $imageFolder .= '/';
        }

        $destination = $boarddir . '/Themes/default/images/quiz_images/' . $imageFolder . $fileName;
        @chmod($destination, 0777);

        if (file_exists($destination)) {
            $msg = 'Filename already exists on destination';
        } else {
            move_uploaded_file($tmpName, $destination);
            @chmod($destination, 0777);
        }
    }

    echo "{";
    echo "error: '" . $error . "',\n";
    echo "msg: '" . $msg . "',\n";
    echo "filename: '" . $fileName . "'\n";
    echo '}';
}
