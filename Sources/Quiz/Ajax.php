<?php

declare(strict_types=1);

if (!defined('SMF')) {
    die('Hacking attempt...');
}

use Quiz\Controller\AjaxController;

/**
 * Entry point for all Quiz AJAX/XHR actions.
 *
 * SMF routes requests here via the integrate_actions hook.
 * Actual logic is handled by AjaxController.
 *
 * @return void
 */
function quizImageUpload(): void
{
    $subAction = trim((string)($_GET['sa'] ?? ''));

    if ($subAction === '') {
        die();
    }

    $controller = new AjaxController();
    $controller->dispatch($subAction);
}