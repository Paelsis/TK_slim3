<?php
$config = [
  'displayErrorDetails' => true,
  'addContentLengthHeader' => false,
  'db' => [
     'servername' =>'mysql85.unoeuro.com', // Web address to surftowns database
     'username' => 'tangokompaniet_com',
     'password' => 'px9fem6a',
     'dbname' => 'tangokompaniet_com_db2',
  ],
  'bambora' => [
    'accessToken' => 'I1LRc3FQaRocgI2lJDck',
    'merchantNumber' => 'T566348901',
    'secretToken' => 'MKzfAIivTjk76yBsHO121Wj6fcpBGehFhumWbGqs',
    'checkoutUrl' => 'https://api.v1.checkout.bambora.com/sessions',
  ],
  'tk' => [
    'url'=>'https://pertest.tangokompaniet.com',
    'apiBaseUrl'=>'https://pertest.tangokompaniet.com/app/slim/public',
  ]  
    /*
  'logger' => [
      'name' => 'slim-app',
      'level' => Monolog\Logger::DEBUG,
      'path' => '../logs/tangokompaniet.log',
  ],
  */
];