//{namespace name="plugins/unzerdirect"}

Ext.define('Shopware.apps.Order.UnzerDirect.view.batch.Form', {
    override: 'Shopware.apps.Order.view.batch.Form',

    unzerdirectSnippets: {
        action: {
            capture: '{s name=order/batch/capture}Capture payment(s){/s}',
            cancel: '{s name=order/batch/cancel}Cancel payment(s){/s}',
            refund: '{s name=order/batch/refund}Refund payment(s){/s}',
            label: '{s name=order/batch/action}UnzerDirect action{/s}'
        }
    },

    /**
     * @override
     */
    createFormFields: function () {
        var me = this;

        var fields = me.callParent(arguments);
        
        return Ext.Array.insert(fields, 4, [me.createUnzerDirectActionField()]);
    },
    
    /**
     * Creates the "UnzerDirect Action" field
     *
     * @returns Ext.form.field.ComboBox
     */
    createUnzerDirectActionField: function () {
        var me = this,
            store = new Ext.data.SimpleStore({
                fields: [
                    'value',
                    'description'
                ],
                data: [
                    ['capture', me.unzerdirectSnippets.action.capture],
                    ['cancel', me.unzerdirectSnippets.action.cancel],
                    ['refund', me.unzerdirectSnippets.action.refund]
                ]
            });

        return Ext.create('Ext.form.field.ComboBox', {
            name: 'unzerdirectAction',
            triggerAction: 'all',
            fieldLabel: me.unzerdirectSnippets.action.label,
            editable: true,
            typeAhead: true,
            minChars: 2,
            emptyText: me.snippets.selectOption,
            store: store,
            snippets: me.snippets,
            displayField: 'description',
            valueField: 'value',
            validateOnBlur: true,
            validator: me.validateComboboxSelection,
            listeners: {
                scope: me,
                afterrender: this.disableAutocompleteAndSpellcheck
            }
        });
    }
});