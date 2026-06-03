app.component('mfa-verify', {
    template: window.MFA_VERIFY_TEMPLATE || $TEMPLATES['mfa-verify'],
            data() {
                const params = new URLSearchParams(window.location.search);
                const method = params.get('method') || 'email';
                return {
                    code: '',
                    error: '',
                    loading: false,
                    resendSent: false,
                    mfaMethod: method,
                    rememberDevice: false
                };
            },
            methods: {
                async verifyMFA() {
                    this.error = '';
                    this.loading = true;

                    try {
                        let bodyParams = new URLSearchParams();
                        bodyParams.append('code', this.code);
                        if (this.rememberDevice) {
                            bodyParams.append('rememberDevice', 'true');
                        }

                        const response = await fetch($MAPAS.baseURL + 'auth/verify_mfa', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: bodyParams
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

                async resendCode(fallback = false) {
                    try {
                        this.error = '';
                        let bodyParams = new URLSearchParams();
                        if (fallback) {
                            bodyParams.append('fallback', '1');
                        }
                        
                        const response = await fetch($MAPAS.baseURL + 'auth/resend_mfa', { 
                            method: 'POST',
                            body: bodyParams
                        });
                        const data = await response.json();
                        
                        if (data.error) {
                            this.error = data.data;
                            return;
                        }

                        if (fallback) {
                            this.mfaMethod = 'email';
                        }
                        this.code = '';
                        this.resendSent = true;
                    } catch (e) {
                        console.error(e);
                    }
                }
            }
});
