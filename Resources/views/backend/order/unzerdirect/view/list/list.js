//{namespace name="plugins/unzerdirect"}
Ext.define('Shopware.apps.Order.UnzerDirect.view.list.List',
{
    override: 'Shopware.apps.Order.view.list.List',
    unzerdirectSnippets: {
        columns: {
            unzerdirect: '{s name=column/unzerdirect}UnzerDirect Actions{/s}',
            capturePayment: '{s name=column/capture_payment}Capture payment{/s}'
        }
    },
    initComponent:function()
    {
        var me = this;
        me.registerEvents();
        me.callParent(arguments);
    },
    registerEvents: function()
    {
        var me = this;
        me.addEvents(
            'showCaptureConfirmWindow',
            'showCancelConfirmWindow',
            'showRefundConfirmWindow',
        );
        me.callParent(arguments);
    },
        
    operationFinished(operation, cancelled)
    {
        var me = this;
        if(!cancelled)
        {
            me.getStore().reload();
        }
    },
        
    getColumns:function()
    {
        var me = this;
        var columns = me.callParent(arguments);
        var unzerdirectActionColumn = Ext.create('Ext.grid.column.Action', {
            width:30,
            text: 'QP',
            menuText: '<i>' + me.unzerdirectSnippets.columns.unzerdirect + '</i>',
            items:[
                {
                    iconCls: 'sprite-arrow-curve-270-left',
                    action: 'capturePayment',
                    tooltip: me.unzerdirectSnippets.columns.capturePayment,
                    handler: function (view, rowIndex) 
                    {
                        var store = view.getStore(),
                        record = store.getAt(rowIndex);
                        
                        if(me.isUnzerdirectOrder(record) && me.isCaptureEnabled(record))
                            me.fireEvent('showCaptureConfirmWindow', me.getPaymentData(record), record, me);
                    },
                    getClass: function(value, metadata, record)
                    {                
                        if(!me.isUnzerdirectOrder(record) || !me.isCaptureEnabled(record))
                        {
                            return 'x-hide-display';
                        }
                        
                        return '';
                    }
                }
            ],
        });
        return Ext.Array.insert(columns, 11, [unzerdirectActionColumn]);
    },
    isUnzerdirectOrder: function(record)
    {
        return record.get('unzerdirect_payment_id') ? true : false;
    },
    isCaptureEnabled: function(record)
    {
        return record.get('unzerdirect_payment_status') === 5;
    },
    getPaymentData: function(record)
    {
        return {
            id: record.get('unzerdirect_payment_id'),
            amountAuthorized: record.get('unzerdirect_amount_authorized'),
            amountCaptured: record.get('unzerdirect_amount_captured'),
            amountRefunded: record.get('unzerdirect_amount_refunded')
        };
    }
});
