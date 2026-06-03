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
    $this->import('mfa-verify');
    ?>
    <mfa-verify></mfa-verify>
    <?php
} else {
    // Normal login mode
    $this->import('password-strongness');
    $this->import('login');
    $this->import('mc-card');
    ?>
    <login config='<?= $configs; ?>' ></login>
    <?php
}
?>