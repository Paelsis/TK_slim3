<?php
// $CA_CERT_DIR = '/Applications/XAMPP/htdocs/app/slim/tests/_data';
$CA_CERT_DIR = __DIR__ . '/../tests/_data';
$config = [
  'displayErrorDetails' => true,
  'addContentLengthHeader' => false,
  'db' => [
     'servername' =>'localhost',
     'dbname' => 'TK',
     'username' => 'root',
     'password' => '',
  ],
  'paypal' => [
    'clientId' => 'AVBuBaPuoL2MKtlBx_5JQ-nta3vq2x_OWzi6cFk4yJwOrKpgA308cqsfE5oa0GfK3WKgvA94WSYAKF0j',
    'clientSecret'=> 'EIOXJvwJ9DqrmdFgqoRwGG7VXYKTfNGdnbOsJ0-sFOIoMvJpXvfXiLk4aDZXqbkiBkPQHAeU_grCy-ei',
  ],
  'paypalOld' => [
      'clientId' => 'AYSq3RDGsmBLJE-otTkBtM-jBRd1TCQwFf9RGfwddNXWz0uFU9ztymylOhRS',
      'clientSecret'=> 'EGnHDxD_qRPdaLdZz8iCr8N7_MzF-YHPTkjs6NKYQvQSBngp4PTTVWkPZRbL',
  ],
  'bambora' => [
    'accessToken' => 'I1LRc3FQaRocgI2lJDck',
    'merchantNumber' => 'T566348901',
    'secretToken' => 'MKzfAIivTjk76yBsHO121Wj6fcpBGehFhumWbGqs',
    'checkoutUrl' => 'https://api.v1.checkout.bambora.com/sessions',
    'callbackUrl'=>'https://nyasidan.tangokompaniet.com/app/slim/public',
  ],
  'swish' => [
    // 'checkoutUrl' => 'https://mss.cpc.getswish.net/swish-cpcapi/api/v1/paymentrequests/',
    // 'refundsUrl' => 'https://mss.cpc.getswish.net/swish-cpcapi/api/v1/refunds/',
    'environment'=>'test',
    'rootCert' => $CA_CERT_DIR . '/' . 'root.pem',
    'clientCert' => $CA_CERT_DIR . '/' . 'client.pem',
    'password' => 'swish',
    'payeeAlias' => '1231181189',
  ],
  'tk' => [
    'url'=>'localhost:3000',
    'apiBaseUrl'=>'tk.local:8080',
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
  ],
];