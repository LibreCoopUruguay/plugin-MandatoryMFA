app.component('setup-totp', {
    template: window.SETUP_TOTP_TEMPLATE || $TEMPLATES['setup-totp'],
            props: {
                qrCodeUrl: {
                    type: String,
                    required: true
                },
                secret: {
                    type: String,
                    required: true
                }
            },
            data() {
                return {
                    code: '',
                    error: '',
                    success: false,
                    loading: false
                };
            },
            methods: {
                async verifyCode() {
                    if (this.code.length !== 6) {
                        this.error = 'El código debe tener 6 dígitos.';
                        return;
                    }
                    this.error = '';
                    this.loading = true;

                    try {
                        const response = await fetch($MAPAS.baseURL + 'auth/setup_totp', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'code=' + encodeURIComponent(this.code)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.success = true;
                            setTimeout(() => {
                                window.location.href = $MAPAS.baseURL + 'panel';
                            }, 2000);
                        } else {
                            this.error = data.message || 'Error al verificar.';
                        }
                    } catch (e) {
                        this.error = 'Error de conexión';
                        console.error(e);
                    } finally {
                        this.loading = false;
                    }
                }
            }
});
