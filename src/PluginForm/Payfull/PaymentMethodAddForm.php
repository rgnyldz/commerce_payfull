<?php

namespace Drupal\commerce_payfull\PluginForm\Payfull;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;
use Drupal\commerce_payment\CreditCard;

class PaymentMethodAddForm extends BasePaymentMethodAddForm
{
  /**
   * {@inheritdoc}
   */
  public function buildCreditCardForm(array $element, FormStateInterface $form_state)
  {
    $element = parent::buildCreditCardForm($element, $form_state);

    $element['number']['#attributes']['class'] = ['payfull-card-number'];

    $element['owner'] = array(
      '#type' => 'textfield',
      '#title' => t('Card owner'),
      '#required' => TRUE,
      '#size' => 20,
      '#weight' => -1
    );

    $header = [
      'taksit_sayisi' => '',
      'aylik_odeme' => 'Aylık Ödeme',
      'toplam' => 'Toplam',
    ];
    $options = [
      1 => [
        'taksit_sayisi' => 'Tek Çekim',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
      2 => [
        'taksit_sayisi' => '2 Taksit',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
      3 => [
        'taksit_sayisi' => '3 Taksit',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
      4 => [
        'taksit_sayisi' => '4 Taksit',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
      5 => [
        'taksit_sayisi' => '5 Taksit',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
      6 => [
        'taksit_sayisi' => '6 Taksit',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
      7 => [
        'taksit_sayisi' => '7 Taksit',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
      8 => [
        'taksit_sayisi' => '8 Taksit',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
      9 => [
        'taksit_sayisi' => '9 Taksit',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
      10 => [
        'taksit_sayisi' => '10 Taksit',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
      11 => [
        'taksit_sayisi' => '11 Taksit',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
      12 => [
        'taksit_sayisi' => '12 Taksit',
        'aylik_odeme' => 'Aylık Ödeme',
        'toplam' => 'Toplam',
      ],
    ];
    $element['installment'] = array(
      '#type' => 'tableselect',
      '#title' => t('Taksit Seçenekleri'),
      '#input' => TRUE,
      '#js_select' => TRUE,
      '#multiple' => FALSE,
      '#sticky' => FALSE,
      '#header' => $header,
      '#options' => $options,
      '#default_value' => 1,
      '#prefix' => '<div class="credit-card-extras-info installment-block">',
      '#suffix' => '</div>',
    );

    $element['taksit_bilgilendirme'] = array(
      '#type' => 'item',
      '#markup' => '<p class="installment-desc-block">Taksit Seçenekleri kart bilgilerinizi girmenizin ardından görüntülenecektir.</p><span class="installment-loading-block">Taksit Seçenekleri bankanızdan alınıyor</span>'
    );

    $payment_gateway = $this->getEntity()->getPaymentGateway();
    $element['#attached']['drupalSettings']['commerce_payment']['payment_gateway'] = $payment_gateway->id();
    $element['#attached']['library'][] = 'commerce_payfull/commerce-payfull';

    return $element;
  }
}
