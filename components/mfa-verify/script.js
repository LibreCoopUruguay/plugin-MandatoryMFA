
(function () {
    function registerMFAComponent() {
        if (typeof app === 'undefined' && typeof window.app === 'undefined') {
            setTimeout(registerMFAComponent, 50);
            return;
        }

        const vueApp = (typeof app !== 'undefined') ? app : window.app;

        // Use unique global variable
        const template = window.MFA_VERIFY_TEMPLATE || $TEMPLATES['mfa-verify'];

        vueApp.component('mfa-verify', {
            template: template,
            data() {
                return {
                    code: '',
                    error: '',
                    loading: false,
                    resendSent: false
                };
            },
            methods: {
                async verifyMFA() {
                    this.error = '';
                    this.loading = true;

                    try {
                        const response = await fetch($MAPAS.baseURL + 'auth/verify_mfa', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'code=' + encodeURIComponent(this.code)
                        });

                        const data = await response.json();

                        if (data.success) {
                            window.location.href = data.redirectTo || $MAPAS.baseURL + 'panel';
                        } else {
                            this.error = data.data || 'Código inválido';
                        }
                    } catch (e) {
                        this.error = 'Error de conexión';
                        console.error(e);
                    } finally {
                        this.loading = false;
                    }
                },

                async resendCode() {
                    try {
                        const response = await fetch($MAPAS.baseURL + 'auth/resend_mfa', { method: 'POST' });
                        this.resendSent = true;
                    } catch (e) {
                        console.error(e);
                    }
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerMFAComponent);
    } else {
        registerMFAComponent();
    }
})();
