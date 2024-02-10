<?php
$CA_CERT_DIR = '../prod/_data';
$config = [
  'displayErrorDetails' => true,
  'addContentLengthHeader' => false,
  'db' => [
    'servername' =>'mysql85.unoeuro.com', // Web address to surftowns database
    'username' => 'tangokompaniet_com',
    'password' => 'px9fem6a',
    'dbname' => 'tangokompaniet_com_db5',
  ],
  'bambora' => [
    'accessToken' => 'I1LRc3FQaRocgI2lJDck',
    'merchantNumber' => 'T566348901',
    'secretToken' => 'MKzfAIivTjk76yBsHO121Wj6fcpBGehFhumWbGqs',
    'checkoutUrl' => 'https://api.v1.checkout.bambora.com/sessions',
  ],
  'swish' => [
    //'checkoutUrl' => 'https://cpc.getswish.net/swish-cpcapi/api/v1/paymentrequests',
    //'refundsUrl' => 'https://mss.cpc.getswish.net/swish-cpcapi/api/v1/refunds/',
    'environment'=>'production',
    'rootCert' => $CA_CERT_DIR . '/' . 'root.pem',
    'clientCert' => $CA_CERT_DIR . '/' . 'client.pem',
    'password' => 'odrms',
    'payeeAlias' => '1234531521',
  ],
  'tk' => [
    'url'=>'https://nyasidan.tangokompaniet.com',
    'apiBaseUrl'=>'https://nyasidan.tangokompaniet.com/app/slim/public',
    'callback' => [
        'url'=>'https://nyasidan.tangokompaniet.com',
        'apiBaseUrl'=>'https://nyasidan.tangokompaniet.com/app/slim/public',
    ],
    'email' => [
      'host' => 'smtp.unoeuro.com',  // outgoing SMTP mail server
      'from' => ['order@tangokompaniet.com', 'Order Tangokompaniet'],
      'replyTo' => ['order@tangokompaniet.com', 'Reply to Tangokompaniet'],
      'password' => 'Takes22tango!'
    ],
  ]  
];