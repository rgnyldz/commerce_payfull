<?php
namespace Drupal\commerce_payfull\Lib\Payfull\Requests;

use Drupal\commerce_payfull\Lib\Payfull\Config;
use Drupal\commerce_payfull\Lib\Payfull\Responses\Responses;


class ExtraInstallments extends Request {

    const TYPE = 'Get';
    const GETPARAM = 'ExtraInstallmentsList';

    public function __construct(Config $config)
    {
        parent::__construct($config, self::TYPE);
    }

    protected function createRequest()
    {
        $this->params['get_param']  = self::GETPARAM;
        parent::createRequest();
    }

    public function execute()
    {
        $this->createRequest();
        $response = self::send($this->endpoint,$this->params);
        return Responses::processResponse($response);
    }

}
