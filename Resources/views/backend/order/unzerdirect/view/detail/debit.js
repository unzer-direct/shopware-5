//{namespace name="plugins/unzerdirect"}
Ext.define('Shopware.apps.Order.UnzerDirect.view.detail.Debit', {
    
    override: 'Shopware.apps.Order.view.detail.Debit',
    
    unzerDirectSnippets:{
        paymentId:'{s name=debit/payment_id}UnzerDirect Payment ID{/s}',
    },

    createTopElements:function () {
        var me = this;
        
        var items = me.callParent(arguments);
        
        items.push(me.createUnzerDirectIdField())
        
        return items;
    },

    createUnzerDirectIdField: function() {
        var me = this;

        var unzerDirectId = me.record.get('unzerdirect_payment_id');

        me.unzerDirectIdField = Ext.create('Ext.form.field.Text', {
            name:'paymentId',
            fieldLabel:me.unzerDirectSnippets.paymentId,
            anchor:'97.5%',
            labelWidth: 155,
            minWidth:250,
            labelStyle: 'font-weight: 700;',
            readOnly:true,
            value: unzerDirectId,
            hidden: unzerDirectId ? false : true
        });

        return me.unzerDirectIdField;
    }
    
});
