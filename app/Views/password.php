<?php
use XuiPanel\Core\Csrf;
$min = (int)config('security.password_min', 6);
$max = (int)config('security.password_max', 14);
?>
<section class="panel password-panel two-col">
    <div>
        <div class="form-notice critical">
            <span>After changing your password, you will be signed out automatically.</span>
            <span>After the update, you must sign in again.</span>
            <span>Your device must be set up again with the new password.</span>
        </div>

        <span class="eyebrow">Security</span>
        <h2>Change Your Password</h2>
        <p class="muted">Enter your new password twice to update your account password.</p>

        <form method="post" action="<?= e(app_url('password')) ?>" class="form stacked" data-loading-form>
            <?= Csrf::field() ?>
            <label>
                <span>New Password</span>
                <input type="password" name="new_password" minlength="<?= e((string)$min) ?>" maxlength="<?= e((string)$max) ?>" pattern="[A-Za-z0-9]+" autocomplete="new-password" required>
            </label>
            <label>
                <span>Confirm New Password</span>
                <input type="password" name="confirm_password" minlength="<?= e((string)$min) ?>" maxlength="<?= e((string)$max) ?>" pattern="[A-Za-z0-9]+" autocomplete="new-password" required>
            </label>
            <div class="form-actions">
                <button class="btn primary" type="submit" data-loading-text="Updating...">Update Password</button>
            </div>
        </form>
    </div>

    <aside class="security-card emphasized">
        <span class="eyebrow">Important Notes</span>
        <h3>Password Security</h3>
        <ul class="security-list">
            <li>Do not share your password with anyone.</li>
            <li>Your new password can contain letters and numbers only.</li>
            <li>Use a unique password that you do not use on other services.</li>
            <li>After changing your password, your device must be set up again with the new password.</li>
            <li>Do not enter your account details on links or files sent by people you do not know.</li>
        </ul>
    </aside>
</section>