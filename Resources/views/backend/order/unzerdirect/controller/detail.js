//{namespace name="plugins/unzerdirect"}
Ext.define('Shopware.apps.Order.UnzerDirect.controller.Detail',
{
    override: 'Shopware.apps.Order.controller.Detail',
    
    unzerdirectSnippets: {
        notifications: {
            captureSuccess: {
                title: '{s name=detail/notifications/request_success/title}Request submitted{/s}',
                message: '{s name=detail/notifications/capture_request_success/message}The capture request has been successfuly submitted.{/s}'
            },
            captureFailure: {
                title: '{s name=detail/notifications/request_failure/title}Request failed{/s}'
            },
            cancelSuccess: {
                title: '{s name=detail/notifications/request_success/title}Request submitted{/s}',
                message: '{s name=detail/notifications/cancel_request_success/message}The cancel request has been successfuly submitted.{/s}'
            },
            cancelFailure: {
                title: '{s name=detail/notifications/request_failure/title}Request failed{/s}'
            },
            refundFailure: {
                title: '{s name=detail/notifications/request_failure/title}Request failed{/s}'
            },
            refundSuccess: {
                title: '{s name=detail/notifications/request_success/title}Request submitted{/s}',
                message: '{s name=detail/notifications/refund_request_success/message}The refund request has been successfuly submitted.{/s}'
            },
            growlMessage: 'Order-UnzerDirect'
        }
    },
    
    init: function()
    {
        var me = this;
        me.unzerdirectPaymentStore = Ext.getStore('unzerdirect-payment-store');
        if (!me.unzerdirectPaymentStore) {
            me.unzerdirectPaymentStore = Ext.create('Shopware.apps.Order.UnzerDirect.store.Payment');
        }
        me.control({
            'order-detail-window order-unzerdirect-panel, order-list': {
                showCaptureConfirmWindow: me.onShowCaptureConfirmWindow,
            },
            'order-detail-window order-unzerdirect-panel': {
                showCancelConfirmWindow: me.onShowCancelConfirmWindow,
                showRefundConfirmWindow: me.onShowRefundConfirmWindow,
                reload: me.loadUnzerdirectPayment
            },
            'order-unzerdirect-capture-confirm-window order-unzerdirect-confirm-panel': {
                confirmOperation: me.onConfirmCapture,
                cancelOperation: me.onCancelCapture
            },
            'order-unzerdirect-refund-confirm-window order-unzerdirect-confirm-panel': {
                confirmOperation: me.onConfirmRefund,
                cancelOperation: me.onCancelRefund
            },
            'order-unzerdirect-cancel-confirm-window order-unzerdirect-confirm-panel': {
                confirmOperation: me.onConfirmCancel,
                cancelOperation: me.onCancelCancel
            }
        });
        me.callParent(arguments);
    },
    
     
    onShowCaptureConfirmWindow: function(data, record, source)
    {
        var me = this;
        if (me.captureConfirmWindow !== undefined) {
            me.captureConfirmWindow.destroy();
            delete me.captureConfirmWindow;
        }
        me.captureConfirmWindow = Ext.create('Shopware.apps.Order.UnzerDirect.view.detail.CaptureConfirmWindow', {
            data: data,
            record: record,
            source: source
        }).show(undefined, function() {
            this.subApplication = me.subApplication;
        });
    },
    
    onShowCancelConfirmWindow: function(data, record, source)
    {
        var me = this;
        if (me.cancelConfirmWindow !== undefined) {
            me.cancelConfirmWindow.destroy();
            delete me.cancelConfirmWindow;
        }
        me.cancelConfirmWindow = Ext.create('Shopware.apps.Order.UnzerDirect.view.detail.CancelConfirmWindow', {
            data: data,
            record: record,
            source: source
        }).show(undefined, function() {
            this.subApplication = me.subApplication;
        });
    },

    loadUnzerdirectPayment: function(id)
    {
        var me = this;
        var payment = me.unzerdirectPaymentStore.getById(id);
        if(payment)
        {
            me.unzerdirectPaymentStore.remove(payment);
        }
        var paymentModel = Ext.ModelManager.getModel('Shopware.apps.Order.UnzerDirect.model.Payment');
        paymentModel.load(id, { success: function(payment)
        {
            if(payment)
                me.unzerdirectPaymentStore.add(payment);
            
            me.unzerdirectPaymentStore.fireEvent('paymentUpdate', id);
        }});
    },

    onShowRefundConfirmWindow: function(data, record, source)
    {
        var me = this;
        if (me.refundConfirmWindow !== undefined) {
            me.refundConfirmWindow.destroy();
            delete me.refundConfirmWindow;
        }
        me.refundConfirmWindow = Ext.create('Shopware.apps.Order.UnzerDirect.view.detail.RefundConfirmWindow', {
            data: data,
            record: record,
            source: source
        }).show(undefined, function() {
            this.subApplication = me.subApplication;
        });
    },
    
    onConfirmCapture: function(values, source)
    {
        var me = this;
        Ext.Ajax.request({
            url: '{url controller="UnzerDirect" action="capture"}',
            params: values,
            success: function(response) {
                var data = Ext.JSON.decode(response.responseText);
                
                if (!data.success) {
                    var notification = me.unzerdirectSnippets.notifications.captureFailure;
                    Shopware.Notification.createGrowlMessage(notification.title, data.message, me.unzerdirectSnippets.notifications.growlMessage);
                    
                    return;
                }
                
                var notification = me.unzerdirectSnippets.notifications.captureSuccess;
                Shopware.Notification.createGrowlMessage(notification.title, notification.message, me.unzerdirectSnippets.notifications.growlMessage);
                
                source.operationFinished('capture', false);
                
                if (me.captureConfirmWindow !== undefined) {
                    me.captureConfirmWindow.destroy();
                    delete me.captureConfirmWindow;
                }
                
            }
        });  
    },
    
    onCancelCapture: function(source)
    {
        var me = this;
        if (me.captureConfirmWindow !== undefined) {
            me.captureConfirmWindow.destroy();
            delete me.captureConfirmWindow;
        }
        source.operationFinished('capture', true);
    },
    

    
    onConfirmRefund: function(values, source)
    {
        var me = this;
        Ext.Ajax.request({
            url: '{url controller="UnzerDirect" action="refund"}',
            params: values,
            success: function(response) {
                var data = Ext.JSON.decode(response.responseText);
                
                if (!data.success) {
                    var notification = me.unzerdirectSnippets.notifications.refundFailure;
                    Shopware.Notification.createGrowlMessage(notification.title, data.message, me.unzerdirectSnippets.notifications.growlMessage);
                    
                    return;
                }
                
                var notification = me.unzerdirectSnippets.notifications.refundSuccess;
                Shopware.Notification.createGrowlMessage(notification.title, notification.message, me.unzerdirectSnippets.notifications.growlMessage);
                
                source.operationFinished('refund', false);
                
                if (me.refundConfirmWindow !== undefined) {
                    me.refundConfirmWindow.destroy();
                    delete me.refundConfirmWindow;
                }
                
            }
        });  
    },

    onConfirmCancel: function(values, source)
    {
        var me = this;
        Ext.Ajax.request({
            url: '{url controller="UnzerDirect" action="cancel"}',
            params: values,
            success: function(response) {
                var data = Ext.JSON.decode(response.responseText);
                
                if (!data.success) {
                    var notification = me.unzerdirectSnippets.notifications.cancelFailure;
                    Shopware.Notification.createGrowlMessage(notification.title, data.message, me.unzerdirectSnippets.notifications.growlMessage);
                    
                    return;
                }
                
                var notification = me.unzerdirectSnippets.notifications.cancelSuccess;
                Shopware.Notification.createGrowlMessage(notification.title, notification.message, me.unzerdirectSnippets.notifications.growlMessage);
                
                source.operationFinished('cancel', false);
                
                if (me.cancelConfirmWindow !== undefined) {
                    me.cancelConfirmWindow.destroy();
                    delete me.cancelConfirmWindow;
                }
                
            }
        });  
    },
    
    onCancelCancel: function(source)
    {
        var me = this;
        if (me.cancelConfirmWindow !== undefined) {
            me.cancelConfirmWindow.destroy();
            delete me.cancelConfirmWindow;
        }
        source.operationFinished('cancel', true);
    },
    onCancelRefund: function(source)
    {
        var me = this;
        if (me.refundConfirmWindow !== undefined) {
            me.refundConfirmWindow.destroy();
            delete me.refundConfirmWindow;
        }
        source.operationFinished('refund', true);
    }
});