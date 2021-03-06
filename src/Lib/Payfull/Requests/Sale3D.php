<?php

namespace Drupal\commerce_payfull\Lib\Payfull\Requests;

use Drupal\commerce_payfull\Lib\Payfull\Config;
use Drupal\commerce_payfull\Lib\Payfull\Validate;
use Drupal\commerce_payfull\Lib\Payfull\Responses\Responses;

class Sale3D extends Sale
{
    const USE3D = '1';
    private $returnUrl;

    public function __construct(Config $config)
    {
        parent::__construct($config);
    }

    public function setReturnUrl($returnUrl)
    {
        Validate::returnUrl($returnUrl);
        $this->returnUrl = $returnUrl;
    }

    public function getReturnUrl()
    {
        return $this->returnUrl;
    }

    public function execute()
    {
        $this->createRequest();
        $response = self::send($this->endpoint,$this->params);
        return Responses::process3DResponse($response);
    }

    public function createRequest()
    {
        $this->params['return_url'] = $this->returnUrl;
        $this->params['use3d']      = self::USE3D;
        parent::createRequest();
    }

}
