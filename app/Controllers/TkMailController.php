<?php
namespace App\Controllers;

const MAILTEXT = [
    'REGISTRATION'=>[
        'SV'=>'Anmälan till kurs/event hos ' . COMPANY_NAME . ' med order nummer: ', 
        'EN'=>'Registration till course/event at ' . COMPANY_NAME . ' with order number: ', 
        'ES'=>'Registration till course/event at ' . COMPANY_NAME . ' with order number: ', 
    ], 
    'EVENT_NAME' => [
        'SV'=>'Kurs/Tillställning', 
        'EN'=>'Course/Event', 
        'ES'=>'Course/Event',
    ],
    'MESSAGE_SENT_TO_TK' => [
        'SV'=>'Meddelande skickat till Tangokompaniet:', 
        'EN'=>'Message sent to Tangokompaniet:',
        'ES'=>'Message sent to Tangokompaniet:'
    ],
    'TIME' => [
        'SV'=> "Tid :" ,
        'ES'=> "Time :",
        'EN'=> "Time :" ,
    ], 
    'AT' => [
        'SV'=> "klockan" ,
        'ES'=> "at",
        'EN'=> "at" ,
    ], 
    'PRICE_PER_PERSON' => [
        'SV'=>'Pris per person', 
        'EN'=>'Price per person',
        'ES'=>'Price per person'
    ],
    'ONLY_IN_SWEDEN' => [
        'SV'=>' (Fungerar bara i Sverige)', 
        'EN'=>' (Does only work within Sweden)',
        'ES'=>' (Does only work within Sweden)',
    ],
    'TOKEN' => [
        'SV'=>'Klicka på denna länk om du vill cancellera din anmälan', 
        'EN'=>'Click on the following link to cancel your registration:',
        'ES'=>'Click on the following link to cancel your registration:',
    ],
    'THANK_YOU' => [
        'SV'=> "Tack för att du handlar hos oss på " . COMPANY_NAME,
        'EN'=> "Thank you for shopping at " . COMPANY_NAME,
        'ES'=> "Gracias por comprar en " . COMPANY_NAME,
    ], 
    'ORDER_ID_TEXT' => [
        'SV'=>'När du betalar via Internet (swish/paypal/bank) ange ditt order nummer ',
        'ES'=>'When you pay via Internet (swish/paypal/bank) enter your order number ',
        'EN'=>'When you pay via Internet (swish/paypal/bank) enter your order number ',
    ],    
    'ORDER_ID' => [
        'SV'=>' i meddelandefältet på betalningen',
        'ES'=>' in the message field of your payment',
        'EN'=>' in the message field of your payment',
    ],
    'STATUS' => [
        'OK'=>[
                 'SV'=>'accepterad', 
                 'EN'=>'accepted', 
                 'ES'=>'accepted',
         ], 
         'WL'=>[
                 'SV'=>'satt på väntlista efter ledig följare', 
                 'EN'=>'put on waitlist for a free follower', 
                 'EN'=>'put on waitlist for a free follower', 
         ], 
         'WF'=>[
             'SV'=>'satt på väntlista efter ledig förare', 
             'EN'=>'put on waitlist for a free leader', 
             'ES'=>'put on waitlist for a free leader', 
         ], 
         'WS'=>[
             'SV'=>'satt på väntelista eftersom kursen är full', 
             'EN'=>'put on waitlist since course is full', 
             'ES'=>'put on waitlist since course is full', 
         ], 
     ],
];   


/*
switch ($language) {
    case 'SV':
        $onlyInSweden = ' (fungerar bara i Sverige)';
        $onlyAnnaOnline = "Betalning för Annas online kurser";
        $allOtherCourses = 'Alla andra kurser och produkter betalas till Tangokompaniet';
        $orderIdText = "När du betalar via Internet (swish/paypal/bank) ange ditt order nummer " .  
            $orderId . " i meddelandefältet på betalningen";
        break;
    case 'ES': 
        $onlyInSweden ='(only works in Sweden)';
        $onlyAnnaOnline = "Payment for Annas ONLINE courses";
        $allOtherCourses = 'All other courses and products are paid to Tangokompaniet';
        $orderIdText = "When you pay via Internet (swish/paypal/bank) enter your order number " .  
            $orderId . " in the message field of your payment";
        break;
    default: 
        $onlyInSweden = ' (works only in Sweden)';
        $onlyAnnaOnline = "Payment for Annas ONLINE courses";
        $allOtherCourses = 'All other courses and products are paid to Tangokompaniet';
        $orderIdText = "When you pay via Internet (swish/paypal/bank) enter your order number " .  
        $orderId . " in the message field of your payment";
}
*/

class TkMailController extends Controller
{
    protected function _addressAsText($customer) 
    {
        // address
        $address='';
        $address=$address . ' ' . $customer['firstName'] . ' ' . $customer['lastName'] . '<p />';
        $address=$address . $customer['address'] . '<p />';
        $address=$address . $customer['zip'] . '  ' . $customer['city'] . '<p />';
        $address=$address . $customer['country'] . '<p /><p /><p />';
        $address=$address . 'The mail address we have received is ' . $customer['email'];
        return($address);
    }  
    

    protected function _getFullNameAndEmail($orderId)
    {
        $sql = "SELECT $name as namn, city as stad, siteName as plats, startDate as start, startTime as tid, DAYNAME(startDate) as veckodag, p.price as pris, '$currency' as valuta 
            from `tbl_order_product` as o, tbl_course as c 
            left outer join tbl_course_def as d on c.courseId = d.courseId
            left outer join tbl_site as s on c.siteId = s.siteId
            left outer join tbl_price_group p on p.priceGroup = d.priceGroup
            where o.productId = c.productId
            and o.productType = 'course'";
        if ($orderId !== 0) {
            $sql = $sql . " and o.orderId = $orderId";
        }
        return $sql;
    }


    protected function _getCoursesSqlFromOrderProduct($orderId, $language, $currency)
    {
        $name = 'name' . $language;
        $sql = "SELECT $name as namn, city as stad, address as adress, siteName as plats, 
            startDate as start, startTime as tid, DAYNAME(startDate) as veckodag, p.price as pris, '$currency' as valuta,
            s.urlLocation as urlLocation  
            from `tbl_order_product` as o, tbl_course as c 
            left outer join tbl_course_def as d on c.courseId = d.courseId
            left outer join tbl_site as s on c.siteId = s.siteId
            left outer join tbl_price_group p on p.priceGroup = d.priceGroup
            where o.productId = c.productId";
        if ($orderId !== 0) {
            $sql = $sql . " and o.orderId = $orderId";
        }
        return $sql;
    }

    protected function _getCoursesSqlFromReg($orderId, $language, $currency)
    {
        $name = 'name' . $language;
        $sql = "SELECT 
            DATE_FORMAT(DATE(SUBSTR(productId, 1,6)), '%W %d%b %Y') as date,
            CONCAT(SUBSTR(productId, 7,2),':', SUBSTR(productId, 9,2)) as time,
            'Registered dance' as event, productType, firstName, lastName, email
            FROM `tbl_registration`";
        if ($orderId !== 0) {
            $sql = $sql . " where orderId = $orderId";
        }
        return $sql;
    }

    protected function _getRegPackageSql($orderId)
    {
        /* , concat(priceSEK, ' ', 'SEK') as priceSEK , concat(priceEUR, ' ', 'EUR') as priceEUR  */
        $sql="SELECT
        f.orderId,
        IFNULL(pa.name, p.product) AS namn,
            sd.startDate AS START,
            sd.startTime AS TIME,
            DAYNAME(sd.startDate) AS WEEKDAY
            FROM
                tbl_registration_festival AS f
            LEFT OUTER JOIN tbl_registration_festival_product AS p
            ON
                p.eventType = f.eventType AND p.dateRange = f.dateRange AND p.email = f.email
            LEFT OUTER JOIN tbl_schedule_def AS sd
            ON
                sd.eventType = p.eventType
            LEFT OUTER JOIN tbl_package AS pa
            ON
                pa.packageId = p.product
            where p.productType='package'";


        if ((int) $orderId !== 0 ) {
            $sql = $sql . " and f.orderId = $orderId";
        }
        return $sql;
    }

    protected function _getRegWorkshopSql($orderId)
    {
        $sql = "SELECT
        f.orderId,
        IFNULL(w.name, p.product) AS namn,
        city AS stad,
        siteName AS location,
        sd.startDate AS START,
        sd.startTime AS TIME,
        DAYNAME(sd.startDate) AS WEEKDAY
    FROM
        tbl_registration_festival AS f
    LEFT OUTER JOIN tbl_registration_festival_product AS p
    ON
        p.eventType = f.eventType AND p.dateRange = f.dateRange AND p.email = f.email
    LEFT OUTER JOIN tbl_schedule_def AS sd
    ON
        sd.eventType = p.eventType
    LEFT OUTER JOIN tbl_workshop AS w
    ON
        w.workshopId = p.product
    LEFT OUTER JOIN tbl_site AS s
    ON
        s.siteId = w.siteId
    where p.productType='package'";
        
    if ((int) $orderId !== 0) {
            $sql = $sql . " and f.orderId = $orderId";
    }
        
        return $sql;
    }


    protected function _getOtherSql($orderId, $language, $currency)
    {
        $name = 'name' . $language;
        $sql="SELECT o.productId, o.productType 
        from `tbl_order_product` o
        left outer join tbl_product_def d on o.productId = d.productId
        where o.productType NOT IN ('course', 'marathon', 'workshop')";
        
        if ((int) $orderId !== 0) {
            $sql = $sql . " and orderId = $orderId";
        }
        return $sql;
    }

    protected function _getOrderSql($orderId, $currency)
    {
        $sql="SELECT 
            amount as amount, 
            paidAmount as paid, 
            discount as discount, 
            '$currency' as currency 
        from `tbl_order`";
        
        if ((int) $orderId !== 0) {
            $sql = $sql . " where orderId = $orderId";
        }
        return $sql;
    }

    protected function _getOrderIdSql()
    {
        $sql="SELECT orderId from `tbl_order`";
        
        return $sql;
    }

    

    protected function _htmlTextFromSql($sql)
    {
        $rows = $this->_selectRows($sql);

        if ($rows === null) {
            $str = 'Failed to execute SQL-statement ' . $sql;
            return $str;            
        }
        
        $str = "<table style='border-top: 4px solid #81185B;'><tbody>";
        if (count($rows) === 0) {
            return null;
        }    

        foreach($rows[0] as $key=>$value) {
            if ($key !== 'urlLocation') {
                $str = $str .  '<th>'. $key .'</th>';
            }
        }    
        $str = $str . '</tr></thead><tbody>';
        foreach($rows as $row) {
            $str = $str .  '<tr>';
            foreach($row as $key=>$value) {
                if ($key === 'urlLocation') {
                    $str = $str;
                } else if (in_array($key, array('address', 'adress'))) {
                    $anchor = $this->_urlAnchor($value, $row['urlLocation']);
                    $str = $str .  '<td>' . $anchor . '</td>';
                } else if (in_array($key, array('veckodag', 'dayOfWeek'))) {
                    $wd = $this->_weekday($value, 'SV');
                    $str = $str .  '<td>' . $wd . '</td>';
                } else {   
                    $str = $str .  '<td>' . $value . '</td>';
                }    
            }   
            


            $str = $str .  '</tr>';
        }
        $str = $str . '</table>';
        return($str);
    }

    protected function _htmlOrderPaid($sql, $orderId, $currency)
    {
        $rows = $this->_selectRows($sql);
        $row = $rows[0];
        $amount=$row['amount'];
        if (isset($row['paidAmount'])) {
            if ($row['paidAmount']) {
                echo "<h4 style='color:#81185B;'>Kvittens på att du har betalt $amount $currency till " . COMPANY_NAME . " för order $orderId</h4>";
            }    
        }
    }    






    protected function _echoHeader_SV($orderId, $language, $currency)
    {
        $row = $this->_getSingleRow('tbl_order', 'orderId', $orderId);
        if (isset($row['amount'])) {
            $amount=(int) $row['amount'];
        } else {
            $amount = (int) 0;
            $this->logger->error('Failed to find amount in tbl_order');
        }
       
        if (isset($row['paidAmount'])) {
            $paidAmount=(int) $row['paidAmount'];
        } else {
            $this->logger->error('Failed to find paidAmount in tbl_order');
            $paidAmount = (int) 0;
        }
        if (isset($row['status']) && $row['status'] === 'DECLINED') {
            echo "<h4 style='color:#81185B;'>";
            echo "Order nummer $orderId från " . COMPANY_NAME . " har cancellerats";
            echo "</h4>";
        } else if ($paidAmount === 0) {
            echo "<h4 style='color:#81185B;'>";
            echo "Order bekräftelse på order nummer $orderId";
            if ($amount != 0) {
                echo "<p>Du skall betala $amount $currency</p>";
                echo "<p><b>Vänligen ange ordernummer $orderId och namn i meddelandefältet på din inbetalning</b></p>";
            }    
            echo "</h4>";
            echo $this->_paymentInfo($amount, $language);
        } else if ($paidAmount < $amount) {    
            $resultingAmount = $amount - $paidAmount;
            echo "<h4 style='color:#81185B;'>";
            echo "Kvittens på att du har betalt delsumman $paidAmount $currency av totalt $amount $currency till " . COMPANY_NAME . " för order $orderId.";
            echo "</h4>";
            echo "<p><b>Vänligen ange ordernummer $orderId och namn i meddelandefältet på din inbetalning:</b></p>";
            echo "<p style='color:#81185B;'>";
            echo "Totala beloppet på order $orderId är på $amount $currency.";
            echo "Resterande belopp att betala är $resultingAmount $currency.";
            echo $this->_paymentInfo($amount, $language);
        } else if ((int) $paidAmount === (int) $amount) {
            echo "<h3 style='color:#81185B;'>";
            echo "Kvittens på att du har betalt $paidAmount $currency till " . COMPANY_NAME . " för order $orderId";
            echo "</h3>";
        }    
    }


    protected function _echoHeader_ES($orderId, $language, $currency)
    {
        $row = $this->_getSingleRow('tbl_order', 'orderId', $orderId);
        if (isset($row['amount'])) {
            $amount=(int) $row['amount'];
        } else {
            $amount = (int) 0;
            $this->logger->error('Failed to find amount in tbl_order');
        }

        if (isset($row['paidAmount'])) {
            $paidAmount=(int) $row['paidAmount'];
        } else {
            $this->logger->error('Failed to find paidAmount in tbl_order');
            $paidAmount = (int) 0;
        }

        if (isset($row['status']) && $row['status'] === 'DECLINED') {
            echo "<h4 style='color:#81185B;'>";
            echo "Order number $orderId from " . COMPANY_NAME . " has been canceled.";
            echo "</h4>";
        } else if ($paidAmount === 0) {
            echo "<h4 style='color:#81185B;'>";
            echo "Confirmación de pedido del número de pedido order $orderId. Tienes que pagar $amount $currency";
            echo "</h4>";
            echo "<p>Por favor escriba el número de orden $orderId y el nombre en el campo de pago del mensaje.</p>";
            echo $this->_paymentInfo($amount, $language);
        } else if ($paidAmount < $amount) {    
            $resultingAmount = $amount - $paidAmount;
            echo "<h4 style='color:#81185B;'>";
            echo "Reconozca que ha pagado la moneda total de $paidAmount $ de una cantidad total de $ monto $ currency a la compañía de tango para el pedido $ orderId.";
            echo "</h4>";
            echo "<p style = 'color: #81185B;'>";
            echo "El importe total del pedido order $orderId es de $amount $currency.";
            echo "La cantidad restante a pagar es $resultAmount $currency";
            echo "<p><b>Por favor escriba el número de orden $orderId y el nombre en el campo de pago del mensaje.</b></p>";
            echo $this->_paymentInfo($amount, $language);
            echo "</p>";
        } else if ((int) $paidAmount === (int) $amount) {
            echo "<h3 style='color:#81185B;'>";
            echo "Reconocimiento de que usted ha pagado $paidAmount $currency a la compañía de tango para pedidos $orderId";
            echo "</h3>";
        }    
    }

    protected function _echoHeader_EN($orderId, $language, $currency)
    {
        $row = $this->_getSingleRow('tbl_order', 'orderId', $orderId);
        if (isset($row['amount'])) {
            $amount=(int) $row['amount'];
        } else {
            $amount = (int) 0;
            $this->logger->error('Failed to find amount in tbl_order');
        }

        if (isset($row['status']) && $row['status'] === 'DECLINED') {
            echo "<h4 style='color:#81185B;'>";
            echo "Order number $orderId from " . COMPANY_NAME . "has been canceled.";
            echo "</h4>";
        } else if (isset($row['paidAmount'])) {
            $paidAmount=(int) $row['paidAmount'];
        } else {
            $this->logger->error('Failed to find paidAmount in tbl_order');
            $paidAmount = (int) 0;
        }

        if (isset($row['status']) && $row['status'] === 'DECLINED') {
            echo "<h4 style='color:#81185B;'>";
            echo "Order nummer $orderId har cancellerats.";
            echo "</h4>";
        } else if ($paidAmount === 0) {
            echo "<h4 style='color:#81185B;'>";
            echo "<p>Order confirmation on order number $orderId. You shall pay $amount $currency</p>";
            echo "</h4>";
            echo "<p><b>Please write order number $orderId and name in message field of payment.</b></p>";
            echo $this->_paymentInfo($amount, $language);
            echo "</ul>" ;
        } else if ($paidAmount < $amount) {    
            $resultingAmount = $amount - $paidAmount;
            echo "<h4 style='color:#81185B;'>";
            echo "Kvittens på att du har betalt delsumman $paidAmount $currency av totalt $amount $currency till " . COMPANY_NAME . " för order $orderId.";
            echo "</h4>";
            echo "<p style='color:#81185B;'>";
            echo "Total amount on order $orderId is on $amount $currency.";
            echo "Remaining amount to pay is $resultingAmount $currency</p>";
            echo "<p>Please write order number $orderId and name in message field of payment.</p>";
            echo $this->_paymentInfo($amount, $language);
            echo "</p>";
        } else if ((int) $paidAmount === (int) $amount) {
            echo "<h3 style='color:#81185B;'>";
            echo "Receipt that you have paid $paidAmount $currency to " . COMPANY_NAME . " for  $orderId";
            echo "</h3>";
        }    
    }


    protected function _echoHeader($orderId, $language, $currency)
    {
        switch ($language) {
            case 'SV': return($this->_echoHeader_SV($orderId, $language, $currency));
            case 'ES': return($this->_echoHeader_ES($orderId, $language, $currency));
            default: return($this->_echoHeader_EN($orderId, $language, $currency));
        }
    }

    protected function _echoOrder($orderId, $language, $currency)
    {
        echo "<h2 style='color:#81185B;'><i>Order " . $orderId .  " " . COMPANY_NAME . "</i></h2>";

        $this->_echoHeader($orderId, $language, $currency);

        $sql = $this->_getOrderSql($orderId, $currency);
        $html = $this->_htmlTextFromSql($sql);
        echo $html;


        $sql = $this->_getCoursesSqlFromOrderProduct($orderId, $language, $currency);
        $html = $this->_htmlTextFromSql($sql);
        if ($html !== null) {
                echo $html;
        } else {   
            $sql = $this->_getCoursesSqlFromReg($orderId, $language, $currency);
            $html = $this->_htmlTextFromSql($sql);
            if ($html !== null) {
                echo $html;
            }    
        }    

        $sql = $this->_getRegPackageSql($orderId);
        $html = $this->_htmlTextFromSql($sql);
        if ($html !== null) {
            //echo '<h4>Workshops<h4>';
            echo $html;
        }    

        $sql = $this->_getRegWorkshopSql($orderId);
        $html = $this->_htmlTextFromSql($sql);
        if ($html !== null) {
            //echo '<h4>Workshops<h4>';
            echo $html;
        }    
        /*
        $sql = $this->_getOtherSql($orderId, $language, $currency);
        $html = $this->_htmlTextFromSql($sql);
        if ($html != null) {
            echo '<h4>Shoes and clothes<h4>';
            echo $html;
        }
        */
    }    

    protected function _echoEventRegistration($orderId, $eventType) 
    {
        $sql = $this->_getRegPackageSql($orderId, $eventType);
        $html = $this->_htmlTextFromSql($sql);
        if ($html !== null) {
            //echo '<h4>Package<h4>';
            echo $html;
        }

        $sql = $this->_getRegWorkshopSql($orderId, $eventType);
        $html = $this->_htmlTextFromSql($sql);
        if ($html !== null) {
            //echo '<h4>Workshop<h4>';
            echo $html;
        }
    }    

    protected function _getLanguageFromReg($reg)
    {
        $language = isset($reg['language'])?$reg['language']:'SV';
        return $language;
    }    

    protected function _mailSubjectToCustomer($reg)
    {
        $orderId = isset($reg['orderId'])?$reg['orderId']:' ';
        $weekday = $this->_weekday($reg['dayOfWeek'], 'EN');
        $startAt =  $weekday . ' ' . $reg['startDate'] .  ' at ' . $reg['startTime'];
        $text = isset($reg['nameEN'])?$reg['nameEN']:'Starttime : ' . $startAt;

        switch ($reg['status']) {
            case'WL':$subject="Registration $orderId at Tangkompaniet - $text";
                break; 
            case'WF':$subject="Registration $orderId at Tangkompaniet - $text";
                break; 
            default:$subject="Registration $orderId at Tangkompaniet - Starttime $text";
                break;
        }
        return($subject);        
    }


    protected function _mailBodyToCustomer($reg) 
    {

        $language = $this->_getLanguageFromReg($reg);
        $weekday = $this->_weekday($reg['dayOfWeek'], $language);
        $legalText = $this->_getHtmlFromTblText('REGISTRATION', 'LEGAL', $language);
        $cityAnchor = (isset($reg['city']) &&  isset($reg['urlLocation']))?$this->_urlAnchor($reg['city'], $reg['urlLocation']):'';
        $addressAnchor = (isset($reg['address']) && isset($reg['urlLocation']))?$this->_urlAnchor($reg['address'], $reg['urlLocation']):'';
        $currency = isset($reg['currency'])?$reg['currency']:'SEK';
        $orderId = isset($reg['orderId'])?$reg['orderId']:0;
        $startAt =  $weekday . ' ' . $reg['startDate'] .  ' ' . $reg['startTime'];
        $name = isset($reg['name' . $language])?$reg['name' . $language]:$reg['title'];
        $token = isset($reg['token'])?$reg['token']:null;
        $cancellationLink = isset($token)?"https://www.tangokompaniet.com/cancelRegistration/$token":null;
        $clickableUrl = isset($token)?$this->_clickableUrl($cancellationLink, "Cancel Registration"):null;
        $status = isset($reg['status'])?$reg['status']:'OK';
        $price = isset($reg['price'])?$reg['price']:'-';

        $status = MAILTEXT['STATUS'][$status][$language]; 

        $header="<h1>" . MAILTEXT['REGISTRATION'][$language] . $orderId . "</h1>";

        $strReg = json_encode($reg);
        $this->logger->warning("77777 $strReg");


        
        $part1=MAILTEXT['EVENT_NAME'][$language] . ' : ' . $name . "<p/><p/>";
        
        $part2=MAILTEXT['TIME'][$language] . $weekday . ' ' . $reg['startDate'] .  ' at ' . $reg['startTime'] . '<p/><p/>';

        $part3='Location : ' . isset($reg['city'])?$reg['city']:''
        . ' at address ' . isset($addressAnchor)?$addressAnchor . '<p/><p/>':'';

        $part4= 'Registrant : ' . $reg['firstName'] . ' ' . $reg['lastName'];
        $part4.= (isset($reg['firstNamePartner']) && isset($reg['lastNamePartner']))?' --- Partner :' . $reg['firstNamePartner'] . ' ' . $reg['lastNamePartner']:'';
        $part4.= '<p/>';
       
        $part5=isset($reg['price'])?'<p/><p/>' . MAILTEXT['PRICE_PER_PERSON'][$language] . ' : ' . $reg['price'] . ' ' . $currency . ' (shall be paid to Tangokompaniet with one of the methods below)<p/><p/>':'';
        $part5.= '<p/>';
        
        $part5b=isset($clickableUrl)?'<p/><p/>' . MAILTEXT['TOKEN'][$language] . ' : ' . $clickableUrl . '<p/><p/>':'';
        $part5b .= '<p/>';

        if ($reg['productType']!== 'social') {
            $part6="<div style='font-size:small;'color:#2b2523;'>" . $legalText . "</div>"; 
        } else {
            $part6='';
        }    

        ob_start(); //Start output buffer
        echo $this->_paymentInfo($price, $language);
        echo '<h4>' . MAILTEXT['THANK_YOU'][$language] . '</h4>';
        $part7 = ob_get_contents(); //Grab output
        ob_end_clean(); //Discard output buffer

        $body = "<div style='color:#81185B;'>" . $header . $part1 .  $part2 . $part3 . $part4 . $part5 . $part5b . $part6 . $part7 . '</div>';
        $body .= isset($reg['message'])?MAILTEXT['MESSAGE_SENT_TO_TK'][$language] . $reg['message']:'';
        return $body;
    }

    protected function _mailAnchor($email) {
        return "<a 'mailto=" . $email . "'>" . $email . "</a>";
    }

    protected function _urlAnchor($label, $url) {
        if (isset($url)) {
            return "<a href='" . $url . "'>" . $label . "</a>";
        } else if (isset($city)) {
            return $label;
        } else {
            return "";
        }
}

    protected function _mailBodyToTk($reg) 
    {
        $language = 'EN';
        $weekday = $this->_weekday($reg['dayOfWeek'], 'SV');

        # 1: Course
        $course = $reg['nameSV'] . ' ' . $reg['city'] . ' ' . $weekday . ' klockan ' . $reg['startTime'];
        
        # 2: Registratior
        $emailAnchor = $this->_mailAnchor($reg['email']);
        $registrator = $reg['firstName'] . ' ' . $reg['lastName'] . ' med email:' . $emailAnchor;
        
        # 3: Partner
        if (isset($reg['firstNamePartner']) && isset($reg['lastNamePartner'])) {
            if (isset($reg['emailPartner'])) { 
                $emailAnchorPartner = $this->_mailAnchor($reg['emailPartner']);
                $partner = $reg['firstNamePartner'] . ' ' . $reg['lastNamePartner'] . ' med email:' . $emailAnchorPartner;
            } else {   
                $partner = $reg['firstNamePartner'] . ' ' . $reg['lastNamePartner'] . ' utan angiven email-adress';
            }    
        } else {
            $partner = 'Ingen danspartner anmäld';
        }

        # 4 orderId
        $orderId = isset($reg['orderId'])?$reg['orderId']:0;

        # 5: Meddelande
        $message = isset($reg['message'])?$reg['message']:'';
        
        $body = "<h2 style='color:#2b2523;' >" . "Kursanmälan till:" . "</h2>"; 
        $body .= "<div style='color:#2b2523;'>" . $course . "</div>" ;
        $body .= "<h2 style='color:#2b2523;' >" . "Anmälan gjord av:" . "</h2>"; 
        $body .= "<div style='color:#2b2523;'>" . $registrator . "</div>" ;
        $body .= "<h2 style='color:#2b2523;' >" . "Danspartner:" . "</h2>"; 
        $body .= "<div style='color:#2b2523;'>" . $partner . '</div>';
        $body .= "<div style='color:#2b2523;'>" . "Vid betalning via SWISH eller bankgiro ange ditt order nummer " .  
        $reg['orderId']?$reg['orderId']:"Okänt order nummer" . " i meddelande fältet på din betalning" . "</div>" ;

        if (isset($message)) {
            $body .= "<h2 style='color:#2b2523;' >" . "Meddelande från kund:" . "</h2>"; 
            $body .= "<div style='color:#2b2523;'>" . $message . '</div>';
        }
        return $body;
    }

    //  Send mail to TK
    protected function _sendMailToResponsibleAtTk($reg) {
        $requiredKeys = ['emailResponsible', 'dayOfWeek', 'firstName', 'lastName', 'productType', 'email', 'city', 'nameEN'];
        if (($str = $this->_checkMandatoryInput($reg, $requiredKeys))!= null) {
            $this->logger->warning("No mail sent to responsible teacher since key is missing");
            return false;
        }

        // Send mail to responsible for course    
        if (isset($reg['message'])) {
            $subject="Anmälan med orderId:" . $reg['orderId'] . ' har ett kund-meddelande';
        } else {;
            $subject='Anmälan med orderId:' . $reg['orderId'];
        }   
        $weekday = $this->_weekday($reg['dayOfWeek'], 'SV');
        $subject .= $reg['nameEN'] . ' ' . $reg['city'] . ' ' . $weekday;
        $body = $this->_mailBodyToTk($reg);
        $replyTo = array($reg['email'], $reg['emailResponsible']);
        if ($this->_sendMailWithReplyTo($subject, $body, $reg['emailResponsible'], $replyTo)) { 
            $this->logger->info('Registration mail sent to ' . $reg['firstName'] . ' ' . $reg['lastName'] . $reg['email'] . ' on course ' . $reg['nameEN']);  
            if (isset($reg['emailResponsible']) && isset($reg['city']) && isset($reg['dayOfWeek'])) {
                $this->logger->info('Registration mail sent to ' . $reg['email'] . ' on ' . $reg['productType'] . ' for ' . $reg['nameEN'] . 
                ' in '. $reg['city'] . ' on weekday ' . $weekday);
            }
            return true;
        } else {
            $this->logger->info('Subject:' . $subject . ' Body:' . $body);
            $this->logger->error('Failed to send mail to ' . $reg['email'] . ' on course ' . $reg['nameEN']);  
            $this->logger->error('SMTP-server cannot send mail from DEV system');  
            return false;
        }    
    }        

    public function sendMailReg($reg) {

        // Define mail to customer
        $subject = $this->_mailSubjectToCustomer($reg);
        $body = $this->_mailBodyToCustomer($reg);
        $replyTo = array(isset($reg['emailResponsible'])?$reg['emailResponsible']:'', $reg['email']);
        $this->_sendMailWithReplyTo($subject, $body, $reg['email'], $replyTo); 
        
        if (isset($reg['emailPartner'])) {
            $this->_sendMailWithReplyTo($subject, $body, $reg['emailPartner'], $replyTo); 
        }

        $this->_sendMailToResponsibleAtTk($reg);

        return $body = $this->_mailBodyToCustomer($reg);
    }

    public function mailSubjectOrderConfirmation($orderId, $language) {
        $arr=array(
            'SV'=>"Orderbekräftelse $orderId från " . COMPANY_NAME . "",
            'ES'=>"Confirmación de la orden $orderId de " . COMPANY_NAME . "",
            'EN'=>"Reciept for order number $orderId from " . COMPANY_NAME . ""
        );
        $ret=$arr[$language];
        return $ret;
    }    

    public function mailSubjectPartialReciept($orderId, $language) {
        $arr=array(
            'SV'=>"Kvitto på delbetalning på order $orderId från " . COMPANY_NAME . "",
            'ES'=>"Recibo de pago parcial de pedido $orderId de " . COMPANY_NAME . "",
            'EN'=>"Receipt of partial payment of order $orderId from " . COMPANY_NAME . ""
        );
        $ret=$arr[$language];
        return $ret;
    }    


    public function mailSubjectReciept($orderId, $language) {
        $arr=array(
            'SV'=>"Kvitto på order $orderId from " . COMPANY_NAME . "",
            'ES'=>"Recibo en la orden $orderId en " . COMPANY_NAME . "",
            'EN'=>"Reciept on order $orderId from " . COMPANY_NAME . ""
        );
        $ret=$arr[$language];
        return $ret;
    }    

    public function mailSubjectDeclined($orderId, $language) {
        $arr=array(
            'SV'=>"Order $orderId från " . COMPANY_NAME . " has cancellerats",
            'ES'=>"La orden $orderId de " . COMPANY_NAME . " ha sido cancelada",
            'EN'=>"Order $orderId from " . COMPANY_NAME . " has been canceled"
        );
        $ret=$arr[$language];
        return $ret;
    }    


    public function mailSubject($orderId, $language, $status) {
        $row = $this->_getSingleRow('tbl_order', 'orderId', $orderId);
        if (isset($row['amount'])) {
            $amount=(int) $row['amount'];
        } else {
            $amount = (int) 0;
            $this->logger->error('Failed to find amount in tbl_order');
        }

        if (isset($row['paidAmount'])) {
            $paidAmount=(int) $row['paidAmount'];
        } else {
            $this->logger->error('Failed to find paidAmount in tbl_order');
            $paidAmount = (int) 0;
        }

        if (isset($row['status']) && $row['status'] === 'DECLINED') {
            return $this->mailSubjectDeclined($orderId, $language); 
        } else if ($paidAmount === 0) {
            return $this->mailSubjectOrderConfirmation($orderId, $language); 
        } else if ($paidAmount < $amount) {
            return $this->mailSubjectPartialReciept($orderId, $language);
        } else {
            return $this->mailSubjectReciept($orderId, $language);
        }    
    }    


    public function mailBody($orderId, $language) 
    {
        ob_start(); //Start output buffer
        echo "<div style='color:#2b2523;'>"; 
        if ($orderId !== null && $orderId !==0) {
            $this->_echoOrder($orderId, $language, 'SEK'); 
            echo "<div style='font-size:small;'>"; 
            echo $this->_getHtmlFromTblText('REGISTRATION', 'LEGAL', $language);
            echo '</div>'; 
            echo '<h4>' . MAILTEXT['THANK_YOU'][$language] . '</h4>';
        } else {    
            $sql = $this->_getOrderIdSql();
            $rows = $this->_selectRows($sql);
            foreach ($rows as $row) {
                $orderId= (int) $row['orderId'];
                $this->_echoOrder($orderId, $language, 'SEK'); 
            }    
            echo "<div style='font-size:small;'>"; 
            echo $this->_getHtmlFromTblText('REGISTRATION', 'LEGAL', $language);
            echo '</div>'; 
            echo '<h4>' . MAILTEXT['THANK_YOU'][$language] . '</h4>';
        }    
        echo '</div>'; 
        $output = ob_get_contents();//Grab output
        ob_end_clean(); //Discard output buffer

        return $output;
    }

    public function testMailSubject($request, $response)
    {
        $queryParams = $request->getQueryParams();
        $orderId=0;
        if (isset($queryParams['orderId'])) {
            $orderId = (int) $queryParams['orderId'];
        } else {
            $orderId = 1;
        }
        $language='SV';
        if (isset($queryParams['language'])) {
            $language = $queryParams['language'];
        } 
        echo $this->mailSubject($orderId, $language, 'PAID');
    }

    public function testMailText($request, $response)
    {
        $queryParams = $request->getQueryParams();
        $orderId=0;
        if (isset($queryParams['orderId'])) {
            $orderId = (int) $queryParams['orderId'];
        } 
        $language='SV';
        if (isset($queryParams['language'])) {
            $language = $queryParams['language'];
        } 
        $status = 'DECLINED';
        echo $this->mailBody($orderId, $language);
    }


    public function testMail($request, $response) 
    {
        $queryParams = $request->getQueryParams();
        $recipient ='paelsis@hotmail.com';            
        if (isset($queryParams['recipient'])) {
            $recipient = $queryParams['recipient'];
        } 
        $orderId=0;
        if (isset($queryParams['orderId'])) {
            $orderId = (int) $queryParams['orderId'];
        } 
        $language='SV';
        if (isset($queryParams['language'])) {
            $language = (int) $queryParams['language'];
        } 
        $status='OK';
        if (isset($queryParams['status'])) {
            $language = (int) $queryParams['status'];
        } 

        $subject = $this->mailSubject($orderId, $language, $status);
        
        $body = $this->mailBody($orderId, $language);
    
        if ($this->_sendMail($subject, $body, $recipient)) {         
            $this->logger->addDebug('Successful mail sending to ' . $recipient);
            return $response->withJson(array('status' => 'OK', 
            'message'=>'Successful mail sending',
            'subject'=>$subject, 
            'body'=>$body),200);
        } else{
            $this->logger->error('Failed mail sending to ' . $recipient);
            return $response->withJson(array('status' => 'ERROR',
                'message'=>'Failing to send mail',
                'subject'=>$subject, 
                'body'=>$body),200);
        }
    }

    protected function sendMail($request, $response) 
    {
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['email'])) {
            $recipient = $queryParams['email'];
        } else {
            $this->logger->error('sendMail: No email in request');
            return $response->withJson(array('status' => 'ERROR',
                'message'=>'Failing to send mail du to missing field email in request'),204);
        }
        if (isset($queryParams['responsibleEmail'])) {
            $espoinsibleEmail = (int) $queryParams['responsibleEmail'];
        } 
        if (isset($queryParams['subject'])) {
            $subject = (int) $queryParams['subject'];
        } else {
            $subject ="Mail from Tangokompaniet";
        }
        if (isset($queryParams['body'])) {
            $body = (int) $queryParams['body'];
        } else {
            $body =  "<h1>Body in mail from Tangompanietfrom Tangokompaniet is missing</h1>";
        }
        if (isset($queryParams['title'])) {
            $title = (int) $queryParams['title'];
        } 
        if (isset($queryParams['legal'])) {
            $legal = (int) $queryParams['legal'];
        } 
        if (isset($queryParams['thankYou'])) {
            $thankYou = (int) $queryParams['thankYou'];
        } else {
            $thankYou = $this->_getHtmlFromTblText('REGISTRATION', 'LEGAL', $language);
        }
        if (isset($queryParams['color'])) {
            $color = (int) $queryParams['color'];
        } else {
            $color = '#81185B';
        }
        $style="color:$color;";

        $body = "<div style=$style>$body"; 
        if (isset($title)) {
            $body = "<h1>$title<h1>$body<p/>";
        }
        if (isset($object)) {
            $body .= 
            $body .= $this->_build_html_from_object($array);
        }
        if (isset($array)) {
            $body .= "<p/>";
            $body .= $this->_build_html_from_array($array);
        }
        if (isset($legal)) {
            $body .= "<p/>";
            $body .= $legal;
        }
        if (isset($thankYou)) {
            $body .= "<p/>";
            $body .= "<h4>$thankYou</h4>";
        }
        if ($this->_sendMail($subject, $body, $email)) {         
            $this->logger->addDebug('Successful mail sending to ' . $email);
            return $response->withJson(array('status' => 'OK', 
            'message'=>'Successful mail sending',
            'subject'=>$subject, 
            'body'=>$body),200);
        } else{
            $this->logger->error('Failed mail sending to ' . $email);
            return $response->withJson(array('status' => 'ERROR',
                'message'=>'Failing to send mail',
                'subject'=>$subject, 
                'body'=>$body),200);
        }
        if (isset($respoinsibleEmail)) {
            if ($this->_sendMail($subject, $body, $recipient)) {         
                $this->logger->addDebug('Successful mail sending to ' . $respoinsibleEmail);
                return $response->withJson(array('status' => 'OK', 
                'message'=>'Successful mail sending',
                'subject'=>$subject, 
                'body'=>$body),200);
            } else{
                $this->logger->error('Failed mail sending to ' . $respoinsibleEmail);
                return $response->withJson(array('status' => 'ERROR',
                    'message'=>'Failing to send mail',
                    'subject'=>$subject, 
                    'body'=>$body),200);
            }
        }    
    }
};    

