import FinsearchConfigApiService from '../api/finsearch-config.api.service';

const { Application } = Shopware;

Application.addServiceProvider('FinsearchConfigApiService', (container) => {
  const initContainer = Application.getContainer('init');

  return new FinsearchConfigApiService(initContainer.httpClient, container.loginService);
});
