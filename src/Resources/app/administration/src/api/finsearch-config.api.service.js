const ApiService = Shopware.Classes.ApiService;

/**
 * @class
 * @extends ApiService
 */
class FinsearchConfigApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'finsearch') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'FinsearchConfigApiService';
    }

    getValues(salesChannelId, languageId, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .get('_action/finsearch', {
                params: { salesChannelId, languageId, ...additionalParams },
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    saveValues(values, salesChannelId, languageId, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .post(
                '_action/finsearch',
                values,
                {
                    params: { salesChannelId, languageId, ...additionalParams },
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    batchSave(values, additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .post(
                '_action/finsearch/batch',
                values,
                {
                    params: { ...additionalParams },
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

/**
 * @private
 */
export default FinsearchConfigApiService;
