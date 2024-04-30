const ApiService = Shopware.Classes.ApiService;
const { Application } = Shopware;

class ApiClient extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'vlpgetresponse') {
        super(httpClient, loginService, apiEndpoint);
    }

    getCampaigns(salesChannelId) {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/get_campaigns`, {
                params: {
                    sales_channel_id: salesChannelId
                }
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    syncCustomers(salesChannelId, fields, offset) {
        return this.httpClient
            .post(`_action/${this.getApiBasePath()}/sync_customers_to_contacs`, {
                sales_channel_id: salesChannelId,
                fields: fields,
                offset: offset
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

Application.addServiceProvider('vlpgetresponseService', (container) => {
    const initContainer = Application.getContainer('init');
    return new ApiClient(initContainer.httpClient, container.loginService);
});