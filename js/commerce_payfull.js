Drupal.behaviors.commerce_payfull = {
  attach: function (context, settings) {
    var oldCCNumber = "";
    var orderTotalPrice = jQuery('.order-total-line__total span.order-total-line-value').html();
    onCreditCardChange();

    async function getInstallments(ccNumber) {
      var paymentGatewayId = settings.commerce_payment.payment_gateway;
      return new Promise((resolve, reject) => {
        jQuery.ajax({
          type: 'POST',
          url: 'binCheck/' + paymentGatewayId,
          data: { number: ccNumber },
          success: function (response) {
            resolve(response);
          }, error: (response) => {
            reject(response);
          }
        });
      })
    }

    function renderInstallmentsTable(installments) {
      var table = '<div class="tableresponsive-toggle-columns"><button type="button" class="link tableresponsive-toggle" title="Show table cells that were hidden to make the table fit within a small screen." style="display: none;">Hide lower priority columns</button></div><table data-drupal-selector="edit-payment-information-add-payment-method-payment-details-installment" id="edit-payment-information-add-payment-method-payment-details-installment" class="responsive-enabled" data-striping="1"><thead><tr><th></th><th></th><th>Aylık Ödeme</th><th>Toplam</th></tr></thead><tbody>';

      installments.forEach(function (installment, index) {

        let installmentPrice = installment['installmentPrice'].toFixed(2).toString();

        let totalPrice = installment['totalPrice'].toFixed(2).toString();

        table += '<tr class="with-label taksit-secenek-row">';
        table += '<td><div class="js-form-item form-item js-form-type-radio form-item-payment-information-add-payment-method-payment-details-installment js-form-item-payment-information-add-payment-method-payment-details-installment">';
        table += '<input data-drupal-selector="edit-payment-information-add-payment-method-payment-details-installment-' + (index - 1) + '" type="radio" id="edit-payment-information-add-payment-method-payment-details-installment-' + (index - 1) + '" name="payment_information[add_payment_method][payment_details][installment]" value="' + installment.installmentNumber + '" class="form-radio" ' + (index == 0 ? 'checked="checked"' : '') + '">';
        table += '<label></label></div></td>';
        table += '<td class="taksit-miktari">' + (installment.installmentNumber == 1 ? 'Tek Çekim' : (installment.installmentNumber + ' Taksit')) + '</td>';
        table += '<td class="aylik-odeme">₺' + installmentPrice + '</td>';
        table += '<td class="toplam-odeme">₺' + totalPrice + '</td>';
        table += '</tr>';
      });

      table += '</tbody></table>';
      return table;
    }

    async function onCreditCardChange(event) {
      var cardInput = jQuery('.payfull-card-number');
      if (cardInput.length) {
        ccNumber = jQuery(cardInput).val();
      } else {
        ccNumber = '';
      }
      if (oldCCNumber.substr(0, 6) != ccNumber.substr(0, 6) && ccNumber.length > 5) {
        jQuery('.installment-block').hide();
        jQuery('.installment-desc-block').hide();
        jQuery('.installment-loading-block').show();
        let installmentDetails = await getInstallments(ccNumber);
        jQuery('.installment-loading-block').hide();
        jQuery('.installment-block').html(renderInstallmentsTable(installmentDetails) + '<script>jQuery(".credit-card-form .credit-card-extras-info table tbody tr").on("click", function(){jQuery(this)[0].children[0].children[0].children[0].click();});</script>').show();
        jQuery('.installment-block input').on("change", function (event) {
          var totalPrice = jQuery(this).closest('tr').find('.toplam-odeme').html();

          jQuery('.order-total-line__total span.order-total-line-value').html(totalPrice);
        });
        var totalPrice = jQuery(jQuery('.installment-block input')[0]).closest('tr').find('.toplam-odeme').html();
        jQuery('.order-total-line__total span.order-total-line-value').html(totalPrice);
      } else if (ccNumber.length < 6) {
        jQuery('.installment-block').hide();
        jQuery('.installment-loading-block').hide();
        jQuery('.installment-desc-block').show();
        jQuery('.order-total-line__total span.order-total-line-value').html(orderTotalPrice);
      }
      oldCCNumber = ccNumber;
    }

    function onPaymentMethodChange() {
      jQuery('.order-total-line__total span.order-total-line-value').html(orderTotalPrice);
    }

    jQuery('.payfull-card-number').on('keyup', Drupal.debounce(onCreditCardChange, 250, false));
    jQuery('.form-item-payment-information-payment-method input').on('change', onPaymentMethodChange);
  }
};
