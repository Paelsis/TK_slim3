<?php
namespace App\Controllers;
define("COMPANY_NAME", "Tangokompaniet");
define("BANKGIRO", "5532-8223");
define("SWISH", "123 173 30 05");
define("SWISH_ANNA", "070-5150345");
define("PAYPAL_ANNA", "anna@tangokompaniet.com");
define("BIC", "SWEDSESS");
define("IBAN", "SE59 8000 0821 4994 3833 6324");

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
        $sql="SELECT p.nameEN as package FROM tbl_reg_event as e 
        left outer join tbl_schedule_def as s on s.scheduleId = e.scheduleId 
        left outer join tbl_package as p on p.packageId = e.packageId";


        if ((int) $orderId !== 0 ) {
            $sql = $sql . " where e.orderId = $orderId";
        }
        return $sql;
    }

    protected function _getRegWorkshopSql($orderId)
    {
        $sql = "SELECT nameEN as namn, city as stad, siteName as location,
        startDate as start, startTime as time, DAYNAME(startDate) as weekday
        FROM tbl_reg_event_product as p
        left outer join tbl_workshop as w on w.productId = p.productId
        left outer join tbl_site as s on s.siteId = w.siteId";
        
        if ((int) $orderId !== 0) {
            $sql = $sql . " where p.orderId = $orderId";
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
                echo "<h4 style='color:#81185B;'>Kvittens på att du har betalt $amount $currency till " . constant('COMPANY_NAME') . " för order $orderId</h4>";
            }    
        }
    }    

    protected function _echoPaymentInfo($orderId, $language) {
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
            echo "<ul>";
            echo "<li>" . $orderIdText . "</li>" ;
            echo "<li>" . $onlyAnnaOnline . "</li>" ;
            echo "<ul>";
                echo "<li>SWISH:" . constant('SWISH_ANNA') . $onlyInSweden . "</li>";
                echo "<li>PAYPAL:" . constant('PAYPAL_ANNA') . "</li>";
            echo "</ul>";
            echo "<li>" . $allOtherCourses . "</li>" ;
            echo "<ul>";
                echo "<li>SWISH:" . constant('SWISH') . $onlyInSweden . "</li>";
                echo "<li>Bankgiro:" . constant('BANKGIRO') . $onlyInSweden . "</li>";
                echo "<li>International IBAN:" . constant('IBAN') . " BIC:" . constant('BIC') . "</li>";
            echo "</ul>";
        echo "</ul>";
    }


    protected function _htmlTextFromSqlRow($sql)
    {
        $rows = $this->_selectRows($sql);
        
        if (count($rows) === 0 or $rows === null) {
            return(null);
        }    
        $str = "<table style='border-bottom: 1px solid blue; border-top: 1px solid red'><tbody>";
        foreach($rows as $row) {
            foreach($row as $key=>$value) {
                $str = $str .  '<tr><td>' . $key . '</td><td>' .$value. '</td></tr>';
            }    
        }
        $str = $str . '</tbody></table>';
        return($str);
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
            echo "Order nummer $orderId från " . constant('COMPANY_NAME') . " har cancellerats";
            echo "</h4>";
        } else if ($paidAmount === 0) {
            echo "<h4 style='color:#81185B;'>";
            echo "Order bekräftelse på order nummer $orderId";
            if ($amount != 0) {
                echo "<p>Du skall betala $amount $currency</p>";
                echo "<p><b>Vänligen ange ordernummer $orderId och namn i meddelandefältet på din inbetalning</b></p>";
            }    
            echo "</h4>";
            $this->_echoPaymentInfo($orderId, $language);
        } else if ($paidAmount < $amount) {    
            $resultingAmount = $amount - $paidAmount;
            echo "<h4 style='color:#81185B;'>";
            echo "Kvittens på att du har betalt delsumman $paidAmount $currency av totalt $amount $currency till " . constant('COMPANY_NAME') . " för order $orderId.";
            echo "</h4>";
            echo "<p><b>Vänligen ange ordernummer $orderId och namn i meddelandefältet på din inbetalning:</b></p>";
            echo "<p style='color:#81185B;'>";
            echo "Totala beloppet på order $orderId är på $amount $currency.";
            echo "Resterande belopp att betala är $resultingAmount $currency.";
            $this->_echoPaymentInfo($orderId, $language);
        } else if ((int) $paidAmount === (int) $amount) {
            echo "<h3 style='color:#81185B;'>";
            echo "Kvittens på att du har betalt $paidAmount $currency till " . constant('COMPANY_NAME') . " för order $orderId";
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
            echo "Order number $orderId from " . constant('COMPANY_NAME') . " has been canceled.";
            echo "</h4>";
        } else if ($paidAmount === 0) {
            echo "<h4 style='color:#81185B;'>";
            echo "Confirmación de pedido del número de pedido order $orderId. Tienes que pagar $amount $currency";
            echo "</h4>";
            echo "<p>Por favor escriba el número de orden $orderId y el nombre en el campo de pago del mensaje.</p>";
            $this->_echoPaymentInfo($orderId, $language);
        } else if ($paidAmount < $amount) {    
            $resultingAmount = $amount - $paidAmount;
            echo "<h4 style='color:#81185B;'>";
            echo "Reconozca que ha pagado la moneda total de $paidAmount $ de una cantidad total de $ monto $ currency a la compañía de tango para el pedido $ orderId.";
            echo "</h4>";
            echo "<p style = 'color: #81185B;'>";
            echo "El importe total del pedido order $orderId es de $amount $currency.";
            echo "La cantidad restante a pagar es $resultAmount $currency";
            echo "<p><b>Por favor escriba el número de orden $orderId y el nombre en el campo de pago del mensaje.</b></p>";
            $this->_echoPaymentInfo($orderId, $language);
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
            echo "Order number $orderId from " . constant('COMPANY_NAME') . "has been canceled.";
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
            $this->_echoPaymentInfo($orderId, $language);
            echo "</ul>" ;
        } else if ($paidAmount < $amount) {    
            $resultingAmount = $amount - $paidAmount;
            echo "<h4 style='color:#81185B;'>";
            echo "Kvittens på att du har betalt delsumman $paidAmount $currency av totalt $amount $currency till " . constant('COMPANY_NAME') . " för order $orderId.";
            echo "</h4>";
            echo "<p style='color:#81185B;'>";
            echo "Total amount on order $orderId is on $amount $currency.";
            echo "Remaining amount to pay is $resultingAmount $currency</p>";
            echo "<p>Please write order number $orderId and name in message field of payment.</p>";
            $this->_echoPaymentInfo($orderId, $language);
            echo "</p>";
        } else if ((int) $paidAmount === (int) $amount) {
            echo "<h3 style='color:#81185B;'>";
            echo "Receipt that you have paid $paidAmount $currency to " . constant('COMPANY_NAME') . " for  $orderId";
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
        echo "<h2 style='color:#81185B;'><i>Order " . $orderId .  " " . constant('COMPANY_NAME') . "</i></h2>";

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

    protected function _echoThankYou($language)
    {
        echo "<h4 style='color:#81185B;'>";    
        switch ($language) {
            case 'SV': echo "Tack för att du handlar hos oss på " . constant('COMPANY_NAME');
                break;
            case 'ES': echo "Gracias por comprar en " . constant('COMPANY_NAME');
                break;
            default:  echo "Thank you for shopping at " . constant('COMPANY_NAME');
        }    
        echo "</h4>";    
    }    


    protected function _mailBodyToCustomer($reg) 
    {
        $language = 'EN';
        $weekday = $this->_weekday($reg['dayOfWeek'], 'EN');
        $legalText = $this->_getHtmlFromTblText('REGISTRATION', 'LEGAL', $language);
        $cityAnchor = (isset($reg['city']) &&  isset($reg['urlLocation']))?$this->_urlAnchor($reg['city'], $reg['urlLocation']):'';
        $addressAnchor = (isset($reg['address']) && isset($reg['urlLocation']))?$this->_urlAnchor($reg['address'], $reg['urlLocation']):'';
        $currency = isset($reg['currency'])?$reg['currency']:'SEK';
        $orderId = isset($reg['orderId'])?$reg['orderId']:0;
        $startAt =  $weekday . ' ' . $reg['startDate'] .  ' at ' . $reg['startTime'];
        $name = isset($reg['nameEN'])?$reg['nameEN']:'"' . $reg['title'] . '"';

       switch ($reg['status']) {
            case 'OK': $status =  'accepted';
                break; 
            case 'WL': $status = 'put on a list to find a follower';
                break;
            case 'WF': $status = 'put on a list to find leader';
                break;
            case 'WS': $status = 'put on a waitlist (course is full) ';
                break;
        }
        $header ="<h1>Registration $orderId at Tangokompaniet</h1>";

        $part1 = "Event name : $name <p/><p/>";
        
        $part2='Time : ' . $weekday . ' ' . $reg['startDate'] .  ' at ' . $reg['startTime'] . '<p/><p/>';

        $part3='Location : ' . isset($reg['city'])?$reg['city']:''
        . ' at address ' . isset($addressAnchor)?$addressAnchor . '<p/><p/>':'';

        $part4 = 'Registrant : ' . $reg['firstName'] . ' ' . $reg['lastName'];
        $part4.= (isset($reg['firstNamePartner']) && isset($reg['lastNamePartner']))?' --- Partner :' . $reg['firstNamePartner'] . ' ' . $reg['lastNamePartner']:'';
        $part4.= '<p/>';
       
        $part5=isset($reg['price'])?'<p/><p/>Price (per person): ' . $reg['price'] . ' ' . $currency . ' (shall be paid to Tangokompaniet with one of the methods below)<p/>><p/>':'';

        $part6="<div style='font-size:small;'color:#2b2523;'>" . $legalText . "</div>"; 

        ob_start(); //Start output buffer
        $this->_echoPaymentInfo($orderId, $language);
        $this->_echoThankYou($orderId, $language);
        $part7 = ob_get_contents(); //Grab output
        ob_end_clean(); //Discard output buffer

        $body = "<div style='color:#81185B;'>" . $header . $part1 .  $part2 . $part3 . $part4 . $part5 . $part6 . $part7 . '</div>';
        $body .= isset($reg['message'])?'Message sent to Tangokompaniet:' . $reg['message']:'';
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

        return true;
    }

    public function mailSubjectOrderConfirmation($orderId, $language) {
        $arr=array(
            'SV'=>"Orderbekräftelse $orderId från " . constant('COMPANY_NAME') . "",
            'ES'=>"Confirmación de la orden $orderId de " . constant('COMPANY_NAME') . "",
            'EN'=>"Reciept for order number $orderId from " . constant('COMPANY_NAME') . ""
        );
        $ret=$arr[$language];
        return $ret;
    }    

    public function mailSubjectPartialReciept($orderId, $language) {
        $arr=array(
            'SV'=>"Kvitto på delbetalning på order $orderId från " . constant('COMPANY_NAME') . "",
            'ES'=>"Recibo de pago parcial de pedido $orderId de " . constant('COMPANY_NAME') . "",
            'EN'=>"Receipt of partial payment of order $orderId from " . constant('COMPANY_NAME') . ""
        );
        $ret=$arr[$language];
        return $ret;
    }    


    public function mailSubjectReciept($orderId, $language) {
        $arr=array(
            'SV'=>"Kvitto på order $orderId from " . constant('COMPANY_NAME') . "",
            'ES'=>"Recibo en la orden $orderId en " . constant('COMPANY_NAME') . "",
            'EN'=>"Reciept on order $orderId from " . constant('COMPANY_NAME') . ""
        );
        $ret=$arr[$language];
        return $ret;
    }    

    public function mailSubjectDeclined($orderId, $language) {
        $arr=array(
            'SV'=>"Order $orderId från " . constant('COMPANY_NAME') . " has cancellerats",
            'ES'=>"La orden $orderId de " . constant('COMPANY_NAME') . " ha sido cancelada",
            'EN'=>"Order $orderId from " . constant('COMPANY_NAME') . " has been canceled"
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
            $this->_echoThankYou($language);
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
            $this->_echoThankYou($language);
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
};    
