//{namespace name="plugins/unzerdirect"}
Ext.define('Shopware.apps.Order.UnzerDirect.view.detail.Window',
{
    override: 'Shopware.apps.Order.view.detail.Window',
    createTabPanel: function()
    {
        var me = this;
        var tab_panel = me.callParent(arguments);

        tab_panel.add(Ext.create('Shopware.apps.UnzerDirect.view.detail.UnzerDirect', {
            record: me.record,
            unzerdirectPaymentStore: Ext.getStore('unzerdirect-payment-store')
        }));
        
        return tab_panel;
    }
});