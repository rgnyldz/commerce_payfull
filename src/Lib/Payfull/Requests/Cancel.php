<?php

namespace Drupal\commerce_payfull\Lib\Payfull\Requests;

use Drupal\commerce_payfull\Lib\Payfull\Config;
use Drupal\commerce_payfull\Lib\Payfull\Validate;
use Drupal\commerce_payfull\Lib\Payfull\Responses\Responses;

class Cancel extends Request {

    const TYPE = 'Cancel';
    private $transactionId;
    private $passiveData;

    public function __construct(Config $config)
    {
        parent::__construct($config, self::TYPE);
    }

    public function setPassiveData($passiveData)
    {
        Validate::passiveData($passiveData);
        $this->passiveData = $passiveData;
    }

    public function setTransactionId($transactionId)
    {
        Validate::transactionId($transactionId);
        $this->transactionId = $transactionId;
    }

    public function setMerchantTrxId($merchant_trx_id)
    {
        Validate::transactionId($merchant_trx_id);
        $this->merchantTrxId;
    }

    protected function createRequest()
    {
        $this->params['passive_data']       = $this->passiveData;
        $this->params['transaction_id']     = $this->transactionId;
        parent::createRequest();
    }

    public function execute()
    {
        $this->createRequest();
        $response = self::send($this->endpoint,$this->params);
        return Responses::processResponse($response);
    }
}
