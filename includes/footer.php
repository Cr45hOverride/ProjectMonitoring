<?php
/**
 * KISMEC Intern Task & Project Monitoring System
 * Global Footer Component
 */

declare(strict_types=1);
?>

<footer style="margin-top:4rem;padding:2rem 1.5rem;text-align:center;color:var(--text-dim);font-size:0.8rem;border-top:1px solid rgba(255,255,255,0.06);">
  <p>&copy; <?= date('Y') ?> <strong>KISMEC</strong> (Kedah Industrial Skills and Management Development Centre). All Rights Reserved.</p>
  <p style="margin-top:0.35rem;font-size:0.75rem;opacity:0.7;">Production Environment &bull; Timezone: Asia/Kuala_Lumpur (MYT +08:00)</p>
</footer>

<!-- Flatpickr Core & Dark Plugins -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- Application Master JavaScript -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

</body>
</html>
