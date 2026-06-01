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
                <div style="margin: 20px 0;">
                    <img :src="qrCodeUrl" alt="QR Code" style="border: 1px solid #ccc; padding: 10px; border-radius: 5px; background: #fff;" />
                </div>
                
                <p style="font-size: 12px; color: #888; margin-bottom: 20px;">
                    <?= i::__('Si no puedes escanear el código, ingresa esta clave manualmente:') ?><br>
                    <strong style="letter-spacing: 2px; font-size: 14px; color: #333;">{{ secret }}</strong>
                </p>

                <form class="login__form" @submit.prevent="verifyCode">
                    <div v-if="error" class="alert error" style="margin-bottom: 20px; color: red;">
                        {{ error }}
                    </div>

                    <div v-if="success" class="alert success" style="margin-bottom: 20px; color: green; font-weight: bold;">
                        <?= i::__('¡Configurado exitosamente! Te redirigiremos en breve...') ?>
                    </div>

                    <div class="login__fields" v-if="!success">
                        <div class="field">
                            <input type="text" v-model="code" placeholder="0 0 0 0 0 0" class="form-ctrl" required maxlength="6" autocomplete="off" style="text-align: center; letter-spacing: 5px; font-size: 18px; padding: 10px;" />
                        </div>
                    </div>

                    <div class="login__buttons" v-if="!success">
                        <button class="button button--primary button--large button--md" type="submit" :disabled="loading" style="width: 100%; margin-top: 10px;">
                            {{ loading ? '<?= i::__('Verificando...') ?>' : '<?= i::__('Verificar y Activar') ?>' }}
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
