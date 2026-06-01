<?php
use MapasCulturais\i;
use MapasCulturais\App;
$app = App::i();
?>
<div class="login">
    <div class="login__action">
        <div class="login__card" style="text-align: center;">
            <div class="login__card__header" style="text-align: center;">
                <h3 style="font-weight: bold; margin-bottom: 10px;"><?= i::__('Verificación de Seguridad') ?></h3>
                
                <h6 v-if="mfaMethod === 'email'" style="color: #666; font-weight: normal; font-size: 14px;">
                    <?= i::__('Ingrese el código de 6 dígitos que enviamos a su correo electrónico') ?>
                </h6>
                <h6 v-else style="color: #666; font-weight: normal; font-size: 14px;">
                    <?= i::__('Ingrese el código de 6 dígitos de su aplicación Authenticator') ?>
                </h6>
            </div>
            
            <div class="login__card__content">
                <form class="login__form" @submit.prevent="verifyMFA">
                    
                    <div v-if="error" class="alert error" style="margin-bottom: 20px; color: red;">
                        {{ error }}
                    </div>

                    <div class="login__fields">
                        <div class="field">
                            <label for="code" style="text-align: left; display: block; width: 100%; font-weight: bold; margin-bottom: 5px;"><?= i::__('Código de Verificación') ?></label>
                            <input type="text" id="code" v-model="code" placeholder="0 0 0 0 0 0" class="form-ctrl" required maxlength="6" autocomplete="off" style="text-align: center; letter-spacing: 5px; font-size: 18px; padding: 10px;" />
                        </div>
                        
                        <div class="field" style="margin-top: 15px; text-align: left;">
                            <label style="display: flex; align-items: center; cursor: pointer; font-weight: normal; font-size: 14px; color: #555;">
                                <input type="checkbox" v-model="rememberDevice" style="margin-right: 8px; width: 16px; height: 16px;">
                                <?= i::__('Recordar este dispositivo por 30 días') ?>
                            </label>
                        </div>
                    </div>

                    <div class="login__buttons">
                        <button class="button button--primary button--large button--md" type="submit" :disabled="loading" style="width: 100%; margin-top: 10px;">
                            {{ loading ? '<?= i::__('Verificando...') ?>' : '<?= i::__('Verificar') ?>' }}
                        </button>
                    </div>

                    <div v-if="mfaMethod === 'email'" class="create" style="margin-top: 25px; text-align: center; font-size: 14px;">
                        <span style="color: #666;"><?= i::__('¿No recibiste el código?') ?></span>
                        <a href="#" @click.prevent="resendCode(false)" v-if="!resendSent" class="login__recover-link" style="margin-left: 5px; font-weight: bold;">
                            <?= i::__('Reenviar') ?>
                        </a>
                        <span v-else class="success-message" style="color: green; display: block; margin-top: 5px;">
                            <?= i::__('Código reenviado. Revise su email.') ?>
                        </span>
                        
                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                            <a href="<?= $app->createUrl('auth', 'setup_totp') ?>" style="color: #2b78e4; text-decoration: none; display: flex; align-items: center; justify-content: center;">
                                <span class="icon icon-lock" style="margin-right: 5px;"></span>
                                <?= i::__('Usa Google Authenticator para un inicio de sesión más rápido') ?>
                            </a>
                        </div>
                    </div>
                    
                    <div v-if="mfaMethod === 'totp'" class="create" style="margin-top: 25px; text-align: center; font-size: 14px;">
                        <span style="color: #666;"><?= i::__('¿No tienes acceso a tu aplicación?') ?></span>
                        <a href="#" @click.prevent="resendCode(true)" class="login__recover-link" style="margin-left: 5px; font-weight: bold;">
                            <?= i::__('Enviar código por email') ?>
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
