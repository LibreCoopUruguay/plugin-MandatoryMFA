(function () {
    function registerComponent() {
        if (typeof app === 'undefined' && typeof window.app === 'undefined') {
            setTimeout(registerComponent, 50);
            return;
        }

        const vueApp = (typeof app !== 'undefined') ? app : window.app;

        // Explicitly get template from global scope - prefer unique variable
        const templateContent = (window.MFA_LOGIN_TEMPLATE) ? window.MFA_LOGIN_TEMPLATE : ((window.$TEMPLATES && window.$TEMPLATES['login']) ? window.$TEMPLATES['login'] : null);

        if (!templateContent) {
            console.error('MFA Login Template not found');
        }

        vueApp.component('login', {
            template: templateContent,

            components: {
                VueRecaptcha
            },

            setup() {
                const text = Utils.getTexts('login');
                return { text };
            },

            data() {
                return {
                    configs: { strategies: {} }, // Initialize with safe default
                    email: '',
                    password: '',
                    confirmPassword: '',
                    recaptchaResponse: '',

                    passwordRules: {},

                    recoveryRequest: false,
                    recoveryEmailSent: false,

                    recoveryMode: $MAPAS.recoveryMode?.status ?? '',
                    recoveryToken: $MAPAS.recoveryMode?.token ?? '',
                }
            },

            props: {
                config: {
                    type: String,
                    required: true
                }
            },

            created() {
                // Robust config parsing on creation
                let conf = {};
                try {
                    if (this.config) {
                        conf = JSON.parse(this.config);
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                }

                // Merge parsed config into data
                if (conf) {
                    this.configs = conf;
                }

                // Ensure structure exists
                this.configs = this.configs || {};
                this.configs.strategies = this.configs.strategies || {};
            },

            mounted() {
                let api = new API();
                api.GET($MAPAS.baseURL + "auth/passwordvalidationinfos").then(async response => response.json().then(validations => {
                    this.passwordRules = validations.passwordRules;
                }));
            },

            computed: {
                multiple() {
                    return (
                        this.configs &&
                        this.configs.strategies &&
                        this.configs.strategies.Google &&
                        this.configs.strategies.Google.visible &&
                        this.configs.strategies.govbr &&
                        this.configs.strategies.govbr.visible
                    );
                }
            },

            methods: {

                /* Do login */
                async doLogin() {
                    let api = new API();

                    let dataPost = {
                        'email': this.email,
                        'password': this.password,
                        'g-recaptcha-response': this.recaptchaResponse
                    }

                    await api.POST($MAPAS.baseURL + "autenticacao/login", dataPost).then(response => response.json().then(dataReturn => {

                        if (dataReturn.error) {
                            this.throwErrors(dataReturn.data);
                        } else {
                            // Check if MFA is required
                            if (dataReturn.isMFA) {
                                // Redirect to MFA verification page
                                window.location.href = dataReturn.redirectTo || $MAPAS.baseURL + 'autenticacao/mfa';
                            } else if (dataReturn.redirectTo) {
                                window.location.href = dataReturn.redirectTo;
                            } else {
                                console.log('[MFA DEBUG] Default redirect to panel');
                                window.location.href = Utils.createUrl('panel', 'index');
                            }
                        }
                    }));
                },

                /* Request password recover */
                async requestRecover() {
                    let api = new API();

                    let dataPost = {
                        'email': this.email,
                        'g-recaptcha-response': this.recaptchaResponse
                    }

                    await api.POST($MAPAS.baseURL + "autenticacao/recover", dataPost).then(response => response.json().then(dataReturn => {
                        if (dataReturn.error) {
                            this.throwErrors(dataReturn.data);
                        } else {
                            this.recoveryEmailSent = true;
                        }
                    }));
                },

                async doRecover() {
                    let api = new API();

                    let dataPost = {
                        'password': this.password,
                        'confirm_password': this.confirmPassword,
                        'token': this.recoveryToken
                    }

                    await api.POST($MAPAS.baseURL + "autenticacao/dorecover", dataPost).then(response => response.json().then(dataReturn => {
                        if (dataReturn.error) {
                            this.throwErrors(dataReturn.data);
                        } else {
                            const messages = useMessages();
                            messages.success('Senha alterada com sucesso!');
                            setTimeout(() => {
                                window.location.href = $MAPAS.baseURL + 'autenticacao';
                            }, "1000")
                        }
                    }));
                },

                /* Validações */
                async verifyCaptcha(response) {
                    this.recaptchaResponse = response;
                },

                expiredCaptcha() {
                    this.recaptchaResponse = '';
                },

                throwErrors(errors) {
                    const messages = useMessages();

                    if (this.recaptchaResponse !== '') {
                        grecaptcha.reset();
                        this.expiredCaptcha();
                    }

                    for (let key in errors) {
                        for (let val of errors[key]) {
                            messages.error(val);
                        }
                    }
                },

                togglePassword(id, event) {
                    if (document.getElementById(id).type == 'password') {
                        event.target.style.background = "url('https://api.iconify.design/carbon/view-off-filled.svg') no-repeat center center / 22.5px"
                        document.getElementById(id).type = 'text';
                    } else {
                        event.target.style.background = "url('https://api.iconify.design/carbon/view-filled.svg') no-repeat center center / 22.5px"
                        document.getElementById(id).type = 'password';
                    }
                },
            },
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerComponent);
    } else {
        registerComponent();
    }
})();
