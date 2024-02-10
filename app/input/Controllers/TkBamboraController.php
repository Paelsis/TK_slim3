<?php

namespace App\Controllers;

define('ERR_SERVICE_UNAVAILABLE', 503);
define('ERR_SERVICE_UNAVAILABLE_TEXT', 'Service Unavailable');

define('ERR_GATEWAY_TIMEOUT', 504);
define('ERR_GATEWAY_TIMEOUT_TEXT', 'Gateway timeout');

class TkBamboraController extends Controller
{
    protected function _mailSubject($orderId, $language, $status) {
        $TkMailController = $this->container['TkMailController'];
        return $TkMailController->mailSubject($orderId, $language, $status); 
    }    

    protected function _mailBody($orderId, $language) {
        $TkMailController = $this->container['TkMailController'];
        return $TkMailController->mailBody($orderId, $language); 
    }
   
    protected function _build_sql_insert($table, $data) 
    {
        $key = array_keys($data);
        $val = array_values($data);
        $sql = "INSERT INTO $table (" . implode(', ', $key) . ") "
             . "VALUES ('" . implode("', '", $val) . "')";
     
        return($sql);
    }

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
        if ($orderId === null) {
            $orderId = 1;
        }

        $order = array();
        $order["id"] = $orderId;
        $order["amount"] = (rand(1,100)*100 + rand(1,10))*100; 
        $order["currency"] = "SEK";
        return($order);
    }

    protected function _callbackUrl($type, $order, $language, $accessToken)
    { 
        $tk = $this->container['settings']['tk'];
        $tkUrl = $tk['callback']['url'];
        $apiBaseUrl = $tk['callback']['apiBaseUrl'];

        $callbackUrl = $apiBaseUrl . '/paymentCallbackBanbora' . '?' . 'accessToken=' . $accessToken 
        . '&' . 'orderId=' . $order['id'] 
        . '&' . 'amount=' . $order['amount']
        . '&' . 'currency=' . $order['currency']
        . '&' . 'language=' . $language
        . '&' . 'type=' . $type
        ;

        return($callbackUrl);
    }    

    protected function _bamboraCreateRequest($order, $language)
    {
        $type = 'bambora';
        $orderId = $order['id'];
        $amount = $order['amount'];
        $currency = $order['currency'];
        $this->logger->addDebug('orderId:' . $orderId);
        $this->logger->addDebug('amount:' . $amount);
        $this->logger->addDebug('currency:' . $currency);

        // Fetch bambora setting from config file
        $bambora = $this->container['settings']['bambora'];
        $accessToken = $bambora['accessToken'];

        // Fetch apiBaseUrl from config file       
        $tk = $this->container['settings']['tk'];
        $tkUrl = $tk['callback']['url'];
        $apiBaseUrl = $tk['callback']['apiBaseUrl'];

        // Set the 3 URL-s
        $acceptUrl = $tkUrl . '/paymentAccept' . '/' . $orderId . '/' . $amount . '/' . $currency . '/' . $language;
        $cancelUrl = $tkUrl . '/paymentCancel' . '/' . $orderId . '/' . $language;
        $callbackUrl = $this->_callbackUrl($type, $order, $language, $accessToken);
        $this->logger->addDebug('apiBaseUrl:' . $apiBaseUrl);
        $this->logger->addDebug('acceptUrl:' . $acceptUrl);
        $this->logger->addDebug('cancelUrl:' . $cancelUrl);
        $this->logger->addDebug('callbackUrl:' . $callbackUrl);

        // Must be expressed in minimum amount (öre) when sent as order to bambora
        $order['amount']=$order['amount']*100;

        // Create the Bambora request
        $bamboraRequest = array();
        $bamboraRequest["order"] = $order;
        $bamboraRequest["url"] = array();
        $bamboraRequest["url"]["accept"] = $acceptUrl;
        $bamboraRequest["url"]["cancel"] = $cancelUrl;
        $bamboraRequest["url"]["callbacks"] = array();
        $bamboraRequest["url"]["callbacks"][] = array("url" => $callbackUrl);

        $this->logger->addDebug('callbackUrl:' . $callbackUrl);

        /*
        $bamboraRequest["url"]["callbacks"][] = array("url" => $apiBaseUrl . '/bamboraCallback/$orderId' 
            . '?' . 'accessToken=' . $accessToken 
            . '&' . 'orderId=' . $orderId 
            . '&' . 'amount=' . $amount
            . '&' . 'currency=' . $currency);
        */    
        return($bamboraRequest);
    }


    protected function _bamboraCreateApiKey() 
    {
        // Get the keys from config file
        $bambora = $this->container['settings']['bambora'];
        $accessToken = $bambora['accessToken'];
        $merchantNumber = $bambora['merchantNumber'];
        $secretToken = $bambora['secretToken'];

        // Create the apiKey
        $apiKey = base64_encode(
            $accessToken . "@" . $merchantNumber . ":" . $secretToken
        );

        $this->logger->addDebug('accessToken:' . $accessToken);
        $this->logger->addDebug('merchantNumber:' . $merchantNumber);
        $this->logger->addDebug('secretToken:' . $secretToken);
        $this->logger->addDebug('apiKey:' . $apiKey);

        return($apiKey);
    }

    protected function _bamboraExecuteRequest($request, $apiKey)
    {
        $requestJson = json_encode($request);
        $contentLength = isset($requestJson) ? strlen($requestJson) : 0;
        
        $this->logger->addDebug('BAMBORA requtestJson:' . $requestJson);

        // Fetch bambora setting from config file
        $bambora = $this->container['settings']['bambora'];
        $checkoutUrl = $bambora['checkoutUrl'];

        // Create header
        $headers = array(
            'Content-Type: application/json',
            'Content-Length: ' . $contentLength,
            'Accept: application/json',
            'Authorization: Basic ' . $apiKey
        );

        // Create curl command
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestJson);
        curl_setopt($curl, CURLOPT_URL, $checkoutUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        // Execite curl command
        $rawResult = curl_exec($curl);
        if ($rawResult != null) {
            $result = json_decode($rawResult);
            return($result);
        } else {
            return(null);
        }    
    }

    protected function UNUSED_updateOrder($orderId, $amount, $currency, $accessToken) 
    {
        $table='tbl_order';
        $sql = "UPDATE $table SET amount=:amount, currency=:currency WHERE orderId=:orderId";
        $con = $this->db;
        $sth = $con->prepare($sql);
        $sth->bindParam(':orderId', $orderId);
        $sth->bindParam(':amount', $amount);
        $sth->bindParam(':currency', $currency);
        if ($this->_sthExecute($sth)) {
            $numberOfAffectedRows=$sth->rowCount();
            if ($numberOfAffectedRows > 0) 
            {
                return true;
            } else {
                $this->logger->addWarning('Wrong Token:' . $accessToken . ' for orderId ' . $orderId . ' => nothing updated');
                return false;
            }    
        } else{
            return false;
        }
    }

    private function _updateOrder($order, $token)
    {
        $this->logger->addDebug('_updateOrder'); 

        $sql = "UPDATE tbl_order SET amount=:amount, discount=:discount, currency=:currency, token=:token WHERE orderId=:orderId";

        $this->logger->addDebug('_updateOrder: Save in table tbl_order amount:' . $order['amount'] .
             ', discount=' . $order['discount'] . ' and currency=' . $order['currency'] . 
             ' orderId:' . $order['id'] . ' token:' . $token . ' sql:' . $sql);

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

    // callback
    public function UNUSED_paymentCallback($request, $response) 
    {
        $orderId = $request->getParam('orderId');
        $amount = $request->getParam('amount');
        $currency = $request->getParam('currency');
        $accessToken = $request->getParam('accessToken');
        if ($this->UNUSED_updateOrder($orderId, $amount, $currency, $accessToken)) 
        {
            $this->logger->addDebug('OK: successful update of tblOrder with orderId=' . $orderId . ' amount=' . $amount . ' accessToken=' . $accessToken);
            return $response->withJson(array('status' => 'OK', 'orderId' => $orderId, 'amount' => $amount, 'accessToken' => $accessToken), 200);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'orderId' => $orderId, 'amount' => $amount, 'accessToken' => $accessToken, 'message'=>'Failed to update tblOrder with amount'), 200);
            $this->logger->addDebug('ERROR: failed to update tbl_order with orderId=' . $orderId . ' amount=' . $amount . ' accessToken=' . $accessToken);
        }    
    }


    public function getPaymentRequest($request, $response)
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['orderId'])) {
            $orderId = $allGetVars['orderId'];
        } else {
            $orderId = null;
        }   
        
        // Get the keys from config file
        $apiKey=$this->_bamboraCreateApiKey();

        // Create request
        $order = $this->_simulateOrder($orderId);
        
        $paymentRequest=$this->_bamboraCreateRequest($order, $language);

        // Execute the request
        $result=$this->_bamboraExecuteRequest($paymentRequest, $apiKey);

        if ($this->_updateOrder($order, $result->token)) {
            return $response->withJson(array('status' => 'OK', 'order'=>$order, 'result'=>$result), 200);
        } else {            
            return $response->withJson(array('status' => 'FALSE', 'order'=>$order, 'result'=>$result), constant('ERR_SERVICE_UNAVAILABLE'));
        }
    }

    public function paymentRequest($request, $response)
    {
        $input = $request->getParsedBody();
        $data=$input['data'];
        $order = $data['order'];
        $language = $data['language'];

        // Get the keys from config file
        $apiKey=$this->_bamboraCreateApiKey();

        $bamboraRequest=$this->_bamboraCreateRequest($order, $language);

        // Execute the bambora request
        $this->logger->addDebug('Before _bamboraExecuteRequest');
        $result=$this->_bamboraExecuteRequest($bamboraRequest, $apiKey);
        $this->logger->addDebug('After _bamboraExecuteRequest');

        if ($result === null) {
            return $response->withJson(array('status' => 'ERROR',
             'message'=>'Bambora failed to execute request order id:' . $order['id'], 
             'result'=>$result), 500);
        } else {   
            $this->logger->addDebug('Before _updateOrder paymentCheckout');
            if ($this->_updateOrder($order, $result['token'])) {
                $this->logger->addDebug('After _updateOrder paymentCheckout');
                return $response->withJson(array('status' => 'OK', 'result'=>$result), 200);
            } else {
                $this->logger->error('Failed in _updateOrder paymentCheckout:');
                return $response->withJson(array('status' => 'ERROR',
                'message'=>constant('ERR_SERVICE_UNAVAILABLE_TEXT'), 
                'result'=>$result), constant('ERR_SERVICE_UNAVAILABLE'));
            }    
        }    
    }

    // After tge payment was done
    public function paymentCallback($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        $this->logger->addDebug('--- paymentCallback ---');
        $this->_logObject($allGetVars);

        if (array_key_exists('amount', $allGetVars)) {
                $amount=$allGetVars['amount']/100;
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
            $subject = $this->_mailSubject($orderId, $language, $status);
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
    

