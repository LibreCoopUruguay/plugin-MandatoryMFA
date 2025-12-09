<?php
use MapasCulturais\i;
?>
<div class="login">
    <div class="login__action">
        <div class="login__card" style="text-align: center;">
            <div class="login__card__header" style="text-align: center;">
                <h3 style="font-weight: bold; margin-bottom: 10px;"><?= i::__('Verificación de Seguridad') ?></h3>
                <h6 style="color: #666; font-weight: normal; font-size: 14px;"><?= i::__('Ingrese el código de 6 dígitos que enviamos a su correo electrónico') ?></h6>
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
                    </div>

                    <div class="login__buttons">
                        <button class="button button--primary button--large button--md" type="submit" :disabled="loading" style="width: 100%; margin-top: 10px;">
                            {{ loading ? '<?= i::__('Verificando...') ?>' : '<?= i::__('Verificar') ?>' }}
                        </button>
                    </div>

                    <div class="create" style="margin-top: 25px; text-align: center; font-size: 14px;">
                        <span style="color: #666;"><?= i::__('¿No recibiste el código?') ?></span>
                        <a href="#" @click.prevent="resendCode" v-if="!resendSent" class="login__recover-link" style="margin-left: 5px; font-weight: bold;">
                            <?= i::__('Reenviar') ?>
                        </a>
                        <span v-else class="success-message" style="color: green; display: block; margin-top: 5px;">
                            <?= i::__('Código reenviado. Revise su email.') ?>
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
