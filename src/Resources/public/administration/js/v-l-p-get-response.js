(function(){var e={279:function(){let{Component:e}=Shopware;e.override("sw-sales-channel-switch",{created(){window.vlp_saleschannel=null},methods:{onChange(e){this.salesChannelId=e,window.vlp_saleschannel=this.salesChannelId,this.checkAbort()}}})},375:function(){let e=Shopware.Classes.ApiService,{Application:s}=Shopware;class t extends e{constructor(e,s,t="vlpgetresponse"){super(e,s,t)}getCampaigns(s){return this.httpClient.post(`_action/${this.getApiBasePath()}/get_campaigns`,{params:{sales_channel_id:s}}).then(s=>e.handleResponse(s))}syncCustomers(s,t,n){return this.httpClient.post(`_action/${this.getApiBasePath()}/sync_customers_to_contacs`,{sales_channel_id:s,fields:t,offset:n}).then(s=>e.handleResponse(s))}}s.addServiceProvider("vlpgetresponseService",e=>new t(s.getContainer("init").httpClient,e.loginService))}},s={};function t(n){var i=s[n];if(void 0!==i)return i.exports;var r=s[n]={exports:{}};return e[n](r,r.exports,t),r.exports}t.p="bundles/vlpgetresponse/",window?.__sw__?.assetPath&&(t.p=window.__sw__.assetPath+"/bundles/vlpgetresponse/"),function(){"use strict";t(375),t(279);let{Component:e,Mixin:s}=Shopware,{Criteria:n,EntityCollection:i}=Shopware.Data;e.register("vlpgetresponse-sync-customers",{template:'<div>\r\n    <sw-loader v-if="isFormLoading"></sw-loader>\r\n\r\n    <sw-entity-multi-select\r\n            v-if="APIValid && !isFormLoading"\r\n            entity="customer_group"\r\n            :entity-collection="customerGroups"\r\n            :label="$tc(\'vlpgetresponse-plugin.lblCustomerGroups\')"\r\n            @update:entity-collection="selectCustomerGroups"\r\n    />\r\n\r\n    <sw-entity-multi-select\r\n            v-if="APIValid && !isFormLoading"\r\n            entity="country"\r\n            :entity-collection="countries"\r\n            :label="$tc(\'vlpgetresponse-plugin.lblCountries\')"\r\n            @update:entity-collection="selectCountries"\r\n    />\r\n\r\n    <div class="sw-field" v-for="field in fields">\r\n        <sw-select-field\r\n            v-if="field.type === \'select\'"\r\n            :label="field.label"\r\n            :value="field.savedValue"\r\n            :disabled="isSyncInProgress"\r\n            @update:value="saveField($event, field.id)" >\r\n\r\n            <option></option>\r\n\r\n            <option v-for="option in field.options"\r\n                    :value="option.value">\r\n                {{ option.label }}\r\n            </option>\r\n        </sw-select-field>\r\n    </div>\r\n\r\n    <div class="sw-field" v-if="!isFormLoading && APIValid">\r\n        <sw-button-process\r\n            :isLoading="isLoading"\r\n            :disabled="isSyncInProgress"\r\n            :processSuccess="isSyncFinish"\r\n            @click="syncStart"\r\n        >\r\n            {{ $tc(\'vlpgetresponse-plugin.startSync\') }}\r\n        </sw-button-process>\r\n    </div>\r\n\r\n    <div class="sw-field" v-if="totalSynced > 0">\r\n        <sw-progress-bar\r\n            :value="totalSynced"\r\n            :max-value="totalToSync"\r\n        >\r\n        </sw-progress-bar>\r\n\r\n        <div class="vlphubspot-progress-indicator">\r\n            {{ totalSynced }}/{{ totalToSync }}\r\n        </div>\r\n    </div>\r\n</div>',inject:["vlpgetresponseService","systemConfigApiService","repositoryFactory"],mixins:[s.getByName("notification")],computed:{customerGroupRepository(){return this.repositoryFactory.create("customer_group")},countryRepository(){return this.repositoryFactory.create("country")}},data(){return{APIValid:!1,isFormLoading:!0,isSyncInProgress:!1,isSyncFinish:!1,totalSynced:0,totalToSync:0,totalError:0,customerGroups:null,countries:null,customerGroupsIds:null,countriesIds:null,salesChannelId:null,fields:{},savedFields:{}}},created(){this.createdComponent()},methods:{createdComponent(){this.salesChannelId=window.vlp_saleschannel??null,this.customerGroups=new i(this.customerGroupRepository.route,this.customerGroupRepository.entityName,Shopware.Context.api),this.countries=new i(this.countryRepository.route,this.countryRepository.entityName,Shopware.Context.api),this.systemConfigApiService.getValues("VLPGetResponse",this.salesChannelId).then(e=>{if(this.APIValid=e["VLPGetResponse.config.VLPGetResponseAPIKey"]??!1,this.savedFields=e["VLPGetResponse.config.VLPGetResponseSyncCustomers"]??{},!this.APIValid){this.isFormLoading=!1;return}this.vlpgetresponseService.getCampaigns(this.salesChannelId).then(e=>{if(void 0!==e.data&&e.data){let s=[];e.data.forEach(e=>{s.push({label:e.name,value:e.campaignId})}),this.fields.campaigns={id:"campaigns",label:"Campaigns",type:"select",options:s,savedValue:""}}else this.APIValid=!1,this.createNotificationError({title:this.$tc("vlpgetresponse-plugin.lblError"),message:e.errorMsg});this.isFormLoading=!1})})},syncStart(){void 0===this.savedFields.campaigns||""===this.savedFields.campaigns?this.createNotificationError({title:this.$tc("vlpgetresponse-plugin.lblError"),message:this.$tc("vlpgetresponse-plugin.lblErrorNoCampaign")}):(this.isSyncInProgress=!0,this.isSyncFinish=!1,this.totalSynced=0,this.totalToSync=0,this.totalError=0,this.sync(0))},sync(e){this.vlpgetresponseService.syncCustomers(this.salesChannelId,this.savedFields,e).then(s=>{this.totalSynced+=s.synced,this.totalError+=s.error,this.totalToSync=s.total,console.log(s.running),void 0===s.running||this.totalSynced===this.totalToSync?(this.isSyncInProgress=!1,this.isSyncFinish=!0,this.createNotificationSuccess({title:this.$tc("vlpgetresponse-plugin.lblSuccess"),message:this.$tc("vlpgetresponse-plugin.lblSuccessSync")+": "+this.totalSynced+"/"+this.totalToSync+"<br>"+this.$tc("vlpgetresponse-plugin.lblTotalError")+": "+this.totalError})):(e++,this.sync(e))})},saveField(e,s){this.savedFields[s]=e,this.$emit("change",this.savedFields||"")},selectCustomerGroups(e){this.customerGroups=e,this.customerGroupsIds=this.customerGroups.getIds()},selectCountries(e){this.countries=e,this.countriesIds=this.countries.getIds()}}})}()})();