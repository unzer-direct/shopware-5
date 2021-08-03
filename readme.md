# UnzerDirect Payment Plugin for Shopware #
This plugin enables UnzerDirect as payment option for Shopware
## Installation ##
The plugin can easily be installed by following the steps below:
- Clone the repository into a folder named *UnzerDirectPayment* inside Shopwares *custom/plugins* directory.
- Open the Plugin Manager in the Shopware backend
- Select **Installed** from the Menu on the side and look for the UnzerDirect Payment plugin in the list of uninstalled plugins
- Install the plugin
- Activate the plugin

## Updating ##
To update the plugin follow these steps
- Pull the latest version
- Open the Plugin Manager in the backend
- Find the UnzerDirect Payment plugin
- Click on the update icon (Local update)

## Configuration ##
Configuration of the plugin is done as for every Shopware plugin by opening up the detail view from the Plugin Manager or via the Basic Settings window.
The UnzerDirect Payment plugin has the following settings:

|  Name        | Descritpion                                   |
| ------------ | --------------------------------------------- |
|  Public Key  | The API key for the UnzerDirect integration      |
|  Private Key | The private key for the UnzerDirect integration  |
|  Test mode   | Configure wether the test mode is enabled. With test mode enabled payments using the UnzerDirect [test data](https://learn.unzerdirect.net/tech-talk/appendixes/test/ "test data") are possible.  |


The public and private key can be found in the UnzerDirect management panel under Settings->Integration

In order to use the UnzerDirect payment method the it has to be activated using the Payment methods window in the Shopware backend. Enabling it for different shipping methods might also be necessary

## Backend functionality ##
The following actions can be performed in the Shopware backend:

#### Orders List ####
The plugin adds an additional column to the list or orders in the Shopware Backend. If the UnzerDirect payment status of an order allows capturing this column will contain an icon-button indicating this possibility. Updon clicking the icon a confimation window will be opened. After entering the amount to be captured (or leaving the preselected full amount) the capture can be confirmed and will be sent to the UnzerDirect API

Additionaly the plugin extends the options provided by the Batch processing button. Upon selecting one or multiple orders and opening the Batch processing window one additional dropdown will be present. It can be used to select a UnzerDirect action (capture, cancel or refund) performed for all selected orders. When processing the changes the respective requests to the UnzerDirect API will be performed for each of the orders. Capture and refund will always request the full amount, when using the batch processing functionality.

#### UnzerDirect panel ####
When opening the detail window for an order in the backend a new UnzerDirect tab has been added to the List. If the order was completed using UnzerDirect as the payment method, this tab is enabled and can be selected to access the UnzerDirect panel.

This panel contains a List containing the History of the UnzerDirect payment. That means every requested operation by the user (capture/cancel/refund) and every callback response from the UnzerDirect server is logged and displayed there.

In addition abore this list the following four buttons are present:

| Button   | Functionality                                      |
| -------- | -------------------------------------------------- |
| Capture  | Send a capture request to the UnzerDirect API         |
| Cancel   | Cancel a payment that has not been captured yet    |
| Refund   | Refund a payment that has already been captured    |
| Reload   | Refresh the history and the status of the payment  |


Each button is enabled or disabled according to the current status of the UnzerDirect payment.
Clicking either of the first three buttons will open a window to confirm this operation. When capturing or refunding partial the amount can be entered to make partial captures/refunds a possibility.
