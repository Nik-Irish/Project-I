<?php
/**
 * Notification mark-read / delete / clear handlers.
 */

function handle_notification_actions(string $action, string &$errorMessage, string &$successMessage, string &$view): void
{
    if ($action === 'mark_read') {
        $nid = (int)($_POST['id'] ?? 0);
        notifications_mark_read($nid);
        $successMessage = 'Notification marked as read.';
        $view = 'notifications';
        return;
    }

    if ($action === 'mark_all_read') {
        notifications_mark_all_read();
        $successMessage = 'All notifications marked as read.';
        $view = 'notifications';
        return;
    }

    if ($action === 'delete_notification') {
        $nid = (int)($_POST['id'] ?? 0);
        notifications_delete($nid);
        $successMessage = 'Notification removed.';
        $view = 'notifications';
        return;
    }

    if ($action === 'clear_notifications') {
        notifications_clear();
        $successMessage = 'All notifications cleared.';
        $view = 'notifications';
    }
}
