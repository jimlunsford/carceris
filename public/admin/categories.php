<?php

declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'manage_settings')) {
    http_response_code(403);
    exit('You do not have permission to manage categories.');
}

if (request_method() === 'POST') {
    csrf_require();

    $action = post_string('action', 40);

    try {
        if ($action === 'create') {
            $name = post_string('name', 80);
            $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';

            $categoryId = carceris_create_category($name, $isActive);

            audit_event(
                'category_created',
                (int) $user['id'],
                $user['username'] ?? null,
                'Created category: ' . $name . '. Status: ' . ($isActive ? 'active' : 'inactive') . '.',
                'category',
                $categoryId
            );

            flash_set('success', 'Category added.');
            redirect('/admin/categories.php');
        }

        if ($action === 'update') {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $name = post_string('name', 80);
            $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';

            $before = carceris_category_by_id($categoryId);
            carceris_update_category($categoryId, $name, $isActive);
            $after = carceris_category_by_id($categoryId);

            audit_event(
                'category_updated',
                (int) $user['id'],
                $user['username'] ?? null,
                'Updated category from "' . ($before['name'] ?? 'unknown') . '" to "' . ($after['name'] ?? $name) . '". Status before: '
                    . ((int) ($before['is_active'] ?? 0) === 1 ? 'active' : 'inactive')
                    . '. After: '
                    . ((int) ($after['is_active'] ?? 0) === 1 ? 'active' : 'inactive') . '.',
                'category',
                $categoryId
            );

            flash_set('success', 'Category updated.');
            redirect('/admin/categories.php');
        }

        if ($action === 'move') {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $direction = post_string('direction', 10);

            $result = carceris_move_category($categoryId, $direction);

            if ($result === null) {
                flash_set('success', 'Category order unchanged.');
                redirect('/admin/categories.php');
            }

            audit_event(
                'category_reordered',
                (int) $user['id'],
                $user['username'] ?? null,
                'Moved category "' . ($result['moved']['name'] ?? (string) $categoryId) . '" ' . $direction
                    . ' relative to "' . ($result['swapped_with']['name'] ?? 'unknown') . '".',
                'category',
                $categoryId
            );

            flash_set('success', 'Category moved ' . $direction . '.');
            redirect('/admin/categories.php');
        }

        flash_set('error', 'Unknown category action.');
    } catch (Throwable $exception) {
        flash_set('error', $exception->getMessage());
    }

    redirect('/admin/categories.php');
}

$categories = carceris_categories_all();

audit_event(
    'categories_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed category management page.'
);

require __DIR__ . '/../../app/views/admin/categories.php';
