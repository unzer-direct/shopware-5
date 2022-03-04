//{namespace name="plugins/unzerdirect"}
Ext.define('Shopware.apps.Order.UnzerDirect.controller.Main',
{
	override: 'Shopware.apps.Order.controller.Main',	
	/**
	 *
	 */
	showOrder: function(record) {
            var me = this;
            var detailController = me.getController('Detail');
	    detailController.loadUnzerdirectPayment(record.get('unzerdirect_payment_id'))
            me.callParent(arguments);
	}
});
