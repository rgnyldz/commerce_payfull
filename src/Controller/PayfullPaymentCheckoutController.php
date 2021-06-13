<?php

namespace Drupal\commerce_payfull\Controller;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_payment\Controller\PaymentCheckoutController;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\commerce_cart\CartSession;
use Symfony\Component\HttpFoundation\JsonResponse;



/**
 * Provides checkout endpoints for off-site payments.
 */
class PayfullPaymentCheckoutController extends PaymentCheckoutController
{

  /**
   * Provides the "return" checkout payment page.
   *
   * Redirects to the next checkout page, completing checkout.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function returnPage(Request $request, RouteMatchInterface $route_match)
  {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $route_match->getParameter('commerce_order');
    $step_id = $route_match->getParameter('step');
    $this->validateStepId($step_id, $order);
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $order->get('payment_gateway')->entity;
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    if (!method_exists($payment_gateway_plugin, 'onReturn')) {
      throw new AccessException('The payment gateway for the order does not implement onReturn method');
    }
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = $order->get('checkout_flow')->entity;
    $checkout_flow_plugin = $checkout_flow->getPlugin();

    try {
      $payment_gateway_plugin->onReturn($order, $request);
      $redirect_step_id = $checkout_flow_plugin->getNextStepId($step_id);
    } catch (PaymentGatewayException $e) {
      $this->logger->error($e->getMessage());
      $this->messenger->addError(t('Payment failed at the payment server. Please review your information and try again.'));
      $redirect_step_id = $checkout_flow_plugin->getPreviousStepId($step_id);
    }
    $checkout_flow_plugin->redirectToStep($redirect_step_id);
  }

  /**
   * Validates the requested step ID.
   *
   * Redirects to the actual step ID if the requested one is no longer
   * available. This can happen if payment was already cancelled, or if the
   * payment "notify" endpoint created the payment and placed the order
   * before the customer returned to the site.
   *
   * @param string $requested_step_id
   *   The requested step ID, usually "payment".
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @throws \Drupal\commerce\Response\NeedsRedirectException
   */
  protected function validateStepId($requested_step_id, OrderInterface $order)
  {
    $step_id = $this->checkoutOrderManager->getCheckoutStepId($order);
    if ($requested_step_id != $step_id) {
      throw new NeedsRedirectException(Url::fromRoute('commerce_checkout.form', [
        'commerce_order' => $order->id(),
        'step' => $step_id,
      ])->toString(), 301);
    }
  }

  public function binData(Request $request, RouteMatchInterface $route_match)
  {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $route_match->getParameter('commerce_order');
    $ccNumber = $request->request->get('number', '111111');
    if (!is_numeric($ccNumber) || strlen($ccNumber) < 6) {
      $ccNumber = '111111';
    }
    $paymentGateway = $route_match->getParameter('commerce_payment_gateway');
    $paymentGatewayPlugin = $paymentGateway->getPlugin();
    $installments = $paymentGatewayPlugin->getInstallments($order, $ccNumber);
    return new JsonResponse($installments);
  }

  /**
   * Checks access for the form page.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(RouteMatchInterface $route_match, AccountInterface $account)
  {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $route_match->getParameter('commerce_order');
    if ($order->getState()->getId() == 'canceled') {
      return AccessResult::forbidden()->addCacheableDependency($order);
    }

    // The user can checkout only their own non-empty orders.
    if ($account->isAuthenticated()) {
      $customer_check = $account->id() == $order->getCustomerId();
    } else {
      $cartSession = \Drupal::service('commerce_cart.cart_session');
      $active_cart = $cartSession->hasCartId($order->id(), CartSession::ACTIVE);
      $completed_cart = $cartSession->hasCartId($order->id(), CartSession::COMPLETED);
      $customer_check = $active_cart || $completed_cart;
    }

    // Skip the customer check if we're an anonymous user trying to access the
    // payment or complete step. The reason for this is that it's easy to lose
    // your session data using off-site payment gateways. This causes the order
    // to never be placed even though the payment was successful. See:
    // https://www.drupal.org/project/commerce/issues/3023417.
    $routes_to_match = ['payment', 'complete'];
    if ($account->isAnonymous() && in_array($route_match->getParameter('step'), $routes_to_match)) {
      $access = AccessResult::allowedIf($order->hasItems())
        ->andIf(AccessResult::allowedIfHasPermission($account, 'access checkout'))
        ->addCacheableDependency($order);
    } else {
      $access = AccessResult::allowedIf($customer_check)
        ->andIf(AccessResult::allowedIf($order->hasItems()))
        ->andIf(AccessResult::allowedIfHasPermission($account, 'access checkout'))
        ->addCacheableDependency($order);
    }


    return $access;
  }
}
