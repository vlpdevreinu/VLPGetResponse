const { Component } = Shopware;

Component.override('sw-sales-channel-switch', {
    created() {
        window.vlp_saleschannel = null;
    },

    methods: {
        onChange(id) {
            this.salesChannelId = id;
            window.vlp_saleschannel = this.salesChannelId;
            this.checkAbort();
        },
    }
});