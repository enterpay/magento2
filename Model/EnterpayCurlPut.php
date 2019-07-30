<?php

namespace Solteq\Enterpay\Model;

use Magento\Framework\HTTP\ZendClient;

/**
 * Class EnterpayCurlPut
 *
 * This class is used to make invoice activate request to enterpay api.
 * Magento 2.1.18 can't send PUT requests with @var ZendClient
 *
 * @see \Magento\Framework\HTTP\Adapter\Curl::write()
 *
 * @package Solteq\Enterpay\Model
 */
class EnterpayCurlPut
{
    public function request($url, $data)
    {
      $ch = curl_init($url);

      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'PUT' );
      curl_setopt($ch, CURLOPT_POSTFIELDS,     http_build_query($data) );
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

      $result = curl_exec($ch);
      curl_close($ch);

      return $result;
    }
}
