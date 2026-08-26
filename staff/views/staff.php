<?php
/**
 * views/staff.php — Manage staff accounts
 *
 * Requires: $staffUsers (array), $editStaff (array|null)
 */
?>

<!-- ═══════ Add Staff User ═══════ -->
<div class="form-card add-staff-card" style="margin-bottom:1.5rem;">
    <h2>Add Staff User</h2>
    <form method="POST" action="dashboard.php?view=staff">
        <input type="hidden" name="action" value="staff_create">
        <div class="form-grid">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="pw-input-wrap">
                    <input type="password" name="password" id="staff-password-add" required>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Add Staff</button>
    </form>
</div>

<!-- ═══════ Staff list ═══════ -->
<div class="table-wrap">
    <?php if (empty($staffUsers)): ?>
        <div class="empty-state"><p>No staff accounts yet.</p></div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($staffUsers as $st): ?>
                <?php
                    $isEditing = !empty($editStaff)
                              && (int)$editStaff['id'] === (int)$st['id'];
                ?>

                <?php if ($isEditing): ?>
                    <!-- ── Inline edit row ── -->
                    <tr class="editing-row">
                        <td><?php echo (int)$st['id']; ?></td>
                        <td colspan="3">
                            <form method="POST" action="dashboard.php?view=staff"
                                  class="inline-edit-form">
                                <input type="hidden" name="action" value="staff_update">
                                <input type="hidden" name="id"
                                       value="<?php echo (int)$st['id']; ?>">

                                <input type="text"
                                       name="username"
                                       value="<?php echo htmlspecialchars($st['username']); ?>"
                                       class="edit-input edit-username"
                                       required>

                                <div class="pw-input-wrap edit-pw-wrap">
                                    <input type="password"
                                           name="password"
                                           id="staff-password-edit-<?php echo (int)$st['id']; ?>"
                                           class="edit-input"
                                           placeholder="New password (optional)">
                                    <button type="button" class="pw-toggle"
                                            data-target="staff-password-edit-<?php echo (int)$st['id']; ?>">
                                        Show
                                    </button>
                                </div>

                                <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                <a href="dashboard.php?view=staff"
                                   class="btn btn-sm btn-ghost">Cancel</a>
                            </form>
                        </td>
                    </tr>

                <?php else: ?>
                    <!-- ── Normal row ── -->
                    <tr>
                        <td><?php echo (int)$st['id']; ?></td>
                        <td><?php echo htmlspecialchars($st['username']); ?></td>
                        <td>
                            <form method="POST" action="dashboard.php?view=staff"
                                  class="inline-form staff-password-form">
                                <input type="hidden" name="action" value="staff_password_update">
                                <input type="hidden" name="id"
                                       value="<?php echo (int)$st['id']; ?>">
                                <div class="pw-input-wrap staff-password-wrap">
                                    <input type="password"
                                           name="password"
                                           id="staff-password-<?php echo (int)$st['id']; ?>"
                                           class="staff-password-input"
                                           placeholder="New password"
                                           aria-label="New password for <?php echo htmlspecialchars($st['username']); ?>"
                                           required>
                                    <button type="button" class="pw-toggle"
                                            data-target="staff-password-<?php echo (int)$st['id']; ?>">
                                        Show
                                    </button>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">Set</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" class="inline-form"
                                  style="margin-left:.4rem;"
                                  onsubmit="return confirm('Delete this staff user?');">
                                <input type="hidden" name="action" value="staff_delete">
                                <input type="hidden" name="id"
                                       value="<?php echo (int)$st['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endif; ?>

            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ═══════ Styles ═══════ -->
<style>
    .add-staff-card {
        background: #f5fffa;
        border-color: #dbe2ef;
    }
    .add-staff-card h2,
    .add-staff-card .form-group label {
        color: #112d4e;
    }
    .add-staff-card .form-group input {
        color: #112d4e;
        background: #eef6ff;
        border-color: #9fb9d6;
    }
    .add-staff-card .form-group input:focus {
        border-color: #3f72af;
        box-shadow: 0 0 0 3px rgba(63, 114, 175, .2);
    }

    /* Password wrapper (add form + inline edit) */
    .pw-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }
    .pw-input-wrap input {
        flex: 1;
        padding-right: 3.5rem;
    }
    .pw-toggle {
        position: absolute;
        right: .5rem;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: #3F72AF;
        font-size: .78rem;
        font-weight: 600;
        cursor: pointer;
        padding: .25rem .5rem;
        font-family: inherit;
    }
    .pw-toggle:hover { color: #F9F7F7; }

    /* Add Staff button full-width */
    .btn-block {
        display: block;
        width: 100%;
        margin-top: 1rem;
        padding: .7rem 1rem;
    }

    /* Inline edit row */
    .editing-row td {
        background: rgba(63, 114, 175, .15);
    }
    .inline-edit-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
    }
    .edit-input {
        padding: .4rem .6rem;
        font-size: .85rem;
        color: #F9F7F7;
        background: #0d263f;
        border: 1px solid rgba(219, 226, 239, .3);
        border-radius: 6px;
        outline: none;
        font-family: inherit;
    }
    .edit-input:focus {
        border-color: #3F72AF;
        box-shadow: 0 0 0 3px rgba(63, 114, 175, .3);
    }
    .edit-username { width: 140px; }
    .edit-pw-wrap  { width: 240px; }
    .edit-pw-wrap input { width: 100%; }
    .staff-password-form { display: flex; align-items: center; gap: .4rem; }
    .staff-password-wrap { width: 190px; }
    .staff-password-input { width: 100%; padding: .4rem .6rem; font-size: .82rem; color: #F9F7F7; background: #0d263f; border: 1px solid rgba(219, 226, 239, .3); border-radius: 6px; outline: none; font-family: inherit; }
    .staff-password-input:focus { border-color: #3F72AF; box-shadow: 0 0 0 3px rgba(63, 114, 175, .3); }
    .staff-password-input::placeholder { color: #DBE2EF; }
    @media (max-width: 760px) {
        .staff-password-form { align-items: stretch; flex-direction: column; }
        .staff-password-wrap { width: 100%; }
    }
</style>

<!-- ═══════ Password toggle script ═══════ -->
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
</script>   