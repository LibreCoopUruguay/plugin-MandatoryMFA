<?php

use MapasCulturais\i;
use MapasCulturais\App;

$app = App::i();


$configs = json_encode($config);
if (!$configs) $configs = '{}';

$this->import('password-strongness');
$this->import('mc-breadcrumb');
$this->import('entity-field');
$this->import('entity-terms');
$this->import('mc-card');
$this->import('mc-icon');
$this->import('mc-stepper');
$this->import('create-account');

$this->breadcrumb = [
    ['label'=> i::__('Volver'), 'url' => $app->createUrl('auth')],
];
?>

<mc-breadcrumb></mc-breadcrumb>

<create-account config='<?= htmlspecialchars($configs, ENT_QUOTES, 'UTF-8'); ?>'></create-account>