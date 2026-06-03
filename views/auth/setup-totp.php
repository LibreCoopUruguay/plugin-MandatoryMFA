<?php
use MapasCulturais\i;
use MapasCulturais\App;

$app = App::i();

// Load the component template and script inline (cannot use $this->import() 
// because MandatoryMFA components may not be auto-discovered by the Theme)
$templatePath = __DIR__ . '/../../components/setup-totp/template.php';
$scriptPath = __DIR__ . '/../../components/setup-totp/script.js';

// Capture template output
ob_start();
include $templatePath;
$templateHtml = ob_get_clean();

// Escape for JS string
$templateJs = json_encode($templateHtml);
?>

<!-- Register setup-totp component template -->
<script>
    if (typeof $TEMPLATES === 'undefined') var $TEMPLATES = {};
    $TEMPLATES['setup-totp'] = <?= $templateJs ?>;
</script>

<!-- Register setup-totp component script -->
<script>
<?php include $scriptPath; ?>
</script>

<!-- Use the component -->
<setup-totp qr-code-url="<?= htmlspecialchars($qrCodeUrl) ?>" secret="<?= htmlspecialchars($secret) ?>"></setup-totp>
