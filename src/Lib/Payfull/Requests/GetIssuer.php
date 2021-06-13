<?php

namespace Drupal\commerce_payfull\Lib\Payfull\Requests;

use Drupal\commerce_payfull\Lib\Payfull\Config;
use Drupal\commerce_payfull\Lib\Payfull\Validate;
use Drupal\commerce_payfull\Lib\Payfull\Responses\Responses;

class GetIssuer extends Request
{
    const TYPE = 'Get';
    const GETPARAM = 'Issuer';
    private $bin;

    public function __construct(Config $config)
    {
        parent::__construct($config, self::TYPE);
    }

    public function setBin($bin)
    {
        Validate::bin($bin);
        $this->bin = $bin;
    }

    protected function createRequest()
    {
        $this->params['get_param']  = self::GETPARAM;
        $this->params['bin']        = $this->bin;
        parent::createRequest();
    }

    public function execute()
    {
        $this->createRequest();
        $response = self::send($this->endpoint,$this->params);
        return Responses::processResponse($response);
    }
}
