<?php
use MapasCulturais\i;
use MapasCulturais\App;

$app = App::i();
?>

<div class="login">
    <div class="login__action">
        <div class="login__card" style="text-align: center; max-width: 400px; margin: 0 auto;">
            <div class="login__card__header" style="text-align: center;">
                <h3 style="font-weight: bold; margin-bottom: 10px;"><?= i::__('Configurar Authenticator') ?></h3>
                <h6 style="color: #666; font-weight: normal; font-size: 14px;">
                    <?= i::__('Escanea este código QR con Google Authenticator, Authy, o tu aplicación de preferencia.') ?>
                </h6>
            </div>
            
            <div class="login__card__content">
                <?php if (!empty($error)): ?>
                    <div class="alert error" style="margin-bottom: 20px; color: #c0392b; background: #fce4e4; border: 1px solid #e0b0b0; padding: 10px; border-radius: 5px;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div style="margin: 20px 0;">
                    <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="QR Code" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px; background: #fff;" />
                </div>
                
                <p style="font-size: 12px; color: #888; margin-bottom: 20px;">
                    <?= i::__('Si no puedes escanear el código, ingresa esta clave manualmente:') ?><br>
                    <strong style="letter-spacing: 2px; font-size: 14px; color: #333;"><?= htmlspecialchars($secret) ?></strong>
                </p>

                <form class="login__form" method="POST" action="<?= $app->createUrl('auth', 'setup_totp') ?>">
                    <div class="login__fields">
                        <div class="field">
                            <input type="text" name="code" placeholder="0 0 0 0 0 0" class="form-ctrl" required maxlength="6" autocomplete="off" style="text-align: center; letter-spacing: 5px; font-size: 18px; padding: 10px;" />
                        </div>
                    </div>

                    <div class="login__buttons">
                        <button class="button button--primary button--large button--md" type="submit" style="width: 100%; margin-top: 10px;">
                            <?= i::__('Verificar y Activar') ?>
                        </button>
                    </div>
                </form>
                
                <div style="margin-top: 15px;">
                    <a href="<?= $app->createUrl('panel', 'index') ?>" style="color: #888; font-size: 14px; text-decoration: none;">
                        <?= i::__('Cancelar y volver al Panel') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
