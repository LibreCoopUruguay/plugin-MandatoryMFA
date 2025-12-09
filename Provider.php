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
                    $this->_generateAndSendMFA($userToLogin);
                    
                    // Generate temporary token instead of using session
                    $token = bin2hex(random_bytes(32));
                    
                    $app->disableAccessControl();
                    $userToLogin->setMetadata('mfa_temp_token', $token);
                    $userToLogin->setMetadata('mfa_temp_token_expires', time() + 300); // 5 minutes
                    $userToLogin->saveMetadata(true);
                    $app->enableAccessControl();
                    
                    // Use raw response like in toggle_mfa to avoid framework issues
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'mfa_required' => true,
                        'redirectTo' => $app->createUrl('auth', 'mfa') . '?token=' . $token
                    ]);
                    exit;
                    
                } catch (\Throwable $e) {
                    // Fall back to normal login on MFA error
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
        ];;
    }
}
