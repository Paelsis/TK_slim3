<?php

namespace App\Controllers;
use HelmutSchneider\Swish\Client;
use HelmutSchneider\Swish\PaymentRequest;
// use GuzzleHttp\Handler\StreamHandler;

const STATUS_OK = 200;
const STATUS_CREATED = 201;
const STATUS_ACCEPTED = 202;
const STATUS_ERROR = 204;
const ENVIRONMENT_PRODUCTION='production';

class TkSwishController extends Controller
{
    protected function _mailSubject($orderId, $language, $status) {
        $TkMailController = $this->container['TkMailController'];
        return $TkMailController->mailSubject($orderId, $language, $status); 
    }    

    protected function _mailBody($orderId, $language) {
        $TkMailController = $this->container['TkMailController'];
        return $TkMailController->mailBody($orderId, $language); 
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
        if ($orderId !== "") {
            $order["id"] = $orderId;
        } else {
            $order["id"] = rand(100,1000000);
        }    
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

    protected function _createClient() 
    {
        // Certificates
        $swish = $this->container['settings']['swish'];
        $environment = $swish['environment'];
        $rootCert = $swish['rootCert'];
        $clientCert = [$swish['clientCert'], $swish['password']];
        $this->_checkFiles($swish);

        // .pem-bundle containing your client cert and it's corresponding private key. forwarded to guzzle's "cert" option
        // you may use an empty string for "password" if you are using the test certificates.
        // The argument to Client:: is SWISH_PRODUCTION_URL or SWISH_TEST_URL
        $handler = new \GuzzleHttp\Handler\StreamHandler();

        if ($environment === ENVIRONMENT_PRODUCTION) {
            $client = Client::make($rootCert, $clientCert, Client::SWISH_PRODUCTION_URL, $handler);
        } else {   
            $client = Client::make($rootCert, $clientCert, Client::SWISH_TEST_URL, $handler);
        }   
        return $client; 
    }

    protected function _createPaymentRequestInput($order)
    {
        $tk = $this->container['settings']['tk'];
        $swish = $this->container['settings']['swish'];
        $pr = new PaymentRequest([
            'payeePaymentReference' => $order['id'],
            'callbackUrl' => $tk['apiBaseUrl'] . '/paymentCallbackSwish',
            'payerAlias' => $order['payerAlias'],
            'payeeAlias' => $swish['payeeAlias'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'message' => 'Betalning till Tangokompaniet'
         ]);
        return($pr);
    }      

    protected function _swishPaymentRequest($order)
    {
        $client = $this->_createClient(); 
        $this->logger->info('Client created');

        $pr = $this->_createPaymentRequestInput($order);    
        $this->logger->info('SWISH payment request input object created');

        $id = $client->createPaymentRequest($pr);
        $this->logger->info('SWISH payment request created and id returned id:' . $id);

        return($id);
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


    protected function _paymentCallback($arr) 
    {
            
        $this->logger->addDebug('--- paymentCallback ---');
        $this->_logObject($arr);

        if ($arr['status'] === 'PAID') {
            $paidAmount = $arr['amount'];
        } else {
            $paidAmount = 0;
        }    


        // Update table tbl_order with $paidAmount, but only if not updated before
        $language='EN';
        $orderId=$arr['payeePaymentReference'];
        $token=$arr['id'];
        $status=$arr['status'];
        $data=array('paidAmount'=>$paidAmount, 'currency'=>$arr['currency'], 'status'=>$arr['status']);
        $where = 'orderId=' . $orderId . ' and token=' . '\'' . $token . '\'';

        $sql=$this->_build_sql_update('tbl_order', $data, $where);
        $this->logger->info('sql = ' . $sql);
        if (!$this->_sqlExecute($sql)) {
            $message='Failed to update orderId ' . $orderId . ' with paidAmount = ' . $arr['amount']/100 . ' ' . $arr['currency'];
            $this->logger->error($message);
            return(false);
        }

        // Fetch order
        $order = $this->_fetchOrder($orderId);

        if ($order !== null) {        
            // Send email to customer  
            $recipient = $order['email'];
            $subject = $this->_mailSubject($orderId, $language, $status);
            $body = $this->_mailBody($orderId, $language);
            if (!$this->_sendMail($subject, $body, $recipient)) {
                $this->logger->warning('Failed to send mail successful payment of orderId ' . $orderId);
            } 
            $this->logger->addDebug('success _paymentCallback orderId:' . $orderId . ' amount:' . $arr['amount'] . ' currency:' . $arr['currency'] . ' recipient:' . $recipient);
            return(true);
        } else {
            // Send email to order@tangokompaniet.com 
            $tk = $this->container['settings']['tk'];
            $recipient=$tk['email']['replyTo'][0];
            $subject = "AUTOMATIC: Order $orderId was not found";
            $body = "<h4>The order $orderId was not found in database.</h4>";
            if (!$this->_sendMail($subject, $body, $recipient)) {
                $this->logger->warning('Failed to send mail missing order with orderId ' . $orderId);
            } 
            $this->logger->error('Order ' . $orderId . ' not found in database');
            return(false);
        }    
    }    

    public function testPaymentCallback($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        
        $err = $this->_checkMandatoryInput($allGetVars, ['id', 'payeePaymentReference', 'amount', 'currency', 'status']);

        if ($err !== null) {
            return $response->withJson(array('code'=>406, 'message'=>$err), 406);
        } else if ($this->_paymentCallback($allGetVars)) {
            return $response->withJson(array('code'=>200, 'message'=>'OK'), 200);
        } else {    
            return $response->withJson(array('code'=>406, 'message'=>'Bad request. Payment with order id ' . $allGetVars['payeePaymentReference'] . ' not registered in database or id mismatch.'), 406);
        }
    }    

    // Callback from SWISH 
    public function paymentCallback($request, $response) 
    {
        $input = $request->getParsedBody();

        $err = $this->_checkMandatoryInput($allGetVars, ['id', 'payeePaymentReference', 'amount', 'currency', 'status']);
        
        if ($err !== null) {
            return $response->withJson(array('code'=>406, 'message'=>$err), STATUS_ERROR);
        } else if ($this->_paymentCallback($input)) {
            return $response->withJson(array('code'=>200, 'message'=>'OK'), STATUS_OK);
        } else {    
            return $response->withJson(array('code'=>406, 'message'=>'Bad request. Payment with order id ' . $input['payeePaymentReference'] . ' not registered in database or id mismatch.'), 406);
        }
    }    



    public function paymentRequest($request, $response)
    {
        $input = $request->getParsedBody();
        $data = $input['data'];
        $order=$data['order'];
        $language=$data['language'];

        // Execute the swish request
        $token=$this->_swishPaymentRequest($order);

        if (isset($token) && strlen($token) > 10) {
            $this->logger->addDebug('Before _updateOrder paymentCheckout');
            if ($this->_updateOrder($order, $token)) {
                $this->logger->addDebug('After _updateOrder paymentCheckout');
                return $response->withJson(array('status' => 'OK', 
                                                'message='=>$message, 
                                                'orderId'=>$order['id'],    
                                                'result'=>$token), 
                        STATUS_CREATED);
            } else {
                $this->logger->error('Failed in _updateOrder paymentCheckout:');
                return $response->withJson(array('status' => 'WARNING', 
                                                'message'=>'Token created but order not updated',
                                                'orderId'=>$order['id'],    
                                                'result'=>$token), 
                        STATUS_CREATED);
            }    
        } else {   
            return $response->withJson(array('status' => 'ERROR', 
                                        'message'=>'No token created', 
                                        'orderId'=>$order['id'],    
                                        'result'=>$token),
                        STATUS_ERROR); 
        }    
    }

    public function testPaymentRequest($request, $response)
    {
        $allGetVars = $request->getQueryParams();
        if (isset($allGetVars['orderId'])) {
            $orderId = $allGetVars['orderId'];
        } else {
            $orderId = "";
        }   

        // Simulate order
        $order = $this->_simulateOrder($orderId);
        $language = 'SV';
        
        // Execute the request
        $token=$this->_swishPaymentRequest($order);

        if (isset($token) && strlen($token) > 10) {
            if ($this->_updateOrder($order, $token)) {
                $this->logger->addDebug('After _updateOrder paymentCheckout');
                return $response->withJson(array('status' => 'OK', 
                    'message'=>'Token created',
                    'orderId'=>$order['id'],    
                    'result'=>$token), 
                    STATUS_CREATED);
            } else {
                $this->logger->error('Failed in _updateOrder paymentCheckout:');
                return $response->withJson(array('status' => 'ACCEPTED', 
                    'message'=>'Token created but order not updated', 
                    'orderId'=> $order['id'],    
                    'result'=> $token),
                     STATUS_CREATED);
            }    
        } else {
            $this->logger->error('Failed in _updateOrder paymentCheckout:');
            return $response->withJson(array('status' => 'ERROR',
                                             'message'=> 'No token created', 
                                             'orderId'=> $order['id'],    
                                             'result'=> $token), 
                                             STATUS_ERROR);
        }    
    }
};  
