<?php
/**
 * Global Footer Component
 */
?>
<footer class="bg-white border-top py-3 mt-auto">
    <div class="container-fluid px-4 d-flex flex-column flex-md-row align-items-center justify-content-between text-muted small">
        <div>
            &copy; <?= date('Y') ?> <strong><?= htmlspecialchars(get_system_config('library_name', APP_NAME)) ?></strong>. All rights reserved.
        </div>
        <div class="d-flex align-items-center gap-3 mt-2 mt-md-0">
            <span><i class="bi bi-shield-check text-success"></i> Encrypted & Verified</span>
            <span>&bull;</span>
            <span><i class="bi bi-cloud-check text-warning"></i> Cloudflare Vault Synced</span>
            <span>&bull;</span>
            <span><i class="bi bi-key text-primary"></i> 2-Step OTP Active</span>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Application Utilities JS -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>

