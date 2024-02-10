<?php

namespace App\Controllers;

use PayPal\Api\Amount; 
use PayPal\Api\Details; 
use PayPal\Api\Item; 
use PayPal\Api\ItemList; 
use PayPal\Api\Payer; 
use PayPal\Api\Payment; 
use PayPal\Api\RedirectUrls; 
use PayPal\Api\Transaction;

define("RETURN_URL", "http://localhost:3000");
define("CANCEL_URL", "http://localhost:8080");



class TkPaypalController extends Controller
{
    protected function _create_item_list($products)
    {
        $itemArr = []; 
        foreach ($products as $p) {
            $item = new Item(); 
            $item->setName($p['productType'] . ' ' . $p['name']) 
                ->setCurrency('SEK') 
                ->setQuantity(1) 
                ->setSku($p['productId'])
                ->setPrice($p['price']); 
            $itemArr[] = $item;
        }   
        $itemList = new ItemList();
        $itemList->setItems($itemArr);
        return($itemList);
    }

    protected function _create_details() 
    {
        $details = new Details(); 
        $details->setShipping(1.5) ->setTax(1) ->setSubtotal(17.50);        
        return($details);    
    }
    
    protected function _create_payment($am, $cu) 
    {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        $details = $this->_create_details();

        /* Expressed in minimum units (öre) */
        $amMin = $am * 100; 

        $amount = new Amount();
        $amount->setTotal($amMin)
            ->setCurrency($cu)
        //    ->setDetails($details)
        ; 
            
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            //  ->setItemList($itemList) 
            ->setDescription("Payment Tangokompaniet") 
            //  ->setInvoiceNumber(uniqid());
        ;

        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl(constant("RETURN_URL"))
             ->setCancelUrl(constant("CANCEL_URL"));

        $payment = new Payment();
        $payment->setIntent('sale')
            ->setPayer($payer)
            ->setTransactions(array($transaction))
            ->setRedirectUrls($redirectUrls);
     
        return($payment);    
    }   

    protected function _setupLogfile()
    {
        $apiContext = $this->container['apiContext'];

        // Apply loggings into file PayPal.log
        $apiContext->setConfig(
            array(
                'log.LogEnabled' => true,
                'log.FileName' => '../logs/PayPal.log',
                'log.LogLevel' => 'DEBUG'
            )
        );
    }
    
    public function payment($request, $response) 
    {
        $amount='20';
        $currency='SEK';
        $payment=$this->_create_payment($amount, $currency);

        $this->_setupLogfile();

        // 4. Make a Create Call and print the values
        try {
            $apiContext = $this->container['apiContext'];
            $payment->create($apiContext);
            $this->logger->addDebug('Payment:' . $payment);
            $this->logger->addDebug("\n\nRedirect user to approval_url: " . $payment->getApprovalLink() . "\n");
            echo $payment;
            //return $response->withJson(array('status' => 'true','payment'=>$payment, 'approvalLink'=>$payment->getApprovalLink()) ,200);
        }
        catch (\PayPal\Exception\PayPalConnectionException $ex) {
            // This will print the detailed information on the exception.
            //REALLY HELPFUL FOR DEBUGGING
            $this->logger->error($ex->getData());
            return $response->withJson(array('status' => 'ERROR'),422);
        }      
    }
}