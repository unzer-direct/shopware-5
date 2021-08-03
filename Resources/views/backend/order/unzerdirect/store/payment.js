//{namespace name="plugins/unzerdirect"}
Ext.define('Shopware.apps.Order.UnzerDirect.store.Payment',
{
    extend: 'Ext.data.Store',

    model: 'Shopware.apps.Order.UnzerDirect.model.Payment',

    storeId: 'unzerdirect-payment-store',

    pageSize: 10,

    autoLoad: false,

    sorters: [
        {
            property: 'createdAt',
            direction: 'DESC'
        }
    ],

    proxy: {
        type: 'ajax',
        url: '{url controller="UnzerDirect" action="list"}',
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        }
    }
});
