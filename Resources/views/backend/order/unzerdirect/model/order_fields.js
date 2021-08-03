//{namespace name="plugins/unzerdirect"}

//{block name="backend/order/model/order/fields" append}
	{ name: 'unzerdirect_payment_id', type: 'string', defaultValue: null },
        { name: 'unzerdirect_payment_status', type: 'int' },
        { name: 'unzerdirect_amount_authorized', type: 'int' },
        { name: 'unzerdirect_amount_captured', type: 'int' },
        { name: 'unzerdirect_amount_refunded', type: 'int' },
//{/block}
