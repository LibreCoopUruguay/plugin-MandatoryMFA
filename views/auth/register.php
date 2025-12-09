<?php

use MapasCulturais\i;
use MapasCulturais\App;

$app = App::i();


$configs = json_encode($config);
if (!$configs) $configs = '{}';

// Manual load of create-account component
ob_start();
require __DIR__ . '/../../components/create-account/template.php'; 
$createAccountTemplate = ob_get_clean();

$createAccountScript = file_get_contents(__DIR__ . '/../../components/create-account/script.js');

$this->breadcrumb = [
    ['label'=> i::__('Volver'), 'url' => $app->createUrl('auth')],
];
?>

<mc-breadcrumb></mc-breadcrumb>

<script>
    if (typeof $TEMPLATES === 'undefined') var $TEMPLATES = {};
    window.CREATE_ACCOUNT_TEMPLATE = <?php echo json_encode($createAccountTemplate); ?>;
    $TEMPLATES['create-account'] = window.CREATE_ACCOUNT_TEMPLATE;
    
    <?php echo $createAccountScript; ?>
</script>

<create-account config='<?= htmlspecialchars($configs, ENT_QUOTES, 'UTF-8'); ?>'></create-account>