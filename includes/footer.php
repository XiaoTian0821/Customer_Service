<?php
/** includes/footer.php – close customer-portal chrome + load assets. */
?>
</main>

<footer class="ns-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="ns-footer-brand">
          <span class="ns-brand-mark"><i class="fa-solid fa-bolt-lightning"></i></span>
          <span class="ns-brand-text">Nova<span>Serve</span></span>
        </div>
        <p class="ns-footer-blurb">NovaServe Technologies Sdn. Bhd. — AI-assisted customer care for homes and businesses. Our intelligent assistant works around the clock, backed by our support team.</p>
      </div>
      <div class="col-6 col-lg-3">
        <h6 class="ns-footer-head">Explore</h6>
        <a href="<?= url('index.php') ?>">Support Home</a>
        <a href="<?= url('faq.php') ?>">FAQ</a>
        <a href="<?= url('knowledge.php') ?>">Knowledge Base</a>
        <a href="<?= url('ticket.php') ?>">Support Tickets</a>
      </div>
      <div class="col-6 col-lg-4">
        <h6 class="ns-footer-head">Contact</h6>
        <div class="ns-footer-contact">
          <div><i class="fa-solid fa-phone"></i> +60 3 8000 0000</div>
          <div><i class="fa-regular fa-envelope"></i> care@novaserve.example</div>
          <div><i class="fa-solid fa-location-dot"></i> Level 28, Nova Towers, KL</div>
          <div><i class="fa-regular fa-clock"></i> AI: 24/7 · Office: Mon–Fri 8:30–6:30</div>
        </div>
      </div>
    </div>
    <div class="ns-footer-bottom">
      <span>© <?= date('Y') ?> NovaServe Technologies Sdn. Bhd. All rights reserved.</span>
      <span class="ns-footer-legal">Privacy · Terms · Refund Policy</span>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.NS_BASE=<?= json_encode(app_base() . '/') ?>;</script>
<script src="<?= url('assets/js/common.js') ?>"></script>
<?php if (!empty($nsScripts)): foreach ($nsScripts as $s): ?>
<?php $assetFile = __DIR__ . '/../assets/js/' . $s; ?>
<script src="<?= url('assets/js/' . $s) . (is_file($assetFile) ? '?v=' . filemtime($assetFile) : '') ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
