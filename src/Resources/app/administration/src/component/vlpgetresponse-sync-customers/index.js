import template from './vlpgetresponse-sync-customers.html.twig';
const { Component, Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

Component.register('vlpgetresponse-sync-customers', {
    template,

    inject: [
        'vlpgetresponseService',
        'systemConfigApiService',
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    computed: {
        customerGroupRepository() {
            return this.repositoryFactory.create('customer_group');
        },

        countryRepository() {
            return this.repositoryFactory.create('country');
        },
    },

    data() {
        return {
            APIValid: false,

            isFormLoading: true,
            isSyncInProgress: false,
            isSyncFinish: false,
            totalSynced: 0,
            totalToSync: 0,
            totalError: 0,

            customerGroups: null,
            countries: null,
            customerGroupsIds: null,
            countriesIds: null,
            salesChannelId: null,

            fields: {},
            savedFields: {}
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.salesChannelId = window.vlp_saleschannel ?? null;
            this.customerGroups = new EntityCollection(
                this.customerGroupRepository.route,
                this.customerGroupRepository.entityName,
                Shopware.Context.api,
            );
            this.countries = new EntityCollection(
                this.countryRepository.route,
                this.countryRepository.entityName,
                Shopware.Context.api,
            );

            this.systemConfigApiService.getValues('VLPGetResponse', this.salesChannelId).then(values => {
                this.APIValid = values['VLPGetResponse.config.VLPGetResponseAPIKey'] ?? false;
                this.savedFields = values['VLPGetResponse.config.VLPGetResponseSyncCustomers'] ?? {};

                if(!this.APIValid) {
                    this.isFormLoading = false;
                    return;
                }

                this.vlpgetresponseService.getCampaigns(this.salesChannelId).then((res) => {
                    if(typeof res.data !== 'undefined' && res.data) {
                        let options = [];

                        res.data.forEach((campaign) => {
                            options.push({
                                label: campaign.name,
                                value: campaign.campaignId
                            });
                        });

                        this.fields['campaigns'] = {
                            id: 'campaigns',
                            label: 'Campaigns',
                            type: 'select',
                            options: options,
                            savedValue: '',
                        };
                    } else {
                        this.APIValid = false;
                        this.createNotificationError({
                            title: this.$tc('vlpgetresponse-plugin.lblError'),
                            message: res.errorMsg
                        });
                    }

                    this.isFormLoading = false;
                });
            });
        },

        syncStart() {
            if(typeof this.savedFields.campaigns === 'undefined' || this.savedFields.campaigns === '') {
                this.createNotificationError({
                    title: this.$tc('vlpgetresponse-plugin.lblError'),
                    message: this.$tc('vlpgetresponse-plugin.lblErrorNoCampaign')
                });
            } else {
                this.isSyncInProgress = true;
                this.isSyncFinish = false;
                this.totalSynced = 0;
                this.totalToSync = 0;
                this.totalError = 0;

                this.sync(0);
            }
        },

        sync(offset) {
            this.vlpgetresponseService.syncCustomers(this.salesChannelId, this.savedFields, offset).then((res) => {
                this.totalSynced += res.synced;
                this.totalError += res.error;
                this.totalToSync = res.total;

                if(typeof res.running === 'undefined' || this.totalSynced === this.totalToSync) {
                    this.isSyncInProgress = false;
                    this.isSyncFinish = true;
                    this.createNotificationSuccess({
                        title: this.$tc('vlpgetresponse-plugin.lblSuccess'),
                        message: this.$tc('vlpgetresponse-plugin.lblSuccessSync') + ': ' + this.totalSynced + '/' + this.totalToSync + '<br>' + this.$tc('vlpgetresponse-plugin.lblTotalError') + ': ' + this.totalError
                    });
                } else {
                    offset++;
                    this.sync(offset);
                }
            });
        },

        saveField(value, id) {
            this.savedFields[id] = value;
            this.$emit('change', this.savedFields || '');
        },

        selectCustomerGroups(customerGroups) {
            this.customerGroups = customerGroups;
            this.customerGroupsIds = this.customerGroups.getIds();
        },

        selectCountries(countries) {
            this.countries = countries;
            this.countriesIds = this.countries.getIds();
        }
    }
});