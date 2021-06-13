<?php

namespace Drupal\commerce_payfull\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\commerce_price\Price;
use Drupal\commerce_payfull\Lib\Payfull\Config;
use Drupal\commerce_payfull\Lib\Payfull\Requests\Sale3D;
use Drupal\commerce_payfull\Lib\Payfull\Models\Customer;
use Drupal\commerce_payfull\Lib\Payfull\Models\Card;
use Drupal\commerce_payfull\Lib\Payfull\Requests\GetIssuer;
use Drupal\commerce_payfull\Lib\Payfull\Requests\Installments;
use Drupal\commerce_payfull\Lib\Payfull\Requests\ReturnTransaction;

/**
 * Provides the Payfull payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "payfull",
 *   label = "Payfull",
 *   display_label = "Payfull",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_payfull\PluginForm\Payfull\PaymentMethodAddForm"
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 *   requires_billing_information = TRUE,
 * )
 */
class Payfull extends OnsitePaymentGatewayBase implements SupportsRefundsInterface
{
  public function buildConfigurationForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('This is the api key from the Payfull panel.'),
      '#default_value' => $this->configuration['api_key'],
      '#required' => TRUE,
    ];

    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret key'),
      '#description' => $this->t('The secret key from the Payfull panel.'),
      '#default_value' => $this->configuration['secret_key'],
      '#required' => TRUE,
    ];

    $form['subdomain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subdomain'),
      '#description' => $this->t('The API Url from the Payfull panel.'),
      '#default_value' => $this->configuration['subdomain'],
      '#required' => TRUE,
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    $this->configuration['api_key'] = $values['api_key'];
    $this->configuration['secret_key'] = $values['secret_key'];
    $this->configuration['subdomain'] = $values['subdomain'];
  }

  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details)
  {
    $parameters = \Drupal::routeMatch()->getParameters();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $parameters->get('commerce_order');
    $payment_method->setReusable(false);
    $payment_method->save();

    $backUrl = \Drupal\Core\Url::fromRoute('commerce_payfull.checkout.return', ['commerce_order' => $order->id(), 'step' => 'payment']);
    $baseUrl = \Drupal::request()->headers->get('X-Forwarded-Proto', 'https') . '://' . \Drupal::request()->getHttpHost();

    $binData = $this->getInstallments($order, $payment_details['number'], true);
    $orderTotal = $order->getTotalPrice()->getNumber();
    foreach ($binData['installments'] as $price) {
      if ($price['installmentNumber'] == $payment_details["installment"]) {
        $orderTotal = $price['totalPrice'];
        break;
      }
    }

    $config = new Config();
    $config->setApiKey($this->configuration['api_key']);
    $config->setApiSecret($this->configuration['secret_key']);
    $config->setApiUrl("https://" . $this->configuration['subdomain'] . ".payfull.com/integration/api/v1");

    $request = new Sale3D($config);
    $request->setPaymentTitle('Web Sipariş #' . $order->id());
    $request->setPassiveData($order->id());
    $request->setMerchantTrxId($order->id());
    $request->setCurrency('TRY');
    $request->setTotal('1'/*$orderTotal*/);
    $request->setInstallment($payment_details["installment"] ?? 1);
    if ((($payment_details['installment'] ?? 1) > 1) && $binData['bankInfo']['bank'] ?? false) {
      $request->setBankId($binData['bankInfo']['bank']);
    }
    if ((($payment_details['installment'] ?? 1) > 1) && $binData['bankInfo']['gateway'] ?? false) {
      $request->setGateway($binData['bankInfo']['gateway']);
    }
    $request->setReturnUrl($baseUrl . $backUrl->toString());

    $paymentCard = new Card();
    $paymentCard->setCardHolderName($payment_details["owner"]);
    $paymentCard->setCardNumber($payment_details["number"]);
    $paymentCard->setExpireMonth($payment_details["expiration"]["month"]);
    $paymentCard->setExpireYear($payment_details["expiration"]["year"]);
    $paymentCard->setCvc($payment_details["security_code"]);
    $request->setPaymentCard($paymentCard);

    $billingProfile = $payment_method->getBillingProfile();
    $billingAddress = $billingProfile->get('address')->first();
    $phoneNumber = $billingProfile->hasField('field_phone_number') ? ($billingProfile->get('field_phone_number')->value ?? '111111111') : '111111111';
    $customer = new Customer();
    $customer->setName($billingAddress->given_name);
    $customer->setSurname($billingAddress->family_name);
    $customer->setEmail($order->getEmail());
    $customer->setPhoneNumber($phoneNumber);
    $request->setCustomerInfo($customer);

    $response = $request->execute();
    if (($response['status'] ?? 0) != 1) {
      \Drupal::messenger()->addError($response['ErrorMSG']);
      throw new HardDeclineException($response['ErrorMSG']);
    }
    else {
      (new Response($response['form']))->send();
    }
  }

  public function onReturn(OrderInterface $order, Request $request) {
    $attributes = [
      'type', 'status', 'ErrorMSG', 'ErrorCode', 'transaction_id', 'passive_data', 'original_currency', 'currency', 'total', 'original_total', 'conversion_rate', 'bank_id', 'use3d', 'installments', 'time', 'confirm_action', 'hash'
    ];
    $result = [];
    foreach ($attributes as $attribute) {
      $result[$attribute] = $request->request->get($attribute);
    }
    if (($result['status'] ?? 0) != 1) {
      $order->set('payment_method', null);
      \Drupal::messenger()->addError($result['ErrorMSG']);
      throw new HardDeclineException($result['ErrorMSG']);
    }
    $paymentMethod = $order->get('payment_method')->entity;
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entityTypeManager->getStorage('commerce_payment')->create(
      [
        'payment_method' => $paymentMethod->id(),
        'payment_gateway' => $paymentMethod->getPaymentGateway(),
        'order_id' => $order->id(),
        'remote_id' => $result['transaction_id']
      ]
    );
    $payment->setRemoteId($result['transaction_id']);
    $payment->setAmount(new Price(strval($result['total']), 'TRY'));
    $this->createPayment($payment, false);
  }

  public function createPayment(PaymentInterface $payment, $capture = TRUE)
  {
    $payment->setState('completed');
    $payment->save();
  }

  public function deletePaymentMethod(PaymentMethodInterface $payment_method)
  {

  }

  public function refundPayment(PaymentInterface $payment, ?Price $amount = NULL)
  {
    $config = new Config();
    $config->setApiKey($this->configuration['api_key']);
    $config->setApiSecret($this->configuration['secret_key']);
    $config->setApiUrl("https://" . $this->configuration['subdomain'] . ".payfull.com/integration/api/v1");

    $request = new ReturnTransaction($config);
    $request->setTransactionId($payment->getRemoteId());
    $request->setTotal($amount->getNumber());
    $response = $request->execute();
    if (($response['status'] ?? 0) != 1) {
      \Drupal::messenger()->addError($response['ErrorMSG']);
      throw new HardDeclineException($response['ErrorMSG']);
    }
    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
        $payment->setState('partially_refunded');
    } else {
        $payment->setState('refunded');
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

  public function getBinData($payment_details, OrderInterface $order)
  {
    $config = new Config();
    $config->setApiKey($this->configuration['api_key']);
    $config->setApiSecret($this->configuration['secret_key']);
    $config->setApiUrl("https://" . $this->configuration['subdomain'] . ".payfull.com/integration/api/v1");

    $requestedPrice = $order->getTotalPrice() ? $order->getTotalPrice()->getNumber() : 100;

    $request = new GetIssuer($config);
    $request->setBin(substr($payment_details['number'], 0, 6));
    $binData = $request->execute();

    $request = new Installments($config);
    $installments = $request->execute();

    $bankInfo = null;
    foreach($installments['data'] as $temp) {
			if($temp['bank'] == $binData['data']['bank_id']) {
				$bankInfo = $temp;
				break;
			}
		}
    if ($bankInfo == null) {
      $bankInfo = [
        'installments' => [['count' => 1, 'commission' => '0%']]
      ];
    }


    foreach($bankInfo['installments'] as $key => $installment) {
      $percentage = rtrim($installment['commission'], '%');
      $bankInfo['installments'][$key]['per_installment'] = ($requestedPrice * (100 + $percentage) / 100)/$installment['count'];
      $bankInfo['installments'][$key]['total'] = $requestedPrice * (100 + $percentage) / 100;
    }


    return ['installments' => $bankInfo['installments'], 'cardFamily' => $binData['data']['bank_id'] ?? 'generic', 'bankInfo' => $bankInfo];
  }

  public function getInstallments(OrderInterface $order, string $creditCardNumber, bool $withBankInfo = false)
  {
    $data = $this->getBinData(array('number' => $creditCardNumber), $order);
    $installments = [];
    $prices = $data['installments'];
    foreach ($prices as $price) {
      $priceData = [
        'installmentNumber' => $price['count'],
        'installmentPrice' => $price['per_installment'],
        'totalPrice' => $price['total']
      ];
      $installments[] = $priceData;
    }
    if ($withBankInfo == false) {
      return $installments;
    }
    else {
      return ['installments' => $installments, 'bankInfo' => $data['bankInfo']];
    }
  }
}
