<?php
namespace MandatoryMFA;

use MapasCulturais\i;
use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin {
    public function _init() {
        // Registration logic if needed
        $app = App::i();
        i::load_textdomain( 'multipleLocal', __DIR__ . "/../MultipleLocalAuth/translations", i::get_locale() );
        
        // Load LGPD configuration
        $lgpdConfig = include(__DIR__ . '/config/lgpd.php');
        if (is_array($lgpdConfig)) {
            foreach ($lgpdConfig as $key => $value) {
                $app->config[$key] = $value;
            }
        }
        
        // Create explicit path to component
        // $this->registerComponent('mfa-verify');

        // Enqueue Styles (copied from MultipleLocalAuth)
        $app->hook('GET(<<auth|panel>>.<<*>>):before', function() use ($app) {
            $app->view->enqueueStyle('app-v2', 'multipleLocal-v2', 'css/plugin-MultiplLocalAuth.css');
        });
    }

    public function register() {
        // Register User Metadata required for Login
        // Note: We use keys from MultipleLocalAuth namespace/provider to maintain compatibility
        $this->registerUserMetadata(\MultipleLocalAuth\Provider::$passMetaName, ['label' => i::__('Contraseña')]);
        $this->registerUserMetadata(\MultipleLocalAuth\Provider::$recoverTokenMetadata, ['label' => i::__('Token para recuperación de contraseña')]);
        $this->registerUserMetadata(\MultipleLocalAuth\Provider::$recoverTokenTimeMetadata, ['label' => i::__('Timestamp del token para recuperación de contraseña')]);
        $this->registerUserMetadata(\MultipleLocalAuth\Provider::$accountIsActiveMetadata, ['label' => i::__('¿Cuenta activa?')]);
        $this->registerUserMetadata(\MultipleLocalAuth\Provider::$tokenVerifyAccountMetadata, ['label' => i::__('Token de verificación')]);
        $this->registerUserMetadata(\MultipleLocalAuth\Provider::$loginAttempMetadata, ['label' => i::__('Número de tentativas de login')]);
        $this->registerUserMetadata(\MultipleLocalAuth\Provider::$timeBlockedloginAttempMetadata, ['label' => i::__('Tiempo de bloqueio por exceso de tentativas')]);
        
        // MFA Metadata Registration
        $this->registerUserMetadata(\MultipleLocalAuth\Provider::$mfaEnabledMetadata, ['label' => i::__('MFA Habilitado')]);
        $this->registerUserMetadata(\MultipleLocalAuth\Provider::$mfaCodeHashMetadata, ['label' => i::__('Hash Código MFA')]);
        $this->registerUserMetadata(\MultipleLocalAuth\Provider::$mfaCodeExpiresMetadata, ['label' => i::__('Expira Código MFA')]);
        $this->registerUserMetadata('mfa_temp_token', ['label' => i::__('Token Temporal MFA')]);
        $this->registerUserMetadata('mfa_temp_token_expires', ['label' => i::__('Expira Token Temporal MFA')]);        
    }
}
