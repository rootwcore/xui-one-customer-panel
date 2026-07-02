<?php
use XuiPanel\Core\Csrf;
$activeLookup = array_flip(array_map('intval', $active));
?>
<section class="panel">
    <div class="panel-head">
        <div>
            <span class="eyebrow">Bouquets</span>
            <h2>Select the channel groups you want to watch</h2>
            <p class="muted">After saving your changes, refresh the channel list on your device.</p>
        </div>
        <div class="badge">Total <?= e((string)count($allowed)) ?> Bouquets</div>
    </div>

    <?php if ($allowed === []): ?>
        <div class="empty-state">
            <strong>Your bouquet list cannot be displayed right now.</strong>
            <p>The channel groups assigned to your account could not be loaded. Please try again later.</p>
        </div>
    <?php else: ?>
        <form method="post" action="<?= e(app_url('bouquets')) ?>" data-loading-form>
            <?= Csrf::field() ?>
            <div class="bouquet-grid">
                <?php foreach ($allowed as $id): ?>
                    <?php $checked = isset($activeLookup[(int)$id]); ?>
                    <label class="bouquet-card <?= $checked ? 'enabled' : '' ?>">
                        <input type="checkbox" name="bouquets[]" value="<?= e((string)$id) ?>" <?= $checked ? 'checked' : '' ?> data-bouquet-toggle>
                        <span class="switch" aria-hidden="true"><i></i></span>
                        <span class="bouquet-title"><?= e($bouquetMap[(int)$id] ?? ('Bouquet #' . $id)) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="form-actions sticky-actions">
                <button class="btn primary" type="submit" data-loading-text="Saving...">Save Changes</button>
                <a class="btn secondary" href="<?= e(app_url('dashboard')) ?>">Back to Dashboard</a>
            </div>
        </form>
    <?php endif; ?>
</section>