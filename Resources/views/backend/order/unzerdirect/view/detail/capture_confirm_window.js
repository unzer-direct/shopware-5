//{namespace name="plugins/unzerdirect"}
Ext.define('Shopware.apps.Order.UnzerDirect.view.detail.CaptureConfirmWindow',
{
    extend: 'Shopware.apps.Order.UnzerDirect.view.detail.ConfirmWindow',
    
    alias: 'widget.order-unzerdirect-capture-confirm-window',
    cls: Ext.baseCSSPrefix + 'order-unzerdirect-capture-confirm-window',
    snippets: {
        title: '{s name=order/capture_confirm_window/title}Capture payment{/s}',
        text: '{s name=order/capture_confirm_window/text}Check the amount and press confirm to send the capture request to the UnzerDirect API.{/s}',
        amountLabel: '{s name=order/capture_confirm_window/amount_label}Amount:{/s}'
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
        var amount = Math.min(me.record.get('invoiceAmount') * 100, me.data.amountAuthorized);
        props.message = me.snippets.text;
        props.amountLabel = me.snippets.amountlabel;
        props.maxAmount = me.data.amountAuthorized - me.data.amountCaptured;
        props.amount = amount - me.data.amountCaptured;
        return props;
    }
});
