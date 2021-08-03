//{namespace name="plugins/unzerdirect"}
Ext.define('Shopware.apps.Order.UnzerDirect.controller.Batch',
{
	override: 'Shopware.apps.Order.controller.Batch',	

	/**
	 *
	 */
	prepareStoreProxy: function(store, values) {
            this.callParent(arguments);
            
            var extraParams = store.getProxy().extraParams;
            extraParams.unzerdirectAction = values.unzerdirectAction;
            
            return store;
	}
});