//{namespace name="plugins/unzerdirect"}
Ext.define('Shopware.apps.Order.UnzerDirect.view.detail.RefundConfirmWindow',
{
    extend: 'Shopware.apps.Order.UnzerDirect.view.detail.ConfirmWindow',
    
    alias: 'widget.order-unzerdirect-refund-confirm-window',
    cls: Ext.baseCSSPrefix + 'order-unzerdirect-refund-confirm-window',
    snippets: {
        title: '{s name=order/refund_confirm_window/title}Refund payment{/s}',
        text: '{s name=order/refund_confirm_window/text}Check the amount and press confirm to send the refund request to the UnzerDirect API.{/s}',
        amountLabel: '{s name=order/refund_confirm_window/amount_label}Amount:{/s}'
    },
    /**
     *
     */
    initComponent: function()
    {
        var me = this;
        me.title = me.snippets.title;
    
        me.callParent(arguments);
    },
    getPanelProperties: function ()
    {
        var me = this;
        var props = me.callParent(arguments);
        var amount = me.record.get('invoiceAmount') * 100;
        props.message = me.snippets.text;
        props.amountLabel = me.snippets.amountlabel;
        props.maxAmount = me.data.amountCaptured - me.data.amountRefunded;
        if(amount > me.data.amountCaptured)
            props.amount = me.data.amountCaptured - amount;
        else
            props.amount = me.data.amountCaptured;
        return props;
    }
});
