<?php
use MapasCulturais\i;
use MapasCulturais\App;

$app = App::i();

$this->import('setup-totp');
?>

<setup-totp qr-code-url="<?= htmlspecialchars($qrCodeUrl) ?>" secret="<?= htmlspecialchars($secret) ?>"></setup-totp>
