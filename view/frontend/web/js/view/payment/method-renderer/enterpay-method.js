/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'mage/storage',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/get-totals',
        'Magento_Checkout/js/model/url-builder',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/totals',
        'Magento_Ui/js/model/messageList',
        'mage/translate',
    ],
    function (ko, $, storage, Component, placeOrderAction, quote, getTotalsAction, urlBuilder, mageUrlBuilder, fullScreenLoader, customer, totals, messageList, $t) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Solteq_Enterpay/payment/enterpay'
            },
            redirectAfterPlaceOrder: false,

            getInstructions: function() {
              return window.checkoutConfig.payment.instructions[this.item.method];
            },

            getPaymentUrl: function() {
              return window.checkoutConfig.payment.payment_redirect_url[this.item.method];
            },

            addErrorMessage: function(msg) {
              messageList.addErrorMessage({
                message: msg
              });
            },

            placeOrder: function() {
              var self = this;

              placeOrderAction(self.getData(), self.messageContainer).done(function () {
                $.ajax({
                  url: mageUrlBuilder.build(self.getPaymentUrl()),
                  type: 'post',
                  context: this,
                  data: { 'is_ajax': true }
                }).done(function(response) {
                  if ($.type(response) == 'object' && response.success && response.data) {
                    $('#enterpay-form-wrapper').append(response.data);
                    return false;
                  }
                  self.addErrorMessage($t('An error occurred on the server. Please try to place the order again.'));
                }).fail(function() {
                  self.addErrorMessage($t('An error occurred on the server. Please try to place the order again.'));
                }).always(function() {
                  fullScreenLoader.stopLoader();
                });
              });
            }
        });
    }
);
