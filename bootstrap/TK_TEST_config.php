<?php
$CA_CERT_DIR = '../prod/_data';
$WEB_SITE='https://www.tangokompaniet.com';
$config = [
  'displayErrorDetails' => true,
  'addContentLengthHeader' => false,
  'db' => [
    'servername' =>'mysql85.unoeuro.com', // Web address to surftowns database
    'username' => 'tangokompaniet_com',
    'password' => 'px9fem6a',
    'dbname' => 'tangokompaniet_com_db_test',
  ],
  'tk' => [
    'url'=>$WEB_SITE,
    'apiBaseUrl'=>$WEB_SITE . '/app/slim/public',
    'callback' => [
        'url'=>$WEB_SITE,
        'apiBaseUrl'=>$WEB_SITE . '/app/slim/public',
    ],
    'email' => [
      'host' => 'smtp.unoeuro.com',  // outgoing SMTP mail server
      'from' => ['order@tangokompaniet.com', 'Order Tangokompaniet'],
      'replyTo' => ['order@tangokompaniet.com', 'Reply to Tangokompaniet'],
      'password' => 'Takes22tango!'
    ],
  ]  
];