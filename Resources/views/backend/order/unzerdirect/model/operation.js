//{namespace name="plugins/unzerdirect"}
Ext.define('Shopware.apps.Order.UnzerDirect.model.Operation',
{
    extend: 'Ext.data.Model',
    fields: [
        { name: 'id', type: 'int' },
        { name: 'createdAt', type: 'date' },
        { name: 'type', type: 'string' },
        { name: 'status', type: 'string' },
        { name: 'amount', type: 'int' }
    ],
    
    belongsTo: 'Shopware.apps.Order.UnzerDirect.model.Operation'
    
});