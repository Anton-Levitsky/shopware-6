const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
import template from './payone-settings.html.twig';

Component.register('payone-settings', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet')
    ],

    inject: ['PayonePaymentApiCredentialsService'],

    data() {
        return {
            isLoading: false,
            isTesting: false,
            isSaveSuccessful: false,
            isTestSuccessful: false,
            config: {},
            merchantIdFilled: false,
            accountIdFilled: false,
            portalIdFilled: false,
            portalKeyFilled: false,
            showValidationErrors: false
        };
    },

    computed: {
        credentialsMissing: function() {
            return !this.merchantIdFilled || !this.accountIdFilled || !this.portalIdFilled || !this.portalKeyFilled;
        }
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    methods: {
        paymentMethodPrefixes() {
            // TODO: Autogenerate config array with these prefixes
            return [
                'creditCard',
                'debit',
                'paypal',
                'paypalExpress',
                'payolutionInvoicing',
                'payolutionInstallment',
                'sofort'
            ];
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        testFinish() {
            this.isTestSuccessful = false;
        },

        onConfigChange(config) {
            this.config = config;

            this.checkCredentialsFilled();

            this.showValidationErrors = false;
        },

        checkCredentialsFilled() {
            this.merchantIdFilled = !!this.getConfigValue('merchantId');
            this.accountIdFilled = !!this.getConfigValue('accountId');
            this.portalIdFilled = !!this.getConfigValue('portalId');
            this.portalKeyFilled = !!this.getConfigValue('portalKey');
        },

        getConfigValue(field) {
            const defaultConfig = this.$refs.systemConfig.actualConfigData.null;
            const salesChannelId = this.$refs.systemConfig.currentSalesChannelId;

            if (salesChannelId === null) {
                return this.config[`PayonePayment.settings.${field}`];
            }
            return this.config[`PayonePayment.settings.${field}`]
                    || defaultConfig[`PayonePayment.settings.${field}`];
        },

        getPaymentConfigValue(field, prefix) {
            let uppercasedField = field.charAt(0).toUpperCase() + field.slice(1);

            return this.getConfigValue(prefix + uppercasedField)
                || this.getConfigValue(field);
        },

        onSave() {
            if (this.credentialsMissing) {
                this.showValidationErrors = true;
                return;
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.$refs.systemConfig.saveAll().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onTest() {
            this.isTesting = true;
            this.isTestSuccessful = false;

            let credentials = {};
            this.paymentMethodPrefixes().forEach((prefix) => {
                credentials[prefix] = {
                    merchantId: this.getPaymentConfigValue('merchantId', prefix),
                    accountId: this.getPaymentConfigValue('accountId', prefix),
                    portalId: this.getPaymentConfigValue('portalId', prefix),
                    portalKey: this.getPaymentConfigValue('portalKey', prefix)
                };
            });

            this.PayonePaymentApiCredentialsService.validateApiCredentials(credentials).then((response) => {
                const credentialsValid = response.credentialsValid;
                const errors = response.errors;

                if (credentialsValid) {
                    this.createNotificationSuccess({
                        title: this.$tc('payone-payment.settingsForm.titleSuccess'),
                        message: this.$tc('payone-payment.settingsForm.messageTestSuccess')
                    });
                    this.isTestSuccessful = true;
                } else {
                    for(let key in errors) {
                        if(errors.hasOwnProperty(key)) {
                            this.createNotificationError({
                                title: this.$tc('payone-payment.settingsForm.titleError'),
                                message: this.$tc('payone-payment.settingsForm.messageTestError.' + key)
                            });
                        }
                    }
                }
                this.isTesting = false;
            }).catch((errorResponse) => {
                this.createNotificationError({
                    title: this.$tc('payone-payment.settingsForm.titleError'),
                    message: this.$tc('payone-payment.settingsForm.messageTestError.general')
                });
                this.isTesting = false;
            });
        },

        getBind(element, config) {
            if (config !== this.config) {
                this.onConfigChange(config);
            }
            if (this.showValidationErrors) {
                if (element.name === 'PayonePayment.settings.merchantId' && !this.merchantIdFilled) {
                    element.config.error = {
                        code: 1,
                        detail: this.$tc('payone-payment.messageNotBlank')
                    };
                }
                if (element.name === 'PayonePayment.settings.accountId' && !this.accountIdFilled) {
                    element.config.error = {
                        code: 1,
                        detail: this.$tc('payone-payment.messageNotBlank')
                    };
                }
                if (element.name === 'PayonePayment.settings.portalId' && !this.portalIdFilled) {
                    element.config.error = {
                        code: 1,
                        detail: this.$tc('payone-payment.messageNotBlank')
                    };
                }
                if (element.name === 'PayonePayment.settings.portalKey' && !this.portalKeyFilled) {
                    element.config.error = {
                        code: 1,
                        detail: this.$tc('payone-payment.messageNotBlank')
                    };
                }
            }

            return element;
        },

        getPaymentStatusCriteria() {
            const criteria = new Criteria(1, 100);
            criteria.addFilter(Criteria.equals('stateMachine.technicalName', 'order_transaction.state'));

            return criteria;
        }
    }
});
