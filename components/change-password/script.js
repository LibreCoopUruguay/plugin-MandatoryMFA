app.component('change-password', {
    template: window.$TEMPLATES && window.$TEMPLATES['change-password'] ? window.$TEMPLATES['change-password'] : '',

            components: {
                VueRecaptcha
            },

    setup() {
        const messages = useMessages();
        const text = Utils.getTexts('change-password')
        return { text, messages }
    },

    data() {
        return {
            passwordRules: {},
            currentPassword: null,
            newPassword: null,
            confirmNewPassword: null,
            mfaEnabled: false,
            mfaMandatory: false
        }
    },

    props: {
        entity: {
            type: Entity,
            required: true
        },
        myAccount: {
            type: Boolean,
            required: false
        },
    },

    mounted() {
        let api = new API();
        api.GET($MAPAS.baseURL + "auth/passwordvalidationinfos").then(async response => response.json().then(validations => {
            this.passwordRules = validations.passwordRules;
        }));

        if (this.myAccount) {
            api.GET($MAPAS.baseURL + "auth/get_mfa_status").then(async response => response.json().then(data => {
                if (data.success) {
                    this.mfaEnabled = data.mfa_enabled;
                    this.mfaMandatory = data.mfa_mandatory || false;
                }
            }));
        }
    },

    methods: {

        async changePassword(modal) {
            let api = new API();
            if (this.myAccount) {
                let data = {
                    'current_password': this.currentPassword,
                    'new_password': this.newPassword,
                    'confirm_new_password': this.confirmNewPassword,
                }
                await api.POST($MAPAS.baseURL + "autenticacao/changepassword", data).then(response => response.json().then(dataReturn => {
                    if (dataReturn.error) {
                        this.throwErrors(dataReturn.data);
                    } else {
                        this.messages.success('Senha alterada com sucesso!');
                        this.cancel(modal);
                    }
                }));
            } else {
                let data = {
                    'new_password': this.newPassword,
                    'confirm_new_password': this.confirmNewPassword,
                    'email': this.entity.email,
                }
                await api.POST($MAPAS.baseURL + "autenticacao/adminchangeuserpassword", data).then(response => response.json().then(dataReturn => {
                    if (dataReturn.error) {
                        this.throwErrors(dataReturn.data);
                    } else {
                        this.messages.success('Senha alterada com sucesso!');
                        this.cancel(modal);
                    }
                }));
            }
        },

        cancel(modal) {
            this.newPassword = '';
            this.confirmNewPassword = '';
            modal.close();
        },

        throwErrors(errors) {
            for (let key in errors) {
                for (let val of errors[key]) {
                    this.messages.error(val);
                }
            }
        },

        async toggleMFA() {
            let api = new API();
            await api.POST($MAPAS.baseURL + "auth/toggle_mfa").then(response => response.json().then(data => {
                if (data.success) {
                    this.mfaEnabled = data.mfa_enabled;
                    let msg = this.mfaEnabled ? 'MFA ativado com sucesso!' : 'MFA desativado com sucesso!';
                    this.messages.success(msg);
                } else {
                    this.messages.error(data.message || 'Erro ao alterar MFA');
                    // Revert visual state if failed
                    this.mfaEnabled = !this.mfaEnabled;
                }
            }));
        },

        togglePassword(id, event) {
            if (document.getElementById(id).type == 'password') {
                event.target.style.background = "url('https://api.iconify.design/carbon/view-off-filled.svg') no-repeat center center / 22.5px"
                document.getElementById(id).type = 'text';
            } else {
                event.target.style.background = "url('https://api.iconify.design/carbon/view-filled.svg') no-repeat center center / 22.5px"
                document.getElementById(id).type = 'password';
            }
        }
    }
});