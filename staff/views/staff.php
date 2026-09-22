<?php
/**
 * views/staff.php - Manage staff accounts
 *
 * Requires: $staffUsers (array), $editStaff (array|null)
 */
?>
<link rel="stylesheet" href="css/staff.css">

<!-- ═══════ Add Staff ═══════ -->
<div class="form-card add-staff-card">
    <h2>Add Staff User</h2>
    <p class="form-hint">
        Username: 3-15 letters and numbers.
        Password: 8+ characters with uppercase, lowercase, number, and symbol.
    </p>
    <form method="POST" action="dashboard.php?view=staff">
        <input type="hidden" name="action" value="staff_create">
        <div class="form-grid">
            <div class="form-group">
                <label>Username <span class="req">*</span></label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password <span class="req">*</span></label>
                <div class="pw-input-wrap">
                    <input type="password" name="password" id="staff-password-add" required>
                    <button type="button" class="pw-toggle" data-target="staff-password-add">Show</button>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add Staff</button>
        </div>
    </form>
</div>

<!-- ═══════ Staff list ═══════ -->
<div class="table-wrap">
    <?php if (empty($staffUsers)): ?>
        <div class="empty-state"><p>No staff accounts yet. Add one above.</p></div>
    <?php else: ?>
        <table class="data-table staff-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($staffUsers as $st): $sid = (int)$st['id']; ?>
                <?php $isEditing = !empty($editStaff) && (int)$editStaff['id'] === $sid; ?>

                <?php if ($isEditing): ?>
                    <!-- ── Inline edit row ── -->
                    <tr class="editing-row">
                        <td><?php echo $sid; ?></td>
                        <td colspan="3">
                            <form method="POST" action="dashboard.php?view=staff"
                                  class="inline-edit-form">
                                <input type="hidden" name="action" value="staff_update">
                                <input type="hidden" name="id" value="<?php echo $sid; ?>">

                                <label class="edit-label">Username</label>
                                <input type="text" name="username"
                                       value="<?php echo htmlspecialchars($st['username']); ?>"
                                       class="edit-input edit-username" required>

                                <label class="edit-label">New password
                                    <span class="edit-optional">(leave blank to keep)</span>
                                </label>
                                <div class="pw-input-wrap edit-pw-wrap">
                                    <input type="password" name="password"
                                           id="staff-password-edit-<?php echo $sid; ?>"
                                           class="edit-input">
                                    <button type="button" class="pw-toggle"
                                            data-target="staff-password-edit-<?php echo $sid; ?>">Show</button>
                                </div>

                                <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                <a href="dashboard.php?view=staff" class="btn btn-sm btn-ghost">Cancel</a>
                            </form>
                        </td>
                    </tr>

                <?php else: ?>
                    <!-- ── Staff row ── -->
                    <tr>
                        <td><?php echo $sid; ?></td>
                        <td class="staff-username"><?php echo htmlspecialchars($st['username']); ?></td>
                        <td class="staff-added">
                            <?php echo !empty($st['created_at'])
                                ? htmlspecialchars(date('Y-m-d', strtotime($st['created_at'])))
                                : '-'; ?>
                        </td>
                        <td class="row-actions">
                            <a href="dashboard.php?view=staff&amp;edit=<?php echo $sid; ?>"
                               class="btn btn-sm btn-secondary">Edit</a>
                            <button type="button" class="btn btn-sm btn-secondary"
                                    data-reset-row="pw-reset-<?php echo $sid; ?>">Reset Password</button>
                            <form method="POST" class="inline-form"
                                  onsubmit="return confirm('Delete staff user &quot;<?php echo htmlspecialchars($st['username']); ?>&quot;?');">
                                <input type="hidden" name="action" value="staff_delete">
                                <input type="hidden" name="id" value="<?php echo $sid; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>

                    <!-- ── Collapsed reset-password row ── -->
                    <tr class="pw-reset-row" id="pw-reset-<?php echo $sid; ?>">
                        <td colspan="4">
                            <form method="POST" action="dashboard.php?view=staff"
                                  class="pw-reset-form">
                                <input type="hidden" name="action" value="staff_password_update">
                                <input type="hidden" name="id" value="<?php echo $sid; ?>">

                                <label class="edit-label">
                                    New password for
                                    <strong><?php echo htmlspecialchars($st['username']); ?></strong>
                                </label>
                                <div class="pw-input-wrap reset-pw-wrap">
                                    <input type="password" name="password"
                                           id="staff-password-<?php echo $sid; ?>"
                                           class="staff-password-input"
                                           placeholder="8+ chars, upper, lower, number, symbol"
                                           aria-label="New password for <?php echo htmlspecialchars($st['username']); ?>"
                                           required>
                                    <button type="button" class="pw-toggle"
                                            data-target="staff-password-<?php echo $sid; ?>">Show</button>
                                </div>

                                <button type="submit" class="btn btn-sm btn-primary">Set Password</button>
                                <button type="button" class="btn btn-sm btn-ghost"
                                        data-reset-row="pw-reset-<?php echo $sid; ?>">Cancel</button>
                            </form>
                        </td>
                    </tr>
                <?php endif; ?>

            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ═══════ Scripts: password visibility + reset-row toggle ═══════ -->
<script>
document.querySelectorAll('.pw-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        var target = document.getElementById(button.dataset.target);
        if (!target) return;
        if (target.type === 'password') {
            target.type = 'text';
            button.textContent = 'Hide';
        } else {
            target.type = 'password';
            button.textContent = 'Show';
        }
    });
});

document.querySelectorAll('[data-reset-row]').forEach(function (button) {
    button.addEventListener('click', function () {
        var row = document.getElementById(button.dataset.resetRow);
        if (row) row.classList.toggle('open');
    });
});
</script>   