<?php
/** includes/admin_footer.php – close admin chrome + load JS. */
?>
    </div><!-- /ns-admin-content -->
  </main>
</div><!-- /ns-admin-shell -->

<script>window.NS_ADMIN_BASE=<?= json_encode(app_base()) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/admin.js') ?>"></script>
<?php if (!empty($nsAdminScripts)): foreach ($nsAdminScripts as $s): ?>
<?php $assetFile = __DIR__ . '/../assets/js/admin/' . $s; ?>
<script src="<?= url('assets/js/admin/' . $s) . (is_file($assetFile) ? '?v=' . filemtime($assetFile) : '') ?>"></script>
<?php endforeach; endif; ?>
<?php if (!empty($nsInlineScript)): ?>
<script><?= $nsInlineScript ?></script>
<?php endif; ?>
</body></html>
