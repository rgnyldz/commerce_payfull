<?php

namespace Drupal\commerce_payfull\Lib\Payfull\Requests;

use Drupal\commerce_payfull\Lib\Payfull\Config;
use Drupal\commerce_payfull\Lib\Payfull\Models\Customer;
use Drupal\commerce_payfull\Lib\Payfull\Models\Card;
use Drupal\commerce_payfull\Lib\Payfull\Validate;
use Drupal\commerce_payfull\Lib\Payfull\Responses\Responses;

class Sale extends Request
{
    const TYPE = 'Sale';
    private $paymentTitle;
    private $passiveData;
    private $currency;
    private $total;
    private $installment;
    private $bankId;
    private $gateway;
    private $merchantTrxId;

    public function __construct(Config $config)
    {
        parent::__construct($config, self::TYPE);
    }

    public function setPaymentCard(Card $paymentCard)
    {
        $this->params['cc_name']    = $paymentCard->getCardHolderName();
        $this->params['cc_number']  = $paymentCard->getCardNumber();
        $this->params['cc_month']   = $paymentCard->getExpireMonth();
        $this->params['cc_year']    = $paymentCard->getExpireYear();
        $this->params['cc_cvc']     = $paymentCard->getCvc();
    }

    public function setCustomerInfo(Customer $customerInfo)
    {
        $this->params['customer_firstname'] = $customerInfo->getName();
        $this->params['customer_lastname']  = $customerInfo->getSurname();
        $this->params['customer_email']     = $customerInfo->getEmail();
        $this->params['customer_phone']     = $customerInfo->getPhoneNumber();
        $this->params['customer_tc']        = $customerInfo->getTcNumber();
    }

    public function setCurrency($currency)
    {
        Validate::currency($currency);
        $this->currency = $currency;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function setTotal($total)
    {
        Validate::total($total);
        $this->total = $total;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function setPaymentTitle($paymentTitle)
    {
        Validate::paymentTitle($paymentTitle);
        $this->paymentTitle = $paymentTitle;
    }

    public function getPaymentTitle()
    {
        return $this->paymentTitle;
    }

    public function setPassiveData($passiveData)
    {
        Validate::passiveData($passiveData);
        $this->passiveData = $passiveData;
    }

    public function getPassiveData()
    {
        return $this->passiveData;
    }

    public function setInstallment($installment)
    {
        Validate::installment($installment);
        $this->installment = $installment;
    }

    public function getInstallment()
    {
        return $this->installment;
    }

    public function setBankId($bankId)
    {
        Validate::bankId($bankId);
        $this->bankId = $bankId;
    }

    public function getBankId()
    {
        return $this->bankId;
    }

    public function setGateway($gateway)
    {
        Validate::gateway($gateway);
        $this->gateway = $gateway;
    }

    public function getGateway()
    {
        return $this->gateway;
    }

    public function setMerchantTrxId($merchant_trx_id)
    {
        Validate::transactionId($merchant_trx_id);
        $this->merchantTrxId = $merchant_trx_id;
    }

    protected function createRequest()
    {
        $this->params['payment_title']      = $this->paymentTitle;
        $this->params['passive_data']       = $this->passiveData;
        $this->params['currency']           = $this->currency;
        $this->params['total']              = $this->total;
        $this->params['installments']       = $this->installment;
        $this->params['bank_id']            = $this->bankId;
        $this->params['gateway']            = $this->gateway;
        if(isset($this->merchantTrxId)){
           $this->params['merchant_trx_id'] = $this->merchantTrxId;
        }
        parent::createRequest();
    }

    public function execute()
    {
        $this->createRequest();
        $response = self::send($this->endpoint,$this->params);
        return Responses::processResponse($response);
    }

}
