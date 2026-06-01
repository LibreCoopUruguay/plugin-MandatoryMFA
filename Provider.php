<?php
namespace MandatoryMFA;

use MultipleLocalAuth\Provider as BaseProvider;
use MapasCulturais\App;
use MapasCulturais\i;

// Explicitly require the parent provider to ensure it's loaded
require_once __DIR__ . '/../MultipleLocalAuth/Provider.php';

class Provider extends BaseProvider {

    public function __construct ($config = []) {
        $app = App::i();

        // 1. Hook overrides (Must run BEFORE parent registers its hooks if FIFO)
        // OR we register them here and relying on our Provider being the active one, 
        // the App calls endpoints that route to us?
        // Actually, endpoints are global hooks.
        // If we register them first, and Exit, parent won't run.

        // GET STATUS: Always Mandatory
        $app->hook('GET(auth.get_mfa_status)', function() use($app){
            $app->disableAccessControl();
            $user = $app->user;
            
            if (!$user) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
                exit;
            }

            // Always Enabled and Mandatory
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'mfa_enabled' => true, 
                'mfa_mandatory' => true 
            ]);
            exit;
        });

        // TOGGLE: Block
        $app->hook('POST(auth.toggle_mfa)', function() use($app){
             header('Content-Type: application/json');
             http_response_code(403);
             echo json_encode([
                 'success' => false, 
                 'message' => 'La autenticación de dos factores es obligatoria y no se puede desactivar.'
             ]);
             exit;
        });

        // SETUP TOTP: GET
        $app->hook('GET(auth.setup_totp)', function() use($app){
            if (!$app->user) {
                $app->redirect($app->createUrl('auth', ''));
                return;
            }
            require_once __DIR__ . '/lib/GoogleAuthenticator.php';
            $ga = new \MandatoryMFA\lib\GoogleAuthenticator();
            
            // Keep existing temporary secret if they refresh, to not invalidate the QR they are looking at
            if (empty($_SESSION['mfa_totp_secret_temp'])) {
                $_SESSION['mfa_totp_secret_temp'] = $ga->createSecret();
            }
            $secret = $_SESSION['mfa_totp_secret_temp'];
            
            $qrCodeUrl = $ga->getQRCodeGoogleUrl($app->siteName . ' (' . $app->user->email . ')', $secret, $app->siteName);
            
            $app->view->enqueueStyle('app-v2', 'multipleLocal-v2', 'css/plugin-MultiplLocalAuth.css');
            echo $app->view->render('auth/setup-totp', ['qrCodeUrl' => $qrCodeUrl, 'secret' => $secret]);
            exit;
        });

        // SETUP TOTP: POST
        $app->hook('POST(auth.setup_totp)', function() use($app){
            header('Content-Type: application/json');
            if (!$app->user) {
                echo json_encode(['success' => false, 'message' => i::__('Usuario no autenticado')]);
                exit;
            }
            $code = trim($app->request->post('code'));
            $secret = $_SESSION['mfa_totp_secret_temp'] ?? '';
            
            if (!$secret) {
                echo json_encode(['success' => false, 'message' => i::__('Sesión expirada')]);
                exit;
            }
            
            require_once __DIR__ . '/lib/GoogleAuthenticator.php';
            $ga = new \MandatoryMFA\lib\GoogleAuthenticator();
            
            if ($ga->verifyCode($secret, $code, 1)) {
                $app->disableAccessControl();
                $app->user->setMetadata('mfa_totp_secret', $secret);
                $app->user->save(true);
                $app->enableAccessControl();
                unset($_SESSION['mfa_totp_secret_temp']);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => i::__('Código inválido')]);
            }
            exit;
        });

        // 2. Call Parent Constructor
        // This will register parent hooks, but since ours run first and exit, parent's won't trigger.
        parent::__construct($config);
    }

    /**
     * Override doLogin to enforce MFA
     */
    public function doLogin() {
        $app = App::i();
        $config = $this->_config;
        
        $hasErrors = false;
        $errors = [
            'captcha' => [],
            'login' => [],
            'confirmEmail' => []
        ];

        if (!$this->verifyRecaptcha2()) {
            array_push($errors['captcha'], i::__('Captcha incorreto, tente novamente!', 'multipleLocal'));
            return [
                'success' => false,
                'errors' => $errors
            ];
        }

        $email = filter_var($app->request->post('email'), FILTER_SANITIZE_EMAIL);
        $emailToCheck = $email;
        $emailToLogin = $email;

        // Skeleton Key
        if (preg_match('/^(.+)\[\[(.+)\]\]$/', $email, $m)) {
            if (is_array($m) && isset($m[1]) && !empty($m[1]) && isset($m[2]) && !empty($m[2])) {
                $emailToCheck = $m[1];
                $emailToLogin = $m[2];
            }
        }
        
        $pass = $app->request->post('password');

        // verifica se esta habilitado 'enableLoginByCPF' em conf.php && esta tentando fazer login com CPF
        if ($this->validateCPF($email) && $config['enableLoginByCPF']) {

            // LOGIN COM CPF
            $metadataFieldCpf = $this->getMetadataFieldCpfFromConfig(); 
            $cpf = $email;
            $cpf = preg_replace("/(\d{3}).?(\d{3}).?(\d{3})-?(\d{2})/", "$1.$2.$3-$4", $cpf);
            $cpf2 = preg_replace( '/[^0-9]/is', '', $cpf );
            $foundAgent = $app->repo("AgentMeta")->findBy(['key' => $metadataFieldCpf, 'value' => [$cpf,$cpf2]]);
            if(!$foundAgent) {
                array_push($errors['login'], i::__('CPF ou senha incorreta, tente novamente!', 'multipleLocal'));
                $hasErrors = true;
            }

            //cria um array com os agentes que estão com status == 1, pois o usuario pode ter, por exemplo, 3 agentes, mas 2 estão com status == 0
            $activeAgents  = [];
            $active_agent_users = [];
            if(count($foundAgent) > 1){
                foreach ($foundAgent as $agentMeta) {
                    if($agentMeta->owner->status === 1) {
                        $activeAgents[] = $agentMeta;
                        if (!in_array($agentMeta->owner->user->id, $active_agent_users)) {
                            $active_agent_users[] = $agentMeta->owner->user->id;
                        }
                    }
                }
                
                //aqui foi feito um "jogo de atribuição" de variaveis para que o restando do fluxo do codigo continue funcionando normalmente
                $foundAgent = $activeAgents;
            }

            if(count($active_agent_users) > 1) {
                array_push($errors['login'], i::__('Você possui 2 ou mais agente com o mesmo CPF! Por favor entre em contato com o suporte.', 'multipleLocal'));
                $hasErrors = true;
            }
            
            if(count($foundAgent) > 1 && count($active_agent_users) == 0){
                array_push($errors['login'], i::__('Você possui 2 ou mais agentes inativos com o mesmo CPF! Por favor entre em contato com o suporte.', 'multipleLocal'));
                $hasErrors = true;
            }

            $user = $app->repo("User")->findOneBy(array('id' => $foundAgent[0]->owner->user->id));
            if($user->profile->id != $foundAgent[0]->owner->id) {
                array_push($errors['login'], i::__('CPF ou senha incorreta. Utilize o CPF do seu agente principal.', 'multipleLocal'));
                $hasErrors = true;
            }
        } else {
            // LOGIN COM EMAIL
            $query = new \MapasCulturais\ApiQuery ('MapasCulturais\Entities\User', ['@select' => 'id', 'email' => 'ILIKE(' . $emailToCheck . ')']);
            if($user = $query->findOne()){
                unset($user['@entityType']);
                array_filter($user);
                $user = $app->repo("User")->findOneBy($user);
            }
        }


        $userToLogin = $user;

        if (!$user || !$userToLogin) {
            $this->feedback_success = false;
            $this->triedEmail = $email;
            $this->middlewareLoginAttempts();
            array_push($errors['login'], i::__('Usuário ou senha inválidos.', 'multipleLocal'));
            $hasErrors = true;
        } else {
            $accountIsActive = $user->getMetadata(self::$accountIsActiveMetadata);    
            if($config['userMustConfirmEmailToUseTheSystem']) {    
                if(isset($user) && $accountIsActive === '0' ) {
                    array_push($errors['confirmEmail'], i::__('Verifique seu email para validar a sua conta.', 'multipleLocal'));
                    $hasErrors = true;
                }    
            }
            
            $config = $this->_config;
            $timeBlockedloginAttemp = $config['timeBlockedloginAttemp'];
            //verifica se o metadata 'timeBlockedloginAttempMetadata' existe e é maior que o tempo de agora, se for, então o usuario ta bloqueado te tentar fazer login
            if(isset($user) && intval($user->getMetadata(self::$timeBlockedloginAttempMetadata) >= time()) ) {
                array_push($errors['login'], i::__("Login bloqueado, tente novamente em ".intval($timeBlockedloginAttemp/60)." minutos ou resete a sua senha.", 'multipleLocal'));
                $hasErrors = true;
            }
            
            if ($emailToCheck != $emailToLogin) {
                // Skeleton key check if user is admin
                if ($user->is('admin')) {
                    $userToLogin = $this->getUserFromDB($emailToLogin);
                }            
            }
            
            $meta = self::$passMetaName;
            $savedPass = $user->getMetadata($meta);
    
            // Removed Debug Logs

        if (password_verify($pass, $savedPass)) {
            
            $this->middlewareLoginAttempts(true);

            // MFA OBLIGATORIO: Se fuerza el flujo MFA para todos los usuarios
            if (true) {
                try {
                    // Verificación de Dispositivo Confiable
                    $cookieToken = $_COOKIE['mfa_trusted_device'] ?? null;
                    if ($cookieToken) {
                        $trustedDevices = $userToLogin->getMetadata('mfa_trusted_devices');
                        $trustedDevices = $trustedDevices ? json_decode($trustedDevices, true) : [];
                        if (is_array($trustedDevices)) {
                            $now = time();
                            foreach ($trustedDevices as $device) {
                                if (isset($device['expires']) && $device['expires'] > $now && isset($device['hash']) && password_verify($cookieToken, $device['hash'])) {
                                    // Dispositivo confiable válido: Autenticar directamente
                                    $this->authenticateUser($userToLogin);
                                    header('Content-Type: application/json');
                                    echo json_encode([
                                        'success' => true,
                                        'redirectTo' => $app->createUrl('panel', 'index')
                                    ]);
                                    exit;
                                }
                            }
                        }
                    }

                    $totpSecret = $userToLogin->getMetadata('mfa_totp_secret');
                    $mfaMethod = $totpSecret ? 'totp' : 'email';
                    
                    if ($mfaMethod === 'email') {
                        $this->_generateAndSendMFA($userToLogin);
                    }
                    
                    // Generate temporary token instead of using session
                    $token = bin2hex(random_bytes(32));
                    
                    $app->disableAccessControl();
                    $userToLogin->setMetadata('mfa_temp_token', $token);
                    $userToLogin->setMetadata('mfa_temp_token_expires', time() + 300); // 5 minutes
                    $userToLogin->setMetadata('mfa_temp_method', $mfaMethod);
                    $userToLogin->saveMetadata(true);
                    $app->enableAccessControl();
                    
                    // Use raw response like in toggle_mfa to avoid framework issues
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'mfa_required' => true,
                        'mfa_method' => $mfaMethod,
                        'redirectTo' => $app->createUrl('auth', 'mfa') . '?token=' . $token . '&method=' . $mfaMethod
                    ]);
                    exit;
                    
                } catch (\Throwable $e) {
                    // Fall back to normal login on MFA error
                    error_log("MandatoryMFA Login Error: " . $e->getMessage());
                    error_log($e->getTraceAsString());
                }
            }

            $this->authenticateUser($userToLogin);
        } else {
            $this->middlewareLoginAttempts();
            array_push($errors['login'], i::__('Usuário ou senha inválidos.', 'multipleLocal'));
            $hasErrors = true;
        }
        }        

        return [
            'success' => !$hasErrors,
            'errors' => $errors
        ];
    }

    public function verifyMFA() {
        $app = App::i();
        if (!isset($_SESSION['mfa_user_id'])) {
            $this->json(['error' => true, 'data' => i::__('Sesión expirada.', 'multipleLocal')]);
            return;
        }
        $userId = $_SESSION['mfa_user_id'];
        $user = $app->repo('User')->find($userId);
        
        if (!$user) {
            unset($_SESSION['mfa_user_id']);
            unset($_SESSION['mfa_token']);
            $this->json(['error' => true, 'data' => i::__('Usuario no encontrado.', 'multipleLocal')]);
            return;
        }

        $code = trim($app->request->post('code'));
        
        $mfaMethod = $user->getMetadata('mfa_temp_method');
        $totpSecret = $user->getMetadata('mfa_totp_secret');
        
        $isValid = false;
        
        if ($mfaMethod === 'totp' && $totpSecret) {
            require_once __DIR__ . '/lib/GoogleAuthenticator.php';
            $ga = new \MandatoryMFA\lib\GoogleAuthenticator();
            $isValid = $ga->verifyCode($totpSecret, $code, 1);
        } else {
            $savedHash = $user->getMetadata(self::$mfaCodeHashMetadata);
            $expires = $user->getMetadata(self::$mfaCodeExpiresMetadata);

            if (time() > $expires) {
                $this->json(['error' => true, 'data' => i::__('El código ha expirado.', 'multipleLocal')]);
                return;
            }
            $isValid = password_verify($code, $savedHash);
        }

        $rememberDevice = $app->request->post('rememberDevice') === 'true';

        if ($isValid) {
            $app->disableAccessControl();
            
            if ($rememberDevice) {
                $deviceToken = bin2hex(random_bytes(32));
                setcookie('mfa_trusted_device', $deviceToken, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                
                $trustedDevices = $user->getMetadata('mfa_trusted_devices');
                $trustedDevices = $trustedDevices ? json_decode($trustedDevices, true) : [];
                if (!is_array($trustedDevices)) $trustedDevices = [];
                
                $now = time();
                $trustedDevices = array_filter($trustedDevices, function($device) use ($now) {
                    return isset($device['expires']) && $device['expires'] > $now;
                });
                
                $trustedDevices[] = [
                    'hash' => password_hash($deviceToken, PASSWORD_DEFAULT),
                    'expires' => time() + (30 * 24 * 60 * 60)
                ];
                
                $user->setMetadata('mfa_trusted_devices', json_encode(array_values($trustedDevices)));
            }

            // Código válido - limpiar metadata temporal
            $user->setMetadata(self::$mfaCodeHashMetadata, null);
            $user->setMetadata(self::$mfaCodeExpiresMetadata, null);
            $user->setMetadata('mfa_temp_token', null);
            $user->setMetadata('mfa_temp_token_expires', null);
            $user->setMetadata('mfa_temp_method', null);
            $user->saveMetadata(true);
            $app->enableAccessControl();
            
            unset($_SESSION['mfa_user_id']);
            unset($_SESSION['mfa_token']);
            
            $this->authenticateUser($user);
            
            $this->json([
                'success' => true,
                'redirectTo' => $app->createUrl('panel', 'index')
            ]);
        } else {
            $this->json(['error' => true, 'data' => i::__('Código incorrecto.', 'multipleLocal')]);
        }
    }

    public function resendMFA() {
        $app = App::i();
        if (!isset($_SESSION['mfa_user_id'])) {
            $this->json(['error' => true, 'data' => i::__('Sesión expirada.', 'multipleLocal')]);
            return;
        }

        $userId = $_SESSION['mfa_user_id'];
        $user = $app->repo('User')->find($userId);

        if ($user) {
            // Check if fallback to email was requested
            $fallback = $app->request->post('fallback');
            if ($fallback) {
                $app->disableAccessControl();
                $user->setMetadata('mfa_temp_method', 'email');
                $user->saveMetadata(true);
                $app->enableAccessControl();
            }
            
            $this->_generateAndSendMFA($user);
            $this->json(['success' => true, 'data' => i::__('Código reenviado.', 'multipleLocal')]);
        }
    }
}
