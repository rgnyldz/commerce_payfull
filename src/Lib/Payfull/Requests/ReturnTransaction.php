<?php


namespace Drupal\commerce_payfull\Lib\Payfull\Requests;

use Drupal\commerce_payfull\Lib\Payfull\Config;
use Drupal\commerce_payfull\Lib\Payfull\Validate;
use Drupal\commerce_payfull\Lib\Payfull\Responses\Responses;

class ReturnTransaction extends Request {

    const TYPE = 'Return';
    private $transactionId;
    private $passiveData;
    private $total;

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

    public function setTotal($total)
    {
        Validate::total($total);
        $this->total = $total;
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
        $this->params['total']              = $this->total;
        parent::createRequest();
    }

    public function execute()
    {
        $this->createRequest();
        $response = self::send($this->endpoint,$this->params);
        return Responses::processResponse($response);
    }

}
