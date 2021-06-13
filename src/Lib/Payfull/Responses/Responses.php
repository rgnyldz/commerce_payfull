<?php

namespace Drupal\commerce_payfull\Lib\Payfull\Responses;


class Responses
{
    public static function processResponse($response)
    {
        return json_decode($response,TRUE);
    }

    public static function process3DResponse($response)
    {
        if(strpos($response, '<form'))
        {
            $data = [
              'form' => $response,
              'status' => 1
            ];
            return $data;
        } else {
            return self::processResponse($response);
        }
    }
}
