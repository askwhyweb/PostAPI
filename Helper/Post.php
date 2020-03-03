<?php

namespace OrviSoft\Cloudburst\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Post
 *
 * @package OrviSoft\Cloudburst\Helper
 */
class Post extends AbstractHelper
{

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return true;
    }

    function pushData($url, $postData){
        set_time_limit(0);
        $postData = array_merge(['secret' => $this->scopeConfig->getValue('cloudburst/options/burst_secret',ScopeInterface::SCOPE_STORE)], $postData);
        
        $fields = (is_array($postData)) ? http_build_query($postData) : $postData;
        //open connection
        $ch = curl_init();
        $headers = [];
        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-length:' . strlen($fields)]);
        curl_setopt($ch, CURLOPT_POST, count($postData));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 400); //timeout in seconds
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 40000);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION,
            function($curl, $header) use (&$headers)
            {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                return $len;

                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );

        //execute post
        $result = curl_exec($ch);
        if(curl_errno($ch))
        {
            echo 'Curl error: ' . curl_error($ch);
        }
        //close connection
        curl_close($ch);
        return $result;
    }
}

