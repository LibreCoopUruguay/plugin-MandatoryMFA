<?php

use MapasCulturais\i;
use MapasCulturais\App;

$app = App::i();

$config = isset($config) ? $config : [];
$configs = json_encode($config);
if (!$configs) $configs = '{}';
echo "<!-- DEBUG: Configs initialized. JSON len: " . strlen($configs) . " -->";

// Check if we're in MFA mode
$mode = $mode ?? 'login';

if (trim($_GET['t'] ?? '')) {
    $this->jsObject['recoveryMode']['status'] = true;
    $this->jsObject['recoveryMode']['token'] = $_GET['t']; 
}

if ($mode === 'mfa') {
    // MFA verification mode
    // MFA verification mode - Manual Load
    ob_start();
    require __DIR__ . '/../../components/mfa-verify/template.php'; 
    $mfaTemplate = ob_get_clean();
    $mfaScript = file_get_contents(__DIR__ . '/../../components/mfa-verify/script.js');
    ?>
    <script>
        if (typeof $TEMPLATES === 'undefined') var $TEMPLATES = {};
        
        // Use unique global to avoid scope issues
        window.MFA_VERIFY_TEMPLATE = <?php echo json_encode($mfaTemplate); ?>;
        $TEMPLATES['mfa-verify'] = window.MFA_VERIFY_TEMPLATE;
        
        <?php echo $mfaScript; ?>
    </script>
    <mfa-verify></mfa-verify>
    <?php
} else {
    // Normal login mode
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Manual load of login component (Isolated in MandatoryMFA)
    ob_start();
    require __DIR__ . '/../../components/login/template.php'; 
    $loginTemplate = ob_get_clean();
    
    $loginScript = file_get_contents(__DIR__ . '/../../components/login/script.js');
    ?>
    <script>
        if (typeof $TEMPLATES === 'undefined') var $TEMPLATES = {};
        $TEMPLATES['login'] = <?php echo json_encode($loginTemplate); ?>;
        // Backup mechanism: Explicit unique global
        window.MFA_LOGIN_TEMPLATE = $TEMPLATES['login'];
        
        <?php echo $loginScript; ?>
    </script>
    <login config='<?= htmlspecialchars($configs, ENT_QUOTES, 'UTF-8'); ?>' ></login>
    <?php
}
?>