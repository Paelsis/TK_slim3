<?php
namespace App\Controllers;

const COMPANY_NAME='Tangokompaniet';
const BANKGIRO="5532-8223";
const SWISH="123 173 30 05";
const BIC="SWEDSESS";
const IBAN="SE59 8000 0821 4994 3833 6324";
const EMAIL_DANIEL='daniel@tangokompaniet.com';

const colorDefault="#81185B";

const TEXTS=[
    "THIS_IS_A_CONFIRMATION"=>[
        'SV'=>"Detta är en bekräftelse på din beställning hos ",
        'EN'=>"This is a confirmation of your order at ",
        'ES'=>"This is a confirmation of your order at ",
    ],
    "YOUR_ORDER_ID"=>[
        'SV'=>"Din beställning har nummer: ",
        'EN'=>"Your order has number: ",
        'ES'=>"Your order has number: ",
    ],
    "PARTIAL_PAYMENNT"=>[
        'SV'=>"Vi har erhållit delbetalning på din order.",
        'EN'=>"We have recieved partial payment on your order",
        'ES'=>"Gracias por comprar en",
    ],
    "FULL_PAYMENT"=>[
        'SV'=>"Tack för din betalning. Detta är kvitto på att din order är betald.",
        'EN'=>"This is a recipet that you have paid the full amount.",
        'ES'=>"This is a recipet that you have paid the full amount.",
    ],
    "THANKS"=>[
        'SV'=>"Tack för att du handlar hos oss på",
        'EN'=>"Thank you for shopping at",
        'ES'=>"Gracias por comprar en",
    ],
    "CONFIRMED"=>[
        'SV'=>"Din bokning är bekräftad",
        'EN'=>"Your booking is confirmed",
        'ES'=>"Your order is confirmed",
    ],
    "CANCELLED"=>[
        'SV'=>"Din order har cancellerats",
        'EN'=>"Your order was cancelled",
        'ES'=>"Your order was cancelled",
    ],
    "HELP_TEXT_SWEDEN"=>[
        'SV'=>'(fungerar bara i Sverige)',
        'EN'=>'(only works in Sweden)',
        'ES'=>'(solo funciona en Suecia)',
    ],
    "SWISH"=>[
        'SV'=>"När du betalar via Internet (SWISH eller bankgiro) så vänligen ange ditt order nummer i meddelande fältet",
        'EN'=>"When you pay via Internet (swish or bank) please enter your order number in the message field",
        'ES'=>"When you pay via Internet (swish or bank) please enter your order number in the message field",
    ],

];

class MailController extends Controller
{



    protected function _color_inverse($color){
        $color = str_replace('#', '', $color);
        if (strlen($color) != 6){ return '000000'; }
        $rgb = '';
        for ($x=0;$x<3;$x++){
            $c = 255 - hexdec(substr($color,(2*$x),2));
            $c = ($c < 0) ? 0 : dechex($c);
            $rgb .= (strlen($c) < 2) ? '0'.$c : $c;
        }
        return '#'.$rgb;
    }

    
    protected function _htmlTextFromSql($sql, $color)
    {
        $rows = $this->_selectRows($sql);
        $inverseColor = $this->_color_inverse($color);

        if ($rows === null) {
            $str = "No parameters for $sql";
            return $str;            
        }
        if (count($rows) === 0) {
            return null;
        }  
        
        $keys = array();
        $str = "";
        // Find all keys that have a value
        foreach($rows as $row) {
            foreach($row as $key=>$value) {
                if ($key === 'id'|| (strpos($sql, 'where')!==false && $key === 'orderId') || strlen($value)===0 || !isset($key)) {
                    continue;
                }
                if (!in_array($key, $keys)) {
                    $keys[] = $key;
                }    
            }
        }   
        
        $str = "<table style='border: 4px solid $color; border-collapse: collapse;'>";
        $str .=  "<thead style='background-color:$color; color:$inverseColor; border: 2px solid $color; border-collapse: collapse;'>";
        foreach($keys as $key) {
            if ($key !== 'urlLocation') {
                if ($key === 'id') {
                    continue;
                }
                $str .=  '<th>' . $key .'</th>';
            }
        }    
        $str = $str . '</tr></thead><tbody>';
        foreach($rows as $row) {
            $str = $str .  "<tr style='border-bottom: 1px solid $color; color:$color; background-color:$inverseColor; border-collapse: collapse;'>";
            foreach($keys as $key) {
                if ($key === 'id') {
                    continue;
                }
                if (isset($row[$key])) {
                    $value = $row[$key];
                } else {
                    $value = '';
                }   

                if ($key === 'urlLocation') {
                    $str = $str;
                } else if (in_array($key, array('address', 'adress'))) {
                    if (isset($row['urlLocation'])) {
                        $val = $this->_urlAnchor($value, $row['urlLocation']);
                    } else {    
                        $val = $value;
                    }    

                    $str .=  '<td>' . $val . '</td>';
                } else if (in_array($key, array('veckodag', 'dayOfWeek'))) {
                    $wd = $this->_weekday($value, $language);
                    $str .=  '<td>' . $wd . '</td>';
                } else {   
                    $str .=  '<td>' . $value . '</td>';
                }    
            }   
            $str .= '</tr>';
        }
        $str .= '</table>';
        return($str);
    }


    protected function _htmlTextFromSqlRow($sql)
    {
        $rows = $this->_selectRows($sql);
        
        if (count($rows) === 0 or $rows === null) {
            return(null);
        }    
        $str = "<table style='border-bottom: 1px solid blue; border-top: 1px solid red'><tbody>";
        
        foreach($rows as $row) {
            $str = $str .  '<tr>';
            foreach($row as $key=>$value) {
                $str = $str .  '<tr><td>' . $key . '</td><td>' .$value. '</td>';
            }    
            $str = $str . '<tr>';
        }

        $str = $str . '</tbody></table>';
        return($str);
    }
    
    protected function _echoThankYou($language, $color)
    {
        echo "<h4 style='color:$color;'>";    
        echo TEXTS['THANKS'][$language] . ' ' . COMPANY_NAME;
        echo "</h4>";    
    }    

    protected function _echoPaymentInfo($orderId, $language) {
        $color = colorDefault;
        echo "<div>" . TEXTS['SWISH'][$language] . "</div>" ;
        echo "<ul>";
        echo "<li>Swish-nummer:" . SWISH . TEXTS['HELP_TEXT_SWEDEN'][$language] . "</li>";
        echo "<li>Bankgiro:" . BANKGIRO . TEXTS['HELP_TEXT_SWEDEN'][$language] . "</li>";
        echo "<li>International IBAN:" . IBAN . " BIC:" . BIC . "</li>";
        echo "</ul>";
    }

    protected function _getHtmlFromTblText($groupId, $textId, $language)
    {
        $sql="select * from `tbl_text` where `groupId` = '$groupId' and `textId` = '$textId' and `language` = '$language'";
        $rows = $this->_selectRows($sql);
        if (count($rows) > 0) {
            return $rows[0]['textBody'];
        } else {    
            return "<p>Text not found in tbl tbl_text for groupId=$groupId textId=$textId language=$language</p>";
        }    
    }

    public function mailSubject($params) {
        if (isset($params['orderId'])) {
            $orderId = $params['orderId'];
        } else {
            $orderId = -1;
        }
        if (isset($params['language'])) {
            $language = $params['language'];
        } else {
            $language='EN';
        }

        $arr=array(
            'SV'=>"Berkäftelse på order $orderId från " . COMPANY_NAME,
            'EN'=>"Confirmation on  $orderId from " . COMPANY_NAME,
            'ES'=>"Confirmacion del pedido $orderId de " . COMPANY_NAME,
        );
        $ret=$arr[$language];
        return $ret;
    }    

    protected function echoMailBody($params)
    {
        $orderId = null;
        if (isset($params['orderId'])) {
            $orderId = $params['orderId'];
            $whereClause = " where orderId=$orderId";
            unset($params['orderId']);
        } 
        if (isset($params['language'])) {
            $language = $params['language'];
            unset($params['language']);
        } else {
            $language='EN';
        }
        if (isset($params['color'])) {
            $color = $params['color'];
        } else {
            $color = "#81185B";
        }    
        if (isset($params['backgroundColor'])) {
            $backgroundColor = $params['backgroundColor'];
        } else {
            $backgroundColor = "#81185B";
        }    
        echo "<h3 style='color:$color;'>"; 
        echo TEXTS['THIS_IS_A_CONFIRMATION'][$language] . ' ' . COMPANY_NAME;
        echo '</h3>'; 
        
        if (isset($orderId)) {
            echo "<h4 style='color:$color;'>"; 
            echo TEXTS['YOUR_ORDER_ID'][$language] . $orderId;
            echo '</h4>'; 
        }

        foreach ($params as $key=>$value) {
            switch ($key) {
            case 'color':
            case 'backgroundColor':    
                break;    
            case 'sql':
                $html = $this->_htmlTextFromSql($value, $color);
                if ($html !== null) {
                    echo $html;
                } else {
                    echo "<div color:$color; style='font-size:normal;;'>"; 
                    echo "<h2>No parameters for params[$key]=$value</h2>";    
                    echo "<h3>$value</h3>";    
                    echo '</div>'; 
                }    
                echo "<br /><br />";
                break;
            default:
                if (isset($whereClause)) {
                    $sql="select * from $value $whereClause";
                } else {
                    $sql="select * from $value";
                }    
                $html = $this->_htmlTextFromSql($sql, $color);
                if ($html !== null) {
                    echo $html;
                    echo "<br />";
                } else {
                    echo "<h4 style='font-size:10px; color:#81185B;'>$key not found</h4>";    
                }    
                break;
            }    
        }
        echo "<div style='color:$color;;'>"; 
        $this->_echoPaymentInfo($orderId, $language); 
        echo '</div>'; 

        echo "<div style='color:$color; font-size:small;;'>"; 
        echo $this->_getHtmlFromTblText('REGISTRATION', 'LEGAL', $language);
        echo '</div>'; 


        $this->_echoThankYou($language, $color);
        echo "<div color:$color; style='font-size:normal;;'>"; 
        // echo $this->_getHtmlFromTblText('REGISTRATION', 'FOOTER', $language);
        echo '</div>'; 
        
    }

    protected function _createParams($queryParams) {
        $params = array();

        // Filter:Exgtract some required keys (but not all) should be transferd from $queryParams
        forEach(['orderId', 'color'] as $key) {
            if (isset($queryParams[$key])) {
                $params +=[$key=> $queryParams[$key]];
            }
        }    

        $params +=[
            'Amount'=>'v_mail_amount',
            'Customer'=>'v_mail_customer', 
            'DancePartner'=>'v_mail_dance_partner', 
            'Course'=>'v_mail_course', 
            'Package'=>'v_mail_package', 
            'Workshop'=>'v_mail_workshop'
        ];      


        return $params;
    }

    public function testMailText($request, $response)  {
        $queryParams = $request->getQueryParams();
        $params = $this->_createParams($queryParams);
        $this->echoMailBody($params);
    }

    // This function require only two $queryParameters $orderId and ($color optional)
    public function mailBody($queryParams) 
    {
        ob_start(); //Start output buffer
        $params = $this->_createParams($queryParams);
        $this->echoMailBody($params);
        $body = ob_get_contents();//Grab output
        ob_end_clean(); //Discard output buffer

        $this->logger->addDebug("mailBody: After putting into body");

        return $body;
    }

    public function testMail($request, $response)  {
        $queryParams = $request->getQueryParams();

        $orderId = 'unknown';
        if (isset($queryParams['orderId'])) {
            $orderId = $queryParams['orderId'];
        } else {
            return $response->withJson(array('status' => 'WARNING','message'=>'The orderId is missing as parameter in calling url'),200);
        }
        if (isset($queryParams['recipient'])){
            $recipient = $queryParams['recipient'];
        } else {
            return $response->withJson(array('status' => 'WARNING','message'=>'Calling "recipient" is missing as parameter in calling url'),200);
        }

        if (isset($queryParams['responsible'])){
            $responsible = $queryParams['responsible']; 
        } else {
            $responsible = EMAIL_DANIEL; 
        }

        $subject = "Confirmation of order $orderId at " . COMPANY_NAME;

        $body = $this->mailBody($queryParams);

        if ($this->_sendMail($subject, $body, $recipient)) {         
            $this->logger->addDebug("Successful mail sending to recipient $recipient for order $orderId");
            if (isset($responsible)) {
                if ($this->_sendMail($subject, $body, $respobsible)) {         
                    $this->logger->addDebug("Successful mail sending to respoinsible $resbonsibe for order $orderId");
                }    
            }
            return $response->withJson(array('status' => 'OK', 
            'message'=>'Successful mail sending',
            'subject'=>$subject, 
            'body'=>$body),200);
        } else{
            $this->logger->error('Failed mail sending to ' . $recipient);
            if ($this->_sendMail("Failed to send mail to customer $recipient for order $orderId", $body, $responsible)) {         
                $this->logger->addDebug("Successful mail sending to respoinsible $resbonsibe for order $orderId");
            }    
            return $response->withJson(array('status' => 'ERROR',
                'message'=>"Failing to send mail to $recipient",
                'subject'=>$subject, 
                'body'=>$body),200);
        }
    }    
}