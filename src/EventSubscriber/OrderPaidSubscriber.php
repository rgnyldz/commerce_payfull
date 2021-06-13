<?php

namespace Drupal\commerce_payfull\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPaidSubscriber implements EventSubscriberInterface
{

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents()
  {
    $events = [
      'commerce_order.order.paid' => ['onPaid', -999],
    ];
    return $events;
  }
  public function onPaid(OrderEvent $event)
  {
    $order = $event->getOrder();
    if ($order->getData('interest', array('applied' => false))['applied'] == false) {
      $vade = 0;
      /** @var \Drupal\commerce_payment\PaymentStorage $payment_storage */
      $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment');
      $payments = $payment_storage->loadMultipleByOrder($order);
      $payments = array_filter($payments, function ($payment) {
        return $payment->getState()->value == 'completed';
      });
      if (count($payments) == 1) {
        $payment = reset($payments);
        $vade = $payment->getAmount()->subtract($order->getTotalPrice())->getNumber();
        if ($vade > 0) {
          $vadeData = array(
            'amount' => $vade,
            'applied' => true
          );
          $order->setData('interest', $vadeData);
          commerce_payfull_calculate_interest_via_order($order, $vade);
        }
      }
    }
  }
}
