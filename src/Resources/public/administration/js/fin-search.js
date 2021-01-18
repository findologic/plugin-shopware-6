(this.webpackJsonp=this.webpackJsonp||[]).push([["fin-search"],{"1Cpp":function(e,t){e.exports='{% block findologic %}\n    <sw-page class="findologic">\n        {% block findologic_header %}\n            <template slot="smart-bar-header">\n                <h2>\n                    {{ $tc(\'sw-settings.index.title\') }}\n                    <sw-icon name="small-arrow-medium-right" small></sw-icon>\n                    {{ $tc(\'findologic.header\') }}\n                </h2>\n            </template>\n        {% endblock %}\n\n        {% block findologic_actions %}\n            <template slot="smart-bar-actions">\n                {% block findologic_actions_save %}\n                    <sw-button\n                            class="sw-settings-login-registration__save-action"\n                            :isLoading="isLoading"\n                            :disabled="isLoading"\n                            variant="primary"\n                            :title="$tc(\'global.default.save\')"\n                            :aria-label="$tc(\'global.default.save\')"\n                            v-model="isSaveSuccessful"\n                            @click="onSave"\n                    >\n                        {{ $tc(\'global.default.save\') }}\n                    </sw-button>\n                {% endblock %}\n\n                {% block findologic_actions_cancel %}\n                    <sw-button\n                            :routerLink="{ name: \'sw.settings.index\' }"\n                            :title="$tc(\'global.default.cancel\')"\n                            :aria-label="$tc(\'global.default.cancel\')">\n                        {{ $tc(\'global.default.cancel\') }}\n                    </sw-button>\n                {% endblock %}\n            </template>\n        {% endblock %}\n\n        {% block findologic_content %}\n            <template slot="content">\n                {% block findologic_content_card %}\n                    <sw-card-view>\n                        {% block findologic_content_card_channel_config %}\n                            {% block findologic_content_card_channel_config_sales_channel_card %}\n                                <sw-card :title="$tc(\'findologic.settingForm.salesChannelTitle\')">\n                                    {% block findologic_content_card_channel_config_sales_channel_card_title %}\n                                        <sw-entity-single-select\n                                                id="salesChannelSelect"\n                                                :placeholder="$tc(\'sw-sales-channel-switch.labelDefaultOption\')"\n                                                :searchPlaceholder="$tc(\'sw-sales-channel-switch.placeholderSelect\')"\n                                                :resetOption="$tc(\'sw-sales-channel-switch.labelDefaultOption\')"\n                                                @change="onSelectedSalesChannel"\n                                                v-model="selectedSalesChannelId"\n                                                entity="sales_channel">\n                                            <template #labelProperty="{item, labelProperty}">\n                                                <span v-if="item.translated">{{ item.translated[labelProperty] }}</span>\n                                            </template>\n                                        </sw-entity-single-select>\n\n                                        <sw-entity-single-select\n                                                ref="languageSelection"\n                                                class="sw-language-switch__select"\n                                                entity="language"\n                                                id="language"\n                                                size="medium"\n                                                :placeholder="$tc(\'findologic.selectSalesChannel\')"\n                                                :disabled="!selectedSalesChannelId"\n                                                required\n                                                v-model="selectedLanguageId"\n                                                @change="onSelectedLanguage">\n                                        </sw-entity-single-select>\n                                    {% endblock %}\n                                </sw-card>\n\n                                {% block findologic_content_card_channel_config_credentials_card %}\n                                    <findologic-config\n                                            v-if="actualConfigData"\n                                            :isActive="isActive"\n                                            :shopkeyAvailable="shopkeyAvailable"\n                                            :isValidShopkey="isValidShopkey"\n                                            :isStagingShop="isStagingShop"\n                                            :allConfigs="allConfigs"\n                                            :actualConfigData="actualConfigData"\n                                            :shopkeyErrorState="shopkeyErrorState"\n                                            :selectedSalesChannelId="selectedSalesChannelId">\n                                    </findologic-config>\n                                {% endblock %}\n                            {% endblock %}\n\n                            {% block findologic_content_card_loading %}\n                                <sw-loader v-if="isLoading"></sw-loader>\n                            {% endblock %}\n                        {% endblock %}\n                    </sw-card-view>\n                {% endblock %}\n            </template>\n        {% endblock %}\n    </sw-page>\n{% endblock %}\n'},"2ETB":function(e,t){e.exports='{% block findologic_credentials %}\n    <sw-container slot="grid" rows="auto auto">\n        <sw-card-section>\n            <sw-card class="sw-card--grid"\n                     :title="$tc(\'findologic.settingForm.config.title\')">\n                <sw-container>\n                    {% block findologic_credentials_card_container %}\n                        {% block findologic_credentials_settings %}\n                            <div class="findologic-settings-credentials-fields">\n                                <sw-text-field\n                                        v-model="actualConfigData[\'FinSearch.config.shopkey\']"\n                                        :label="$tc(\'findologic.settingForm.config.shopkey.label\')"\n                                        :helpText="$tc(\'findologic.settingForm.config.shopkey.tooltipText\')"\n                                        :required="true"\n                                        :error="shopkeyErrorState"\n                                >\n                                </sw-text-field>\n                                <sw-switch-field\n                                        v-model="actualConfigData[\'FinSearch.config.active\']"\n                                        :label="$tc(\'findologic.settingForm.config.active.label\')"\n                                        :helpText="$tc(\'findologic.settingForm.config.active.tooltipText\')"\n                                >\n                                </sw-switch-field>\n                                <sw-switch-field\n                                        v-model="actualConfigData[\'FinSearch.config.activeOnCategoryPages\']"\n                                        :label="$tc(\'findologic.settingForm.config.activeOnCategoryPages.label\')"\n                                        :helpText="$tc(\'findologic.settingForm.config.activeOnCategoryPages.tooltipText\')"\n                                >\n                                </sw-switch-field>\n                                <sw-button\n                                        v-show="showTestButton"\n                                        @click="openSalesChannelUrl"\n                                >\n                                    {{ $tc(\'findologic.settingForm.testButton\') }}\n                                </sw-button>\n                                <span class="divider"></span>\n                                <sw-multi-select\n                                        v-model="actualConfigData[\'FinSearch.config.crossSellingCategories\']"\n                                        :options="categories"\n                                        :label="$tc(\'findologic.settingForm.config.crossSellingCategories.label\')"\n                                        :helpText="$tc(\'findologic.settingForm.config.crossSellingCategories.tooltipText\')"\n                                        :placeholder="$tc(\'findologic.settingForm.config.crossSellingCategories.placeholder\')"\n                                >\n                                    <template #selection-label-property="{ item }">\n                                        {{ item.name }}\n                                    </template>\n\n                                </sw-multi-select>\n                                <sw-text-field\n                                        v-model="actualConfigData[\'FinSearch.config.integrationType\']"\n                                        ref="integrationType"\n                                        :label="$tc(\'findologic.settingForm.config.integrationType.label\')"\n                                        :helpText="$tc(\'findologic.settingForm.config.integrationType.tooltipText\')"\n                                        :disabled="true"\n                                >\n                                </sw-text-field>\n                            </div>\n                        {% endblock %}\n                    {% endblock %}\n                </sw-container>\n\n            </sw-card>\n\n            <sw-card class="sw-card--grid"\n                     v-if="showDIConfig"\n                     :title="$tc(\'findologic.settingForm.directIntegration.title\')">\n                <sw-container>\n\n                    <div class="findologic-settings-credentials-fields">\n                        <sw-text-field\n                                v-model="actualConfigData[\'FinSearch.config.searchResultContainer\']"\n                                :label="$tc(\'findologic.settingForm.config.searchResultContainer.label\')"\n                                :helpText="$tc(\'findologic.settingForm.config.searchResultContainer.tooltipText\')"\n                                :placeholder="$tc(\'findologic.settingForm.config.searchResultContainer.placeholder\')"\n                        >\n                        </sw-text-field>\n                        <sw-text-field\n                                v-model="actualConfigData[\'FinSearch.config.navigationResultContainer\']"\n                                :label="$tc(\'findologic.settingForm.config.navigationResultContainer.label\')"\n                                :helpText="$tc(\'findologic.settingForm.config.navigationResultContainer.tooltipText\')"\n                                :placeholder="$tc(\'findologic.settingForm.config.navigationResultContainer.placeholder\')"\n                        >\n                        </sw-text-field>\n                    </div>\n                </sw-container>\n            </sw-card>\n\n            <sw-card class="sw-card--grid" v-if="showAPIConfig" :title="$tc(\'findologic.settingForm.api.title\')">\n                <sw-container>\n\n                    <div class="findologic-settings-credentials-fields">\n\n                        {% block findologic_credentials_settings_search_filter_position_container %}\n                            <sw-single-select\n                                    v-model="actualConfigData[\'FinSearch.config.filterPosition\']"\n                                    :options="filterPositionOptions"\n                                    :label="$tc(\'findologic.settingForm.config.filterPosition.label\')"\n                                    :helpText="$tc(\'findologic.settingForm.config.filterPosition.tooltipText\')">\n                            </sw-single-select>\n                        {% endblock %}\n                    </div>\n                </sw-container>\n            </sw-card>\n        </sw-card-section>\n    </sw-container>\n{% endblock %}\n'},"7JrG":function(e,t,i){},LN1s:function(e,t){e.exports="{% block sw_plugin_list_grid_columns_actions_settings %}\n    <template v-if=\"item.composerName === 'findologic/plugin-shopware-6'\">\n        <sw-context-menu-item :routerLink=\"{ name: 'findologic.module.index' }\">\n            {{ $tc('sw-plugin.list.config') }}\n        </sw-context-menu-item>\n    </template>\n\n    <template v-else>\n        {% parent %}\n    </template>\n{% endblock %}\n"},Qoii:function(e,t,i){"use strict";i.r(t);const n=Shopware.Classes.ApiService;var o=class extends n{constructor(e,t,i="finsearch"){super(e,t,i),this.name="FinsearchConfigApiService"}getValues(e,t,i={},o={}){return this.httpClient.get("_action/finsearch",{params:{salesChannelId:e,languageId:t,...i},headers:this.getBasicHeaders(o)}).then(e=>n.handleResponse(e))}saveValues(e,t,i,o={},s={}){return this.httpClient.post("_action/finsearch",e,{params:{salesChannelId:t,languageId:i,...o},headers:this.getBasicHeaders(s)}).then(e=>n.handleResponse(e))}batchSave(e,t={},i={}){return this.httpClient.post("_action/finsearch/batch",e,{params:{...t},headers:this.getBasicHeaders(i)}).then(e=>n.handleResponse(e))}};const{Application:s}=Shopware;s.addServiceProvider("FinsearchConfigApiService",e=>{const t=s.getContainer("init");return new o(t.httpClient,e.loginService)});var a=i("LN1s"),l=i.n(a);const{Component:r}=Shopware;r.override("sw-plugin-list",{template:l.a});var c=i("1Cpp"),g=i.n(c);i("ZMAt");const{Component:h,Mixin:d,Application:p}=Shopware,{Criteria:f}=Shopware.Data;h.register("findologic-page",{template:g.a,inject:["repositoryFactory","FinsearchConfigApiService","systemConfigApiService"],mixins:[d.getByName("notification")],data:()=>({isLoading:!1,isSaveSuccessful:!1,isStagingShop:!1,shopkeyExists:!1,isRegisteredShopkey:null,isActive:!1,config:null,allConfigs:{},selectedSalesChannelId:null,selectedLanguageId:null,salesChannel:[],language:[],shopkeyErrorState:null,httpClient:p.getContainer("init").httpClient}),metaInfo(){return{title:this.$createTitle()}},created(){this.createdComponent()},watch:{selectedLanguageId:{handler(e){e&&this.createdComponent()}},shopkey(){this.shopkeyErrorState=null,this.isValidShopkey&&this._isStagingRequest(),this._setErrorStates()}},computed:{configKey(){return this.selectedSalesChannelId+"-"+this.selectedLanguageId},actualConfigData:{get(){return this.allConfigs[this.configKey]},set(e){this.allConfigs={...this.allConfigs,[this.configKey]:e}}},shopkey(){return this.actualConfigData?this.actualConfigData["FinSearch.config.shopkey"]:""},isValidShopkey(){return!1!==/^[A-F0-9]{32}$/.test(this.shopkey)},shopkeyAvailable(){return!!this.shopkey},salesChannelRepository(){return this.repositoryFactory.create("sales_channel")},languageRepository(){return this.repositoryFactory.create("language")},findologicConfigRepository(){return this.repositoryFactory.create("finsearch_config")}},methods:{createdComponent(){if(!this.allConfigs[this.configKey]&&(!this.actualConfigData&&this.selectedSalesChannelId&&this.selectedLanguageId&&this.readAll().then(e=>{e["FinSearch.config.filterPosition"]="top",this.actualConfigData=e}),!this.salesChannel.length)){let e=new f;e.addAssociation("languages"),e.addFilter(f.equals("active",!0)),this.salesChannelRepository.search(e,Shopware.Context.api).then(e=>{this.salesChannel=e})}},readAll(){return this.FinsearchConfigApiService.getValues(this.selectedSalesChannelId,this.selectedLanguageId)},_isStagingRequest(){this.httpClient.get(`https://cdn.findologic.com/config/${this.shopkey}/config.json`).then(e=>{e.data.isStagingShop&&(this.isStagingShop=!0)}).catch(()=>{this.isStagingShop=!1})},onSave(){this.shopkeyAvailable&&!this.isValidShopkey?this._setErrorStates(!0):this.shopkeyAvailable?this._validateShopkeyFromService().then(e=>{this.isRegisteredShopkey=e}).then(()=>{this.isRegisteredShopkey?this._save():this._setErrorStates(!0)}):this._save()},_save(){this.FinsearchConfigApiService.batchSave(this.allConfigs).then(e=>{this.shopkeyExists=!1,this.shopkeyErrorState=null,this.isLoading=!1,this.isSaveSuccessful=!0,this.createNotificationSuccess({title:this.$tc("findologic.settingForm.titleSuccess"),message:this.$tc("findologic.settingForm.configSaved")}),e&&(this.actualConfigData=e)}).catch(e=>{this.isSaveSuccessful=!1,this.isLoading=!1,this.shopkeyExists=!0,this._setErrorStates(!0)})},_setErrorStates(e=!1){this.isLoading=!1,this.shopkeyAvailable&&(this.isValidShopkey?!1===this.isRegisteredShopkey?this.shopkeyErrorState={code:1,detail:this.$tc("findologic.notRegisteredShopkey")}:!0===this.shopkeyExists?this.shopkeyErrorState={code:1,detail:this.$tc("findologic.shopkeyExists")}:this.shopkeyErrorState=null:this.shopkeyErrorState={code:1,detail:this.$tc("findologic.invalidShopkey")}),e&&this._showNotification()},_showNotification(){this.shopkeyAvailable?this.isValidShopkey?!1===this.isRegisteredShopkey?this.createNotificationError({title:this.$tc("findologic.settingForm.titleError"),message:this.$tc("findologic.notRegisteredShopkey")}):!0===this.shopkeyExists&&this.createNotificationError({title:this.$tc("findologic.settingForm.titleError"),message:this.$tc("findologic.shopkeyExists")}):this.createNotificationError({title:this.$tc("findologic.settingForm.titleError"),message:this.$tc("findologic.invalidShopkey")}):this.createNotificationError({title:this.$tc("findologic.settingForm.titleError"),message:this.$tc("findologic.fieldRequired")})},_validateShopkeyFromService(){return this.isLoading=!0,this.httpClient.get("https://account.findologic.com/api/v1/shopkey/validate/"+this.shopkey).then(e=>String(e.status).startsWith("2")).catch(()=>!1)},onSelectedLanguage(e){this.shopkeyErrorState=null,this.selectedLanguageId=e,this.createdComponent()},onSelectedSalesChannel(e){if(void 0===this.salesChannel||null===e)return this.selectedLanguageId=null,void(this.language=[]);let t=this.salesChannel.find(t=>t.id=e);t&&(this.selectedSalesChannelId=e,this.selectedLanguageId=t.languageId,this.language=t.languages)}}});var u=i("2ETB"),S=i.n(u);const{Component:y,Mixin:m}=Shopware,{Criteria:C}=Shopware.Data;y.register("findologic-config",{template:S.a,name:"FindologicConfig",inject:["repositoryFactory"],mixins:[m.getByName("notification")],props:{actualConfigData:{required:!0},allConfigs:{type:Object,required:!0},shopkeyErrorState:{required:!0},selectedSalesChannelId:{type:String,required:!1,default:null},isStagingShop:{type:Boolean,required:!0,default:!1},isValidShopkey:{type:Boolean,required:!0,default:!1},isActive:{type:Boolean,required:!0,default:!1},shopkeyAvailable:{type:Boolean,required:!0,default:!1}},data:()=>({isLoading:!1,term:null,categories:[],categoryIds:[]}),created(){this.createdComponent()},methods:{createdComponent(){this.getCategories()},isString(e){if("string"!=typeof e)return!0},isBoolean:e=>"boolean"!=typeof e,sortByProperty:(e,t="name",i="asc")=>(e.sort((function(e,n){const o="string"==typeof e[t]?e[t].toUpperCase():e[t],s="string"==typeof n[t]?n[t].toUpperCase():n[t];let a=0;return o>s?a="asc"===i?1:-1:o<s&&(a="asc"===i?-1:1),a})),e),openSalesChannelUrl(){if(null!==this.selectedSalesChannelId){const e=new C;e.addFilter(C.equals("id",this.selectedSalesChannelId)),e.setLimit(1),e.addAssociation("domains"),this.salesChannelRepository.search(e,Shopware.Context.api).then(e=>{const t=e.first().domains.first();this._openStagingUrl(t)})}else this._openDefaultUrl()},_openDefaultUrl(){const e=window.location.origin+"?findologic=on";window.open(e,"_blank")},_openStagingUrl(e){if(e){const t=e.url+"?findologic=on";window.open(t,"_blank")}else this._openDefaultUrl()},getCategories(){this.isLoading=!0;const e=[];this.categoryRepository.search(this.categoryCriteria,Shopware.Context.api).then(t=>{this.term=null,this.total=t.total,t.forEach(t=>{e.push({value:t.id,name:t.name,label:t.translated.breadcrumb.join(" > ")})}),this.categories=this.sortByProperty(e,"label")}).finally(()=>{this.isLoading=!1})}},computed:{showTestButton(){return this.isActive&&this.shopkeyAvailable&&this.isValidShopkey&&this.isStagingShop},showAPIConfig(){return void 0===this.integrationType||"API"===this.integrationType},showDIConfig(){return void 0===this.integrationType||"Direct Integration"===this.integrationType},filterPositionOptions(){return[{label:this.$tc("findologic.settingForm.config.filterPosition.top.label"),value:"top"},{label:this.$tc("findologic.settingForm.config.filterPosition.left.label"),value:"left"}]},integrationType(){return this.actualConfigData["FinSearch.config.integrationType"]},salesChannelRepository(){return this.repositoryFactory.create("sales_channel")},categoryRepository(){return this.repositoryFactory.create("category")},categoryCriteria(){const e=new C(1,500);return e.addSorting(C.sort("name","ASC")),e.addSorting(C.sort("parentId","ASC")),this.term&&e.addFilter(C.contains("name",this.term)),e}}});var k=i("s3Ly"),b=i("zaPX");const{Module:v}=Shopware;v.register("findologic-module",{type:"plugin",name:"FinSearch",title:"findologic.header",description:"findologic.general.mainMenuDescription",color:"#f7ff0f",snippets:{"de-DE":b,"en-GB":k},routes:{index:{component:"findologic-page",path:"index",meta:{parentPath:"sw.settings.index"}}}})},ZMAt:function(e,t,i){var n=i("7JrG");"string"==typeof n&&(n=[[e.i,n,""]]),n.locals&&(e.exports=n.locals);(0,i("SZ7m").default)("7841029e",n,!0,{})},s3Ly:function(e){e.exports=JSON.parse('{"findologic":{"header":"FINDOLOGIC","general":{"mainMenuDescription":"FINDOLOGIC plugin for Shopware 6 e-commerce system"},"fieldRequired":"Shopkey is required.","invalidShopkey":"Invalid Shopkey.","shopkeyExists":"This shopkey is already configured for another language.","notRegisteredShopkey":"The given shopkey is not associated to any known service.","selectSalesChannel":"Please select a Sales Channel...","settingForm":{"salesChannelTitle":"Sales Channel & Language","directIntegration":{"title":"Direct Integration"},"api":{"title":"API"},"configSaved":"Configuration saved.","testButton":"Test mode","config":{"title":"Configuration","shopkey":{"label":"Shopkey","tooltipText":"FINDOLOGIC shopkey"},"active":{"label":"Active","tooltipText":"Activate the FINDOLOGIC search provider."},"activeOnCategoryPages":{"label":"Active on category pages","tooltipText":"Activate the FINDOLOGIC search provider for category pages."},"searchResultContainer":{"label":"CSS class for search result","placeholder":"fl-result","tooltipText":"This option has an effect for Direct Integration only, when empty fl-result is used."},"navigationResultContainer":{"label":"CSS class for navigation","placeholder":"fl-navigation-result","tooltipText":"This option has an effect for Direct Integration only, when empty fl-navigation-result is used."},"crossSellingCategories":{"label":"Cross-Selling categories","placeholder":"Search categories...","tooltipText":"If you have multiple subcategories in your Cross-Selling category, make sure to add all subcategories. Products in these categories are excluded from the export"},"integrationType":{"label":"Integration (read-only)","tooltipText":"Currently used integration type. Either one of Direct Integration or API."},"filterPosition":{"label":"Filter position for search result pages","tooltipText":"Top: Shows the filters above the search results. Left: Shows the filters on the left side, next to the search results.","top":{"label":"Top"},"left":{"label":"Left"}}},"titleSuccess":"Success","titleError":"Error"},"error":{"title":"Error"}}}')},zaPX:function(e){e.exports=JSON.parse('{"findologic":{"header":"FINDOLOGIC","general":{"mainMenuDescription":"FINDOLOGIC Plugin für das Shopware 6 E-Commerce System"},"fieldRequired":"Shopkey ist erforderlich.","invalidShopkey":"Ungültiger Shopkey.","shopkeyExists":"Dieser Shopkey is bereits einer anderen Sprache zugeordnet.","notRegisteredShopkey":"Der eingegebene Shopkey ist keinem bekannten Service zugeordnet.","selectSalesChannel":"Bitte wählen Sie einen Verkaufskanal...","settingForm":{"salesChannelTitle":"Verkaufskanal & Sprache","directIntegration":{"title":"Direct Integration"},"api":{"title":"API"},"configSaved":"Konfiguration gespeichert.","testButton":"Testmodus","config":{"title":"Konfiguration","shopkey":{"label":"Shopkey","tooltipText":"FINDOLOGIC shopkey"},"active":{"label":"Aktiv","tooltipText":"Aktiviert die FINDOLOGIC Suche."},"activeOnCategoryPages":{"label":"Aktiv auf Kategorieseiten","tooltipText":"Aktiviert die FINDOLOGIC Suche für Kategorieseiten."},"searchResultContainer":{"label":"CSS-Klasse für Suchresultat","placeholder":"fl-result","tooltipText":"Diese Option ist nur bei Direct Integration wirksam, wenn kein Wert gegeben ist wird fl-result verwendet"},"navigationResultContainer":{"label":"CSS-Klasse für Navigation","placeholder":"fl-navigation-result","tooltipText":"Diese Option ist nur bei Direct Integration wirksam, wenn kein Wert gegeben ist wird fl-navigation-result verwendet."},"crossSellingCategories":{"label":"Cross-Selling Kategorien","placeholder":"Kategorien suchen...","tooltipText":"Sollten Sie mehrere Subkategorien in Ihrer Cross-Selling Kategorie haben, fügen sie jede Subkategorie ein. Produkte in diesen Kategorien werden vom Export ausgeschlossen"},"integrationType":{"label":"Integration (schreibgeschützt)","tooltipText":"Die aktuell verwendete Integrationsart. Entweder Direct Integration oder API."},"filterPosition":{"label":"Position der Filter auf Suchergebnisseiten","tooltipText":"Oben: Zeigt die Filter über dem Suchergebnis an. Links: Zeigt die Filter auf der linken Seite, neben dem Suchergebnis, an","top":{"label":"Oben"},"left":{"label":"Links"}}},"titleSuccess":"Erfolgreich","titleError":"Fehler"},"error":{"title":"Fehler"}}}')}},[["Qoii","runtime","vendors-node"]]]);