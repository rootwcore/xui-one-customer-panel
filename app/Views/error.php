<section class="panel narrow">
    <span class="eyebrow">Notice</span>
    <h2><?= e($title ?? 'Error') ?></h2>
    <p class="muted"><?= e($message ?? 'An unexpected error occurred.') ?></p>
    <a class="btn primary" href="<?= e(app_url('dashboard')) ?>">Back to Dashboard</a>
</section>