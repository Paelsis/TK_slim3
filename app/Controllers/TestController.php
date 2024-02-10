<?php

namespace App\Controllers;
use HelmutSchneider\Swish\Client;
use HelmutSchneider\Swish\PaymentRequest;
// use GuzzleHttp\Handler\StreamHandler;

define('ERR_SERVICE_UNAVAILABLE', 503);
define('ERR_SERVICE_UNAVAILABLE_TEXT', 'Service Unavailable');

define('ERR_GATEWAY_TIMEOUT', 504);
define('ERR_GATEWAY_TIMEOUT_TEXT', 'Gateway timeout');

define("ENVIRONMENT_PRODUCTION", 'production');
define("ENVIRONMENT_TEST", 'test');


class TestController extends Controller
{


    protected function _execute($sql) 
    {
        try {
            $con = $this->db;
            $sth = $con->prepare($sql);
            $sth->execute();
            $this->logger->addDebug('OK: Successful INSERT:' . $sql);
        } catch(\Exception $ex) {
            $this->logger->addDebug('ERROR: Failed to execute SQL-statement:' . $sql);
            return false;
        }
        return true;
    }    

    // Reduce object to only contain the fields given by names of array $keys
    protected function _objectReduceToKeys($obj, $keys)
    {
        $reduced_obj=array();
        foreach($keys as $f) {
            if (array_key_exists($f, $obj)) {
                // $this->logger->addDebug('OK: $f=' . $f . ' $p[$f]=' . $p[$f]);
                $reduced_obj[$f] = $obj[$f];
            } 
        }    
        return $reduced_obj;
    }


    // Reduce the objects of an array $arr to only include the fields given by names of array $keys     
    protected function _arrayReduceToKeys($arr, $keys) 
    {
        $reducedArr=array();
        foreach($arr as $p) {
            $rp = $this->_objectReduceToKeys($p, $keys);
            array_push($reducedArr, $rp);
        } 
        return $reducedArr; 
    }

    protected function _simulateOrder($orderId)
    {
        $order = array();
        $order["id"] = $orderId;
        $order["amount"] = 3; 
        $order["currency"] = "SEK";
        $order["payerAlias"] = "46733780749";
        $order["discount"] = 0;
        return($order);
    }

    protected function _checkFiles($swish) 
    {
        if (file_exists($swish['rootCert'])) {
            $this->logger->addDebug('rootCert found');
        } else {
            $this->logger->addDebug('Root certificate ' . $swish['rootCert'] . ' not found');
            exit('Root certificate ' . $swish['rootCert'] . ' not found');
        }

        if (file_exists($swish['clientCert'])) {
            $this->logger->addDebug('Client certificate found');
        } else {
            $this->logger->addDebug('Client certificate ' . $swish['clientCert'] . ' not found');
            exit('Client certificate ' . $swish['clientCert'] . ' not found');
        }
        return(true);
    }    

    protected function _callbackUrl($type, $order, $language, $accessToken)
    { 
        $tk = $this->container['settings']['tk'];
        $apiBaseUrl = $tk['callback']['apiBaseUrl'];

        $callbackUrl = $apiBaseUrl . '/paymentCallback' . '?' . 'accessToken=' . $accessToken 
        . '&' . 'orderId=' . $order['id'] 
        . '&' . 'amount=' . $order['amount']
        . '&' . 'currency=' . $order['currency']
        . '&' . 'language=' . $language
        . '&' . 'type=' . $type
        ;

        return($callbackUrl);
    }    

    protected function _swishPaymentRequest($order, $language)
    {
        $type = 'swish';
        $tk = $this->container['settings']['tk'];

        // Certificates
        $swish = $this->container['settings']['swish'];
        $accessToken=$swish['accessToken'];
        $environment = $swish['environment'];
        $rootCert = $swish['rootCert'];
        $clientCert = [$swish['clientCert'], $swish['password']];
        $this->_checkFiles($swish);

        // .pem-bundle containing your client cert and it's corresponding private key. forwarded to guzzle's "cert" option
        // you may use an empty string for "password" if you are using the test certificates.
        // The argument to Client:: is SWISH_PRODUCTION_URL or SWISH_TEST_URL
        $handler = new \GuzzleHttp\Handler\StreamHandler();

        if ($environment === constant('ENVIRONMENT_PRODUCTION')) {
            $client = Client::make($rootCert, $clientCert, Client::SWISH_PRODUCTION_URL, $handler);
        } else {   
            $client = Client::make($rootCert, $clientCert, Client::SWISH_TEST_URL, $handler);
        }    
        $callbackUrl = $this->_callbackUrl($type, $order, $language, $accessToken);
        $pr = new PaymentRequest([
            'payeePaymentReference' => $order['id'],
            'callbackUrl' => $callbackUrl,
            'payerAlias' => $order['payerAlias'],
            'payeeAlias' => $swish['payeeAlias'],
            'amount' => 2, // $order['amount'],
            'currency' => $order['currency'],
            'message' => 'Betalning till Tangokompaniet'
         ]);

        $token = $client->createPaymentRequest($pr);

        return($token);
    }

    private function _updateOrder($order, $token)
    {
        $this->logger->addDebug('_updateOrder'); 

        $sql = "UPDATE tbl_order SET amount=:amount, discount=:discount, currency=:currency, token=:token  WHERE orderId=:orderId";

        $this->logger->addDebug('_updateOrder: Save in table tbl_order amount:' . $order['amount'] .
             ', discount=' . $order['discount'] . ' and currency=' . $order['currency'] . 
             ' orderId:' . $order['id'] . ' sql:' . $sql);

        $db = $this->db;
        $sth = $db->prepare($sql);
        $sth->bindParam(':orderId', $order['id']);
        $sth->bindParam(':amount', $order['amount']);
        $sth->bindParam(':discount', $order['discount']);
        $sth->bindParam(':currency', $order['currency']);
        $sth->bindParam(':token', $token);
        if ($this->_sthExecute($sth)) {
            $numberOfAffectedRows=$sth->rowCount();
            if ($numberOfAffectedRows > 0) {
                return true;
            } else {
                $this->logger->warning('The orderId ' . $order['id'] . 
                    ' does not exist in table tbl_order. Therfore the access token will not be updated');
                return false;
            }    
        } else {
            $this->logger->error('Failed to execute statement:' . $sql);
            return false;
        }
    }

    // accept
    public function paymentAccept($request, $response) 
    {
        $input = $request->getParsedBody();
        $data=$input['data'];
        $this->logger->addDebug('OK: bamboraAccept successful');
        return $response->withJson(array('status' => 'OK'), 200);
    }

    // cancel
    public function paymentCancel($request, $response) 
    {
        $input = $request->getParsedBody();
        $data=$input['data'];
        $this->logger->addDebug('OK: bamboraAccept successful');
        return $response->withJson(array('status' => 'OK'), 200);
    }

    public function getPaymentRequest($request, $response)
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['orderId'])) {
            $orderId = $allGetVars['orderId'];
        } else {
            $orderId = 1;
        }   

        $order = $this->_simulateOrder($orderId);
        $language = 'SV';
        
        // Execute the request
        $token=$this->_swishPaymentRequest($order, $language);

        if (isset($token) && strlen($token) > 10) {
            $this->_updateOrder($order, $token);
            return $response->withJson(array('status' => 'OK', 'result'=>$token), 200);
        } else {
            $this->logger->error('Failed in _updateOrder paymentCheckout:');
            return $response->withJson(array('status' => 'ERROR',
                'message'=>constant('ERR_SERVICE_UNAVAILABLE_TEXT'), 
                'result'=>$token), constant('ERR_SERVICE_UNAVAILABLE'));
        }    
    }

    public function paymentRequest($request, $response)
    {
        $input = $request->getParsedBody();
        $data = $input['data'];
        $order=$data['order'];
        $language=$data['language'];

        // Execute the swish request
        $token=$this->_swishPaymentRequest($order, $language);

        if (isset($token) && strlen($token) > 10) {
            $this->logger->addDebug('Before _updateOrder paymentCheckout');
            if ($this->_updateOrder($order, $token)) {
                $this->logger->addDebug('After _updateOrder paymentCheckout');
                return $response->withJson(array('status' => 'OK', 'result'=>$token), 200);
            } else {
                $this->logger->error('Failed in _updateOrder paymentCheckout:');
                return $response->withJson(array('status' => 'ERROR',
                'message'=>constant('ERR_SERVICE_UNAVAILABLE_TEXT'), 
                'result'=>$token), constant('ERR_SERVICE_UNAVAILABLE'));
            }    
        } else {   
            return $response->withJson(array('status' => 'ERROR',
            'message'=>'Bambora failed to execute request order id:' . $order['id'], 
            'result'=>$token), 500);
        }    
    }

    // After tge payment was done
    public function paymentCallback($request, $response) 
    {
       $allGetVars = $request->getQueryParams();
       
       "id": "AB23D7406ECE4542A80152D909EF9F6B",
       "payeePaymentReference": "0123456789",
       "paymentReference": "6D6CD7406ECE4542A80152D909EF9F6B",
       "callbackUrl": "https://example.com/api/swishcb/paymentrequests",
       "payerAlias": "46701234567",
       "payeeAlias": "1231234567890",
       "amount": "100",
       "currency", "SEK",
       "message": "Kingston USB Flash Drive 8 GB",
       "status": "PAID",
       "dateCreated": "2015-02-19T22:01:53+01:00",
       "datePaid": "2015-02-19T22:03:53+01:00"
       }
       

       if (array_key_exists('type', $allGetVars)) {
           $type=$allGetVars['type'];
       } else {
           $type='swish';
       }

       if (array_key_exists('amount', $allGetVars)) {
           if ($type === 'swish') {
               $amount=$allGetVars['amount'];
           } else {
               $amount=$allGetVars['amount']/100;
           }    
       } else {
           $amount=0;
       }

       if (array_key_exists('accessToken', $allGetVars)) {
           $accessToken=$allGetVars['accessToken'];
       } else {
           $accessToken = null;
       }

       // Check if anyone added an id in callback params
       if (array_key_exists('id', $allGetVars)) {
           $id=$allGetVars['id'];
           $this->logger->info('Found id:' . $id);
       } 

       // Check if token exists in callback params
       if (array_key_exists('id', $allGetVars)) {
           $id=$allGetVars['id'];
           $this->logger->info('Found token:' . $token);
       } 

       $language='SV';
       if (array_key_exists('language', $data)) {
           $language=$data['language'];
       }    

       $orderId=$allGetVars['orderId'];

       // Check that the passed accessToken matches the one in settings
       if ($accessToken !== $this->container['settings']['accessToken']) {
           $this->logger->error('Wrong accessToken. Final accept mail to customer not sent');
           return $response->withJson(array('status' => 'WARNING', 'message'=>'accessToken mismatch, payment not registered'),500);
       }    

       // Update table tbl_order with $paidAmount, but only if not updated before
       $data=array('paidAmount'=>$amount);
       $sql=$this->_build_sql_update('tbl_order', $data, "orderId='$orderId'");
       if (!$this->_sqlExecute($sql)) {
           $this->logger->error('Failed to update orderId ' . $orderId . ' with paidAmount = ' . $amount);
           return $response->withJson(array('status' => 'WARNING', 'message'=>'accessToken mismatch'),500);
       }

       // Get config variable $tk            
       $tk = $this->container['settings']['tk'];

       // Fetch order
       $order = $this->_fetchOrder($orderId);
       if ($order !== null) {        
           // Send email to customer  
           $recipient = $order['email'];
           $subject = $this->_mailSubject($orderId, $language);
           $body = $this->_mailBody($orderId, $language);
           $this->_sendMail($subject, $body, $recipient); 
       } else {
           $this->logger->error('Order ' . $orderId . ' tried to access order with token ');
           // Send email to order@tangokompaniet.com 
           $recipient=$this->$tk['email']['replyTo'][0];
           $subject = "AUTOMATIC: Order $orderId was not found";
           $body = "<h4>The order $orderId was not found in database.</h4>";
           $this->_sendMail($subject, $body, $recipient); 
       }    
       $this->logger->addDebug('paymentCallback orderId:' . $orderId . ' amount:' . $amount . ' currency:' . $currency . ' recipient:' . $recipient);

       $apiBaseUrl = $tk['apiBaseUrl'];
       $this->logger->addDebug('paymentCallback apiBaseUrl:' . $apiBaseUrl);
       $reply=array(
           'code'=>200, 
           'accessToken'=>$accessToken, 
           'orderId'=>$orderId, 
           'amount'=>$amount, 
           'currency'=>$currency, 
           'recipient'=>$recipient,
           'message'=>'OK');

       return $response->withJson($reply, 200);
   }  
};  

};    

