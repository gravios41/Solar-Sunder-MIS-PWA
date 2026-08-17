<?php
// includes/footer.php
?>
    </div><!-- /.page-body -->

    <footer class="app-footer">
        <p>
            <strong style="color:var(--solar-orange)">Sunder Solar MIS</strong> &nbsp;•&nbsp;
            Logged in as <?php echo ucwords(str_replace('_', ' ', $_SESSION['user_role'] ?? 'Guest')); ?> &nbsp;•&nbsp;
            &copy; <?php echo date('Y'); ?> All rights reserved
        </p>
    </footer>

</main><!-- /.main-content -->

<!-- Global Confirm Modal -->
<div id="gmConfirm" class="gmModal">
    <div class="gmModal-box gmModal-sm">
        <div class="gmModal-icon danger" id="gmConfirmIcon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="gmModal-title" id="gmConfirmTitle">Are you sure?</div>
        <p class="gmModal-msg" id="gmConfirmMsg"></p>
        <div class="gmModal-actions">
            <button class="btn btn-secondary" id="gmConfirmCancel">Cancel</button>
            <button class="btn btn-danger" id="gmConfirmOk">Delete</button>
        </div>
    </div>
</div>

<!-- Global Detail/View Modal -->
<div id="gmDetail" class="gmModal">
    <div class="gmModal-box">
        <div class="gmDetail-header">
            <h3 id="gmDetailTitle">Details</h3>
            <button class="modal-close" id="gmDetailClose">&times;</button>
        </div>
        <div class="gmDetail-rows" id="gmDetailRows"></div>
        <div class="gmModal-actions">
            <button class="btn btn-primary" id="gmDetailCloseBtn">Close</button>
        </div>
    </div>
</div>

<!-- Global Prompt Modal -->
<div id="gmPrompt" class="gmModal">
    <div class="gmModal-box gmModal-sm">
        <div class="gmModal-icon" id="gmPromptIcon">
            <i class="fas fa-sliders-h"></i>
        </div>
        <div class="gmModal-title" id="gmPromptTitle">Update Value</div>
        <p class="gmModal-msg" id="gmPromptMsg"></p>
        <input type="number" id="gmPromptInput" class="form-control" min="0" max="100" step="1">
        <div class="gmModal-actions" style="margin-top:20px;">
            <button class="btn btn-secondary" id="gmPromptCancel">Cancel</button>
            <button class="btn btn-primary" id="gmPromptOk">Update</button>
        </div>
    </div>
</div>

<!-- Core JS -->
<?php $footerBasePath = $appBasePath ?? (defined('APP_BASE_PATH') ? APP_BASE_PATH : '/'); ?>
<script src="<?php echo htmlspecialchars($footerBasePath); ?>assets/js/sidebar.js"></script>
<script src="<?php echo htmlspecialchars($footerBasePath); ?>assets/js/main.js"></script>
<script src="<?php echo htmlspecialchars($footerBasePath); ?>assets/js/pwa.js"></script>
<script src="<?php echo htmlspecialchars($footerBasePath); ?>assets/js/ajax-loader.js"></script>
<script src="<?php echo htmlspecialchars($footerBasePath); ?>assets/js/dashboard-mobile.js"></script>

<?php if (isset($_SESSION['toast'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showToast(<?php echo json_encode($_SESSION['toast']['message']); ?>, <?php echo json_encode($_SESSION['toast']['type']); ?>);
});
</script>
<?php unset($_SESSION['toast']); endif; ?>

</body>
</html>
