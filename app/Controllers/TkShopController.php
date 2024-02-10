<?php
namespace App\Controllers;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const LEADER='LEADER';
const FOLLOWER='FOLLOWER';
const BOTH='BOTH';


class TkShopController extends Controller
{

        protected function _productAsText($product) {
        $keys = array();
        $productText= ''; 
        switch ($product['productType']) {
            case 'course':
                if (array_key_exists('dayOfWeek', $product)) {
                    $product['weekday']=$this->_weekday($product['dayOfWeek']);
                }    
                if (array_key_exists('leader', $product)) {
                    $product['leaderText']=$product['leader']?'register as leader':'register as follower';
                }    
                $productText='&#9644;&nbsp;'; 
                if ($product['debitable']) {
                    $keys=['productId', 'name', 'leaderText', 'startDate', 'weekday', 'startTime', 'teachers', 'siteName', 'address', 'city', 'price'];
                } else {
                    $keys=['productId', 'name', 'leaderText', 'startDate', 'weekday', 'startTime', 'teachers', 'siteName', 'address', 'city'];
                }     
                break;
            default:
                $productText='&#9672;&nbsp;'; 
                $keys=['productId', 'size', 'price'];
                break;
        }
        $productAdjusted=$this->_objectReduceToKeys($product, $keys);
        foreach ($productAdjusted as $key => $value) {      
            $productText = $productText . $value . ' ';
        }
        return $productText;
    }


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

    // Filter to just include $productType
    protected function _shoppingCartFilter($shoppingCartList, $productType) 
    {
        $shoppingCartListReduced = array();
        if ($productType != null && strlen($productType) != 0) {
            foreach ($shoppingCartList as $pr) {
                if (array_key_exists('productType', $pr)) {
                    if ($pr['productType'] === $productType) {
                        array_push($shoppingCartListReduced, $pr);
                    }    
                }    
            }
        }
        return($shoppingCartListReduced);
    }        

    protected function _statusProducts($shoppingCartList, $productType, $status) 
    {
        $shoppingCartListReduced = array();
        if ($productType != null && strlen($productType) != 0) {
            foreach ($shoppingCartList as $pr) {
                if (array_key_exists('productType', $pr)) {
                    if ($pr['productType'] === $productType && $pr['status'] === $status) {
                        array_push($shoppingCartListReduced, $pr);
                    }    
                }    
            }
        }
        return($shoppingCartListReduced);
    }        

    // Filter to just include $productType
    protected function _inverseProductListFilter($shoppingCartList, $productType) 
    {
        $shoppingCartListReduced = array();
        if ($productType != null && strlen($productType) != 0) {
            foreach ($shoppingCartList as $pr) {
                if (array_key_exists('productType', $pr)) {
                    if ($pr['productType'] != $productType) {
                        array_push($shoppingCartListReduced, $pr);
                    }    
                }    
            }
        }
        return($shoppingCartListReduced);
    }    

        
    protected function _getText($groupId, $textId, $language) {    
        $TkTextController = $this->container['TkTextController'];
        return $TkTextController->getText($groupId, $textId); 
    } 
    
    // Filter to not include $productType
    protected function _shoppingCartFilterInverse($shoppingCartList, $productType) 
    {
        $shoppingCartListReduced = array();
        if ($productType == null || strlen($productType) == 0) {
            $productListReduced = $shoppingCartListReduced;
        } else {
            foreach ($shoppingCartList as $pr) {
                if ($pr['productType'] != $productType) {
                    array_push($shoppingCartListReduced, $pr);
                }
            }
        }
        return($shoppingCartListReduced);
    }            

    protected function _productListAsText($shoppingCartList) 
    {
        $text=''; 
        foreach ($shoppingCartList as $pr) {
            // Convert product object to text 
            $productText= $this->_productAsText($pr);
            $text = $text . $productText;
            if ($pr['debitable']) {
                $text = $text . 'SEK' . '<p />';
            } 
        }
        return($text);
    }    

    protected function _courseMailText($shoppingCartList, $status, $text) {
        $regs = $this->_statusProducts($shoppingCartList, 'course', $status);
        $numberOfRegistrations = count($regs);
        $mailText="";
        if ($numberOfRegistrations > 0) {    
            $mailText = 
            "<h4>You $text to the following $numberOfRegistrations registrations:</h4>" .   
            $this->_productListAsText($regs) .
            "<p />";
        }    
        $this->logger->addDebug('OK: Products with status $status:' . $mailText);
        return($mailText);
    } 

    protected function _sendShopMail($result) 
    {
        $shoppingCartList=$result['products'];
        $customer=$result['customer'];
        $order=$result['order'];
        $meta=$result['meta'];

        // Create necessary text strings from order and products
        $firstName=$customer['firstName'];
        $localOrderId=$order['orderId']?$order['orderId']:99999;
        $amount=$order['amount'];
        $currency=$order['currency'];
        $numberOfProducts=$meta['numberOfProducts'];
        
        $mailBodyCourse=''; 
        
        // Registrations to pay for
        $mailText = $this->_courseMailText($shoppingCartList, 'COURSE_ACCEPT', 'have been registered to');
        $this->logger->addDebug('OK: Registrations:' . $mailText);
        $mailBodyCourse = $mailBodyCourse . $mailText;

        // Registrations to for leader search
        $mailText = $this->_courseMailText($shoppingCartList, 'COURSE_FOLLOWER_SURPLUS', 'have registered for partner search (of leader) on');
        $this->logger->addDebug('OK: Leader search:' . $mailText);
        $mailBodyCourse = $mailBodyCourse . $mailText;

        // Registrations to for follower search
        $mailText = $this->_courseMailText($shoppingCartList, 'COURSE_LEADER_SURPLUS', 'have registered for partner search (of follower) search on');
        $this->logger->addDebug('OK: Follower search:' . $mailText);
        $mailBodyCourse = $mailBodyCourse . $mailText;

        // Text for items that is for of productType course
        $mailText = $this->_courseMailText($shoppingCartList, 'COURSE_FULL', 'are waitlisted on the following full Registrations');
        $this->logger->addDebug('OK: Waitlist:' . $mailText);
        $mailBodyCourse = $mailBodyCourse . $mailText;

        // Text for clothes (not)
        $clothes = $this->_shoppingCartFilterInverse($shoppingCartList, 'course');
        $numberOfClothes=count($clothes);
        $numberOfRegistrations=count($shoppingCartList)-$numberOfClothes;
        $addressText = $this->_addressAsText($customer);
        $mailBodyClothes="";
        if ($numberOfClothes > 0) {    
            $mailBodyClothes = 
            "<h4>We will deliver the following $numberOfClothes items to the shipping address below:</h4>" . 
            $this->_productListAsText($clothes) .
            "<h3>Shipping address:</h4>  
            <p>" . 
            $addressText . 
            "</p>";
        } 
        $this->logger->addDebug('OK: Address:' . $addressText);
        $this->logger->addDebug('OK: Products:' . $mailBodyClothes);
        
        
        // Initialize mail
        $mail = new PHPMailer;
        $mail->CharSet = 'UTF-8';
        // $mail->SMTPDebug = 2;                                 // Enable verbose debug output
        $mail->isSMTP();                                      // Set mailer to use SMTP
        //$mail->Host = 'mail.outlook.com';  // Specify main and backup SMTP servers
        $mail->Host = 'mail.surftown.com';  // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
//      $mail->Username = 'paelsis@hotmail.com';                 // SMTP username
//      $mail->Password = 'F66gurkburk04';                           // SMTP password
        $mail->Username = 'webmaster@nyasidan.tangokompaniet.com';   // SMTP username
        $mail->Password = 'F66Fson04';                           // SMTP password
        $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
        $mail->Port = 587;                                    // TCP port to connect to
        $mail->setFrom('daniel@tangokompaniet.com', 'anna@tangokompaniet.com');
        $mail->addAddress('per.eskilson@gmail.com');
        $mail->addReplyTo('webmaster@tangokompaniet.com', 'order@tangokompaniet.com');

        $mail->isHTML(true);                                  // Set email format to HTML

        $mail->Subject = 'Order with ID=' . $localOrderId . ' from Tangokompaniet';

        $mail->Body = "
        <h3>Hi $firstName,</h3>
        <p>            
        <h4>Thanks for your order to tangokompaniet.com.<h4>
        <h4>Your order has number = <font size='4' color='red'>$localOrderId</font></h4>  
        and contains $numberOfRegistrations registrations/or watilistings to Registrations and  
        $numberOfClothes other items.
        <p />Total price for all items in order = $amount SEK
        <p>
        <font size='3' color='purple'>
        $mailBodyCourse
        $mailBodyClothes
        <p>
        <h4>Payment:</h4>
        Please pay total price of <font color='purple' size='4'>$amount . ' ' . $currency</font> with one of the three methods below.<p />
        <font color='red'>Remember to write your name and order number <font size='4'>$localOrderId</font> in the message of the payment.</font>
        <ul>
        <li>Bankgiro: 5532-8223 (only in Sweden)</li>
        <li>Swish-nummer: 123 173 30 05 (aorks only in Sweden)</li>
        <li>International: IBAN: SE59 8000 0821 4994 3833 6324  BIC:SWEDSESS</li>
        </ul> 
        <h4>QR-code for SWISH to Tangokompaniet<h4>
        <img src='https://chart.googleapis.com/chart?chs=300x300&cht=1231733005;$amount;Order nummer:$localOrderId&choe=UTF-8 title=SWISH to Tangokompaniet' />
        <h4>With regards,</h4>
        <h4>Tangokompaniet</h4>
        </p>";

        // $this->logger->addDebug('OK: Mail Body:' . $mail->Body);
        if(!$mail->send()) {
            $this->logger->addDebug("ERROR: We're having trouble with our mail servers at the moment. Please try again later, or contact us directly by phone.");
            $app->halt(500);
        } 
    }

    public function test($request, $response) 
    {
        return 'TkShopController test';
    }

    

    public function products($request, $response) 
    {
        try{
            $con = $this->db;
            // Get the price into priceChoosen
            $sql = "select A.*, 
                    concat(A.productId, '.1.jpg') as image1, 
                    concat(A.productId, '.2.jpg') as image2,
                    concat(A.productId, '.3.jpg') as image3,
                    B.heel, B.size, B.counter, C.price from 
            tbl_product_def A left outer join tbl_product_inv B on A.productId = B.productId, 
            tbl_price_group C 
            where C.priceGroup = A.priceGroup";
            $result = null;
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            if($result){
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            }else{
                return $response->withJson(array('status' => 'No entries found in database', 'result'=>array()),422);
            }
                   
        } catch (Exception $e) {
            return $response->withJson(array('error' => $e->getMessage()),422);
        }
    }

    public function inventory($request, $response) 
    {
        try{
            $con = $this->db;
            $sql = "SELECT * FROM inventory order by id";
            $result = null;
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            if($result){
                return $response->withJson(array('status' => 'true','result'=>$result, 'Content-Type'=>'application/json; charset=utf-8'),200);
            }else{
                return $response->withJson(array('status' => 'Users Not Found', 'result'=>$result),422);
            }
        } catch (PDOException $e) {
            return $response->withJson(array('error' => $e->getMessage()),422);
        }   
    }

    public function productInventory($request, $response) 
    {
        try {
            $con = $this->db;
            $sql = "SELECT * FROM v_inventory";
            $result = null;
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            if($result){
                return $response->withJson(array('status' => 'true','result'=>$result, 'Content-Type'=>'application/json; charset=utf-8'),200);
            }else{
                return $response->withJson(array('status' => 'Inventory Not Found', 'result'=>array()), 422);
            }
        } catch (PDOException $e) {
            return $response->withJson(array('error' => $e->getMessage()),422);
        }   
       
    }
    
    public function shopShowImages($request, $response) 
    {
        
        if(is_dir($imageDirectory))
        {
            $opendirectory = opendir($this->imageDir);
        
            while (($image = readdir($opendirectory)) !== false)
            {
                if(($image == '.') || ($image == '..'))
                {
                    continue;
                }
                
                $imgFileType = pathinfo($image,PATHINFO_EXTENSION);
                
                if(($imgFileType == 'jpg') || ($imgFileType == 'png'))
                {
                    echo "<h4>$image</h4>";
                    echo '<img src=' . $imageDirectory . $image . '/>';
                }
            }
            
            closedir($opendirectory);
        
        } else {
            echo "Directory $imageDirectory not found";
        }
    }

    // file_exists(fname) 
    // file unlink(fname)no
  
    
    protected function _getImageNames() 
    {
        $allowed = array('.gif','.jpg','.jpeg','.png');
        $result = null;
        
        // Check if imageDirectory exists
        if (is_dir($this->imageDir))
        {
            $opendirectory = opendir($this->imageDir);
        
            while (($image = readdir($opendirectory)) !== false)
            {
                if (!in_array(strtolower(strrchr($image, '.')), $allowed)) {
                    continue;
                }

                $result[] = $image;
            }
            closedir($opendirectory);
        } else {
            $this->logger->error('Directory ' . $imageDirectory . ' does not exist');
        }

        return($result);
    }    

    protected function _renameImages($shoppingCartList, $updateDatabase) {
        // Rename images in array
        $update_count=0;
        foreach ($shoppingCartList as $p) {
            if ($this->_renameImage($p, $updateDatabase)) {
                $update_count++;
            }    
        } 
        return ($update_count);        
    }            
    

    public function renameImages($request, $response) 
    {
        try{
            // Load into result array
            $result = null;
            $con = $this->db;
            $sql = "SELECT * from `tbl_products` where length(`image`) > 0";
                foreach ($con->query($sql) as $product) {
                $shoppingCartList[]=$product;
            } 

            $update_count=$this->_renameImages($shoppingCartList, true);

            $result=$this->_getImageNames();    

            if($result) {
                return $response->withJson(array('status' => 'true', 'update_count'=>$update_count, 'result'=>$result) ,200);
            } else{
                return $response->withJson(array('status' => 'No entries updated in database'),422);
            }
        } catch (PDOException $e) {
            return $response->withJson(array('error' => $e->getMessage()),422);
        }
    }
    
    public function renameImages2($request, $response) 
    {
        $input = $request->getParsedBody();
        $shoppingCartList=$input['products'];
        
        $update_count=0;
        $this->logger->addDebug('Number of fetched products:' . count($shoppingCartList));

        // Rename images with new productId.<imageNumber>.jpg
        $update_count=$this->_renameImages($shoppingCartList, true);
        $this->logger->addDebug('Number of renamed images:' . $update_count);

        // $this->insertNewInventory($inventory);

        $result=$this->_getImageNames();    

        if($result) {
            return $response->withJson(array('status' => 'true', 'update_count'=>$update_count, 'result'=>$result) ,200);
        } else{
            return $response->withJson(array('status' => 'No entries updated in database'),422);
        }
    }

    

    public function shopImages($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        //$imageDirectory = $allGetVars['dirname'];
        $result = $this->_getImageNames();
        
        if ($result != null) 
        {
            if ($result) {
                return $response->withJson(array('status' => 'true','result'=>$result) ,200);
            } else {
                return $response->withJson(array('status' => 'No entries found in database'),422);
            }
            
            closedir($opendirectory);
        
        } else {
            return $response->withJson(array('status' => 'Directory ' . $imageDirectory . 'does not exist'),422);
        }
    } 

    // Convert the size/count string to an array
    protected function _normalizeSizeCount($sizeCount)
    {
        $arrSizeCount = explode(",", $sizeCount);
       
        // Each value contains size/value (ex 37/2)
        $arrCount=array();
        foreach ($arrSizeCount as &$value) {
            $arr = explode('/', $value);
            $size = trim($arr[0]);
            $sld = trim($arr[1]);
            $arrCount[$size] = $sld;  
            // echo 'arrCount[' . $size . '] = ' . $arrCount[$size] . '<br />';
        }
        return $arrCount;
    }

    // Increment the count of the specified size
    protected function _incrementSizeCount($arrCount, $size, $increment) {
        // echo 'Input: size:' . $size . ' increment=' . $increment . '<br />';
        if (array_key_exists($size, $arrCount)) {
            // echo 'before increment arrCount[' . $size . '] = ' . $arrCount[$size] . '<br />';
            $arrCount[$size] += $increment;
            $arrCount[$size] = max($arrCount[$size], 0);
            // echo 'after increment arrCount[' . $size . '] = ' . $arrCount[$size] . '<br />';
        } 
        return $arrCount;
    }

    // Denormalize the array to a string
    protected function _denormalizeSizeCount($arrCount) {
        $sizeCount='';
        foreach ($arrCount as $key=>$value) {
            $sizeCount = $sizeCount . $key . '/' . $value;   
            // Don't add comma-separator after last element 
            end($arrCount);
            if ($key !== key($arrCount)) {
                $sizeCount=$sizeCount . ', ';
            }
        }
        // echo 'sizeCount after update:' . $sizeCount . '<br />';
        return $sizeCount;
    }

    protected function _updateSizeCount($sizeCount, $size, $increment) {
        // Create arrCount array with size as index
        $arrCount=$this->_normalizeSizeCount($sizeCount);
        // Increment size 
        $arrCount=$this->_incrementSizeCount($arrCount, $size, $increment);
        // Denormalize arrCount array to a string with commas and slashes
        $sizeCountNew=$this->_denormalizeSizeCount($arrCount);

        // Return the new size counter
        return $sizeCountNew;
    }
    
    protected function _insertProductDef($insertObj)
    {
        $table='tbl_product_def';
        $duplet=$this->_build_sql_insert_duplet($table, $insertObj);
        if ($this->_sqlInsert($duplet['sql'], $duplet['values'])) {
            $this->logger->info('OK: Successful insert of productId = ' . $insertObj['newProductId'] . ' into table tbl_product_def');
        } else {
            $this->logger->error('ERROR: Could not insert productId = ' . $insertObj['newProductId']  . ' into table tbl_product_def');
        }
        return(true);
    }



    public function incrementCount($request, $response)
    {
        $queryParams = $request->getQueryParams();
        $sizeCount=$queryParams['sizeCount'];

        $size=37;
        $increment=5;

        // Create arrCount array with size as index
        $sizeCountNew = $this->_updateSizeCount($sizeCount, $size, $increment);
        
        return '<h1>Hello</h1>';
    }

    protected function _sell($id, $size, $increment) 
    {
        $sizeCountNew='Unset value';
        try {
            $con = $this->db;
            $con->beginTransaction();

            $sizeCount = $this->_getSingleValue('tbl_products', 'id', $id, 'sizeCount');
            
            $this->logger->addDebug('Old sizeCount:' . $sizeCount);
            // echo 'sizeCount = ' . $sizeCount . '<br />';

            // Change sizeCount to reflect the latest $increment for a particular $size
            $sizeCountNew = $this->_updateSizeCount($sizeCount, $size, $increment);

            $this->logger->addDebug('New sizeCount:' . $sizeCountNew);

            $this->_updateSingleValue('tbl_products', 'id', $id, 'sizeCount', $sizeCountNew);
            $con->commit();

        } catch (PDOException $e) {
            $this->logger->addDebug('TkShopController._insert: insertId:' . $e->getMessage());
            return null;
        } 
        return $sizeCountNew;
    }




    // Sell one product with productId and size
    public function sell($request, $response) 
    {
        $queryParams = $request->getQueryParams();
        $id = $queryParams['id'];
        $size = $queryParams['size'];
        $increment = $queryParams['increment'];
        // $productId = $queryParams['productId'];

        echo 'id = ' . $id . '<br />';
        echo 'size = ' . $size . '<br />';
        echo 'increment = ' . $increment . '<br />';

        $sizeCountNew=$this->_sell($id, $size, $increment);
        echo 'sizeCountNew = ' . $sizeCountNew . '<br />';

        if ($sizeCountNew !== null) {
            $data = array('status' => 'OK','message'=>'Sell request OK for id=' . $id . ' and size=' . $size);
            $response->withJson($data, 201);    
        } else     
            $data=array('status' => 'ERROR', 'message'=> 'Failed to update for id=' . $id . ' and size=' . $size);
            $response->withJson($data, 422);
        ;
    }

    // Sell one product with productId and size
    public function postSell($request, $response) 
    {
        $payload = $request->getParsedBody();
        $keys=$payload['keys'];
        $id=$keys['id'];                
        // $productId=$keys['productId'];
        $data=$payload['data'];
        $size=$data['size'];
        $increment=$data['increment'];

        $sizeCountNew=$this->_sell($id, $size, $increment);

        if ($sizeCountNew !== null) {
            return $response->withJson(array('status' => 'OK', 'result' => $sizeCountNew), 201);
        } else {
            return $response->withJson(array('status' => 'ERROR', 'result' => 'No entries found in database'), 422);
        }
    }

    protected function _redirectOrder($productType, $productList) 
    {
        foreach($productList as $obj) {
            switch ($productType) {
                case "AAA":
                    $this->logger->addDebug('Function:case ' . 'AAAA' );
                    break;
                case "BBB":
                    $this->logger->addDebug('Function:case ' . 'BBBB' );
                    break;
                case "CCC":
                    $this->logger->addDebug('Function:case ' . 'CCCC' );
                    break;
                case "DDD":
                    $this->logger->addDebug('Function:case ' . 'DDDD' );
                    break;
                default:
                    $this->logger->addDebug('Function:case ' . 'XXXXXXX' );
            }
        }
    }

    protected function _insertOrderLine($table, $order, $requiredKeys, $reduceToKeys) {
        if (($str = $this->_checkMandatoryInput($order, $requiredKeys))===null) {
            $obj = $this->_objectReduceToKeys($order, $reduceToKeys); 
            $obj += ['orderId'=>$order['orderId']];
            $duplet = $this->_build_sql_insert_duplet($table, $obj);
            if (!$this->_sqlInsert($duplet['sql'], $duplet['values'])) {
                $this->logger->addDebug('ERROR: failed to insert:' . $sql);
                return(false);
            }   
        } else {
            $this->logger->info('No record insered into ' . $table);
        }    

        return true;
    }    

    protected function _insertProductsForProductType($table, $orderId, $productType, $arr, $reduceToKeys) {

        $newArr = array();
        foreach($arr as $pr) {
            $newPr=$pr;
            $newPr += ['orderId'=>$orderId];
            $newArr[] = $newPr;
        }

        if (count($newArr) > 0) {
            $this->logger->info('Adding products for productType:' . $productType . ' orderId:' . $orderId);
            $reducedArr = $this->_arrayReduceToKeys($newArr, $reduceToKeys); 
            if ($this->_replaceRowsInTable($table, $reducedArr)) {
                return true;
            } else {
                return false;
            }
            $this->logger->info('No products found in table ' . $table);
        } 
        return true;
    }    


    public function _insertProducts($orderId, $productList) 
    {
        $groupByArr = $this->_groupByArr($productList, 'productType');
        foreach ($groupByArr as $key=>$arr) {
            $this->logger->info('groupByArr [' . $key . ']');
            switch ($key) {
                case 'package':    
                    if (!$this->_insertProductsForProductType('tbl_order_package', $orderId, $key, $arr, ['orderId', 'productType', 'productId'])) {
                        return false;
                    }
                break;

                case 'shoe':    
                    if (!$this->_insertProductsForProductType('tbl_order_shoe', $orderId, $key, $arr, ['orderId', 'productType', 'productId', 'size'])) {
                        return false;
                    }    
                break;

                default:    
                    if (!$this->_insertProductsForProductType('tbl_order_product', $orderId, $key, $arr, ['orderId', 'productType', 'productId'])) {
                        return false;
                    }
                break;    
            }
        }
        return true;
    }

    public function insertOrder($order) 
    {
        $con = $this->db;
        $insertObj = $this->_objectReduceToKeys($order, ['amount', 'status', 'eventType']); 
        if (!isset($order['status'])) {
            $insertObj += ['status'=>'OK'];
        }
        $table='tbl_order';
        $duplet=$this->_build_sql_insert_duplet($table, $insertObj);

        // Insert into tbl_order and get orderId
        if (!$this->_sqlInsert($duplet['sql'], $duplet['values'])) {
            return -1001;
        } 
        $orderId = $con->lastInsertId();
        $order += ['orderId'=>$orderId];

        if (isset($order['role'])) {
            if ($order['role']===LEADER) {
                $order += ['rolePartner'=>FOLLOWER];
            } else {
                $order += ['rolePartner'=>LEADER];
            }    
        } else if (isset($order['leader'])) {
            if ($order['leader']==1) {
                $order += ['role'=>LEADER];
                $order += ['rolePartner'=>FOLLOWER];
            } else {
                $order += ['role'=>FOLLOWER];
                $order += ['rolePartner'=>LEADER];
            }    
        } else {
            $order += ['role'=>LEADER];
            $order += ['rolePartner'=>FOLLOWER];
        }    

        // If email exists insert into tbl_order_name
        if (!$this->_insertOrderLine('tbl_order_customer', $order, ['email'], ['orderId', 'firstName', 'lastName', 'email', 'phone', 'role'])) {
            $this->logger->warning("No insert to tbl_order_customer for orderId=$orderId");
        }
        if (!$this->_insertOrderLine('tbl_order_dance_partner', $order, ['firstNamePartner'], ['orderId', 'firstNamePartner', 'lastNamePartner', 'emailPartner', 'phonePartner', 'rolePartner'])>0) {
            $this->logger->warning("No insert to  tbl_order_dance_partner for orderId=$orderId");
        }
        if (!$this->_insertOrderLine('tbl_order_address', $order, ['address'], ['orderId', 'address', 'city', 'country', 'zip'])) {
            $this->logger->warning("No insert to  tbl_order_address for orderId=$orderId");
        }
        if (!$this->_insertOrderLine('tbl_order_package', $order, ['packageId'], ['orderId', 'packageId'])) {
            $this->logger->warning('No insert to  tbl_order_package');
        }
        /*
        if (is_array($order['productList'])) {
            if (count($order['productList'])>0) {
                if (!$this->_insertProducts($orderId, $order['productList'])) {
                    $this->logger->warning("Failed to insert productList into tbl_order_product for orderId=$orderId");
                } 
            } else {
                $this->logger->warning('No records in productList');
            }   
        } else {
            $this->logger->warning('No productList in request');
        }
        */
        return($orderId);
    }    

    public function insertOrderProductWithType($orderId, $productType, $productList) 
    {
        $cnt=0;
        $reducedList = $this->_arrayReduceToKeys($productList, ['productType', 'productId']); 
        foreach($reducedList as $p) {
            $p += ['orderId'=>$orderId, 'productType'=>$productType]; 
        }    
        $this->_replaceRowsInTable('tbl_order_product', $rows); 
        return($cnt);
    }

    // Increment inventory countLeader/countFollower in table tbl_Registrations whenever a registration is made
    protected function _update_course_count($id, $leader, $increment) 
    {
        $table='tbl_Registrations';
        $countNew=0;
        $countOld=0;
        $updateColumn=$leader?'countLeader':'countFollower';
        try {
            $countOld = $this->_getSingleValue($table, 'id', $id, $updateColumn);
            $this->logger->addDebug('countOld:' . $countOld);

            // Change sizeCount to reflect the latest $increment for a particular $size
            $countNew = $countOld + $increment;
            $this->logger->addDebug('countNew:' . $countNew);

            $this->_updateSingleValue($table, 'id', $id, $updateColumn, $countNew);

        } catch (PDOException $e) {
            $this->logger->addDebug('TkShopController._insert: insertId:' . $e->getMessage());
            return null;
        } 
        // $this->logger->addDebug('UPDATE new sizeCount:' . $countNew);
        return $countNew;
    }


    protected function _sumArray($arr, $fieldKey) 
    {
        $sum = 0;    
        foreach ($arr as $obj) {
            $sum += (int) $obj[$fieldKey];                
        } 
        return $sum;
    }
    protected function _discount($shoppingCartList) {
        $Discount = $this->container['Discount'];
        // $discounts = $Discount->getDiscounts();
        $discounts = $Discount->getDiscounts();
        $arr = $Discount->calcDiscountFromShoppingCart($discounts, $shoppingCartList);
        return $arr['totalDiscount'];
    }

    protected function _amountParts($shoppingCartList) {
        $sumPrice = $this->_sumArray($shoppingCartList, 'price');
        $discount = $this->_discount($shoppingCartList);
        $data = array();
        $data['amount']=$sumPrice - $discount; 
        $data['discount']=$discount;
        $data['currency']='SEK';
        return $data;
    }
    protected function _createOrderTest($customer, $shoppingCartList, $currency) 
    {
        $responseArray = array('code' => 200, 'status' => 'OK', 'result' => $shoppingCartList);
        return($responseArray);
    }


    protected function _analyzeOrder($order, $shoppingCartList) 
    {
        return true; 
    }

    protected function testObject() {
        $arrTest = array();
        $arrTest += ['orderId' => 100 ];
        $arrTest += ['status' => 'HK'];
        $arrTest += ['productType' => 'course' ];
        $arrTest += ['id' => 55];
        $arrTest += ['productId' => 55];
        $arrTest += ['firstName' => 'Gunnar' ];
        $arrTest += ['lastName' => 'Janzon' ];
        $arrTest += ['email' => 'gunnar.janzon@swipnet.net' ];
        $arrTest += ['firstNamePartner' => 'Lisa' ];
        $arrTest += ['lastNamePartner' => 'Janzon' ];
        $arrTest += ['emailPartner' => 'lisa.janson@swipnet.net' ];
        return $arrTest;
    }

        // Post the order
    public function testOrder($request, $response)
    {
        $allGetVars = $request->getQueryParams();

        $arr = array();
        $arr[] = $this->testObject();
        $arr[] = $this->testObject();
        $arr[] = $this->testObject();
        $arr[] = $this->testObject();
        $arr[] = $this->testObject();
        
        $orderId=98765;
        if ($this->_insertAllReg($orderId, $arr)) {
            return $response->withJson($arr, 200);
        } else {
            return(array('status'=>'ERROR', 'message'=>'Problems with _insertAllReg()'));
        }
    }   

    protected function _fetchProducts($orderId) {
        $table='tbl_order_products';
        $this->logger->addDebug('_fetchProducts:' . $table);
        $con = $this->db;
        $sql = "SELECT * FROM `$table` where `orderId`='$orderId'";
        $this->logger->addDebug('_fetchProducts - sql:' . $sql);
        $rows = null;
        foreach ($con->query($sql) as $row) {
            $rows[] = $row;
        } 
        $this->logger->addDebug('Number of fetched records:' . count($rows));
        return $rows;
    }    

    protected function _mailBodyRecieptOld($firstName, $orderId, $amount, $currency) {

        $ret="<h3>Dear $firstName </h3>
        <p>            
        Thanks for your order with id $orderId to Tangocompaniet.<p/>
        This is a receipt that Tangokompaniet has recieved an amount of $amount $currency for order id $orderId.<p/>  
        If you have any questions regarding your order write to daniel@tangokompaniet.com<p/>
        </p>
        <h4>Best regards,</h4>
        <h4>Tangokompaniet</h4>";
        return $ret;
    }    


    protected function _insertSingleReg($reg) 
    {
        $returnValue = false;
        $table='tbl_registration';

        // Define cols for reduced Reg
        $cols=['productType', 'status', 'productId', 'leader', 'firstName', 'lastName', 'email', 'phone', 'firstNamePartner', 'lastNamePartner', 'emailPartner', 'message', 'orderId', 'token'];
        if (isset($reg['phonePartner'])) {
            $cols[]='phonePartner';
        }     
        if (isset($reg['label'])) {
            $cols[]='label';
        }     
        if (isset($reg['danceSite'])) {
            $cols[]='danceSite';
        }     
        if (isset($reg['maxRegistrations'])) {
            $cols[]='maxRegistrations';
        }     
        $reducedReg=$this->_intersectionOfKeys($reg, $cols);
      
        // Insert or replace a registration
        $this->logger->addDebug('before _replaceRow for registrator:' . TBL_REGISTRATION);
        if (!$this->_replaceRow('tbl_registration', $reducedReg)) {
            return false;
        } 

        // Insert into phonebook
        $colsPhonebook=['firstName', 'lastName', 'email', 'phone', 'newsletter'];
        $phonebook=$this->_intersectionOfKeys($reg, $colsPhonebook);
        $updKeys = ['phone','newsletter'];
        $this->logger->addDebug('before _insertOrUpdateRowInTable:');
        
        if (!$this->_insertOrUpdateRowInTable(TBL_PHONEBOOK, $phonebook, $updKeys)) {
            $this->logger->error('Failed to insert or update registrant in tbl_phonebook');
            return(false);
        }

        // If partner is filled in, then register partner as well
        if (isset($reducedReg['firstNamePartner']) && isset($reducedReg['lastNamePartner'])) {
            $reducedRegPartner = $this->_createRegPartner($reducedReg);
    
            // Insert partners registration into TBL_REGISTRATION
            /*
            $this->logger->addDebug('_insertSingleReg: before _replaceRow for reducedRegPartner');
            if (!$this->_replaceRow(TBL_REGISTRATION, $reducedRegPartner)) {
                return false;
            } 
            */   

            // Insert partner into phonebook
            $this->logger->addDebug('_insertSingleReg: before _insertOrIgnoreRowsIntoTable');
            $colsPhonebook=['firstName', 'lastName', 'email', 'phone', 'newsletter'];
            $phonebook=$this->_intersectionOfKeys($reducedRegPartner, $colsPhonebook);
            if (!$this->_insertOrIgnoreRowIntoTable(TBL_PHONEBOOK, $phonebook)) {
                $this->logger->error('Failed to insert or update partner in tbl_phonebook');
            }

        } 
        return(true);
    }    



    protected function _insertAllReg($orderId, $token, $regs) {
        $nbrReg=count($regs);
    
        $this->logger->addDebug('XXXXX');
        // Loop over all registrations of type 'course'
        $nbrAcc=0;
        $nbrDup=0;
        $acceptedRegs = array();
        foreach($regs as $reg) {
            if (isset($orderId)) {
                $reg['orderId'] = $orderId;
            }    
            if (isset($token)) {
                $reg['token'] = $token;
            }    
            $returnValue=$this->_insertSingleReg($reg);
            if ($returnValue) {
                // $this->_insertPhonebook($reg); // Turn on this whenever Daniel wants a phone book
                $acceptedRegs[] = $reg;
                $nbrAcc++;
            } else {
                $this->logger->addDebug('WARNING: Duplicate registration of productId ' . $reg['productId'] . ' is rejected');
                $nbrDup++;    
            }
        }   
        $this->logger->addDebug('OK: Number of accepted registrations:' . $nbrAcc);
        if ($nbrDup > 0) {
            $message = 'WARNING: Number of rejected double registrations:' . $nbrDup;
            $this->logger->addDebug($message);
        }   


        // Return array object 
        return(array('acceptedRegs'=>$acceptedRegs,
                    'nbrReg'=>$nbrReg,
                    'nbrAcc'=>$nbrAcc,
                    'nbrDup'=>$nbrDup,
                ));
    }

    protected function _createOrder1($customer, $shoppingCartList, $currency) {
        // Step 1: Insert customer into tblOrder and return an orderId (sequence number)
        $orderId = $this->insertOrder($customer);
        $this->logger->addDebug('_createOrder, orderId=' . $orderId);

        $responseArray = array(
            'code' => 200,
            'status' => 'OK', 
            'orderId' => $orderId,
        );
        return($responseArray);    
    }

    protected function _insertRegistrations($orderId, $token, $groupByArr) {

        // Registrations (productType='course', 'workshop', 'marathon')
        $regs = array();
        if (isset($groupByArr['course'])) {
            $regs = array_merge($regs, $groupByArr['course']);
        }    
        if (isset($groupByArr['social'])) {
            $regs = array_merge($regs, $groupByArr['social']);
        }
        return $this->_insertAllReg($orderId, $token, $regs);
    }


    protected function _getOrderSQL()
    {
        return("SELECT 
        OP.id, O.orderId, 
        CU.firstName, CU.lastName, CU.email, CU.phone,
        DP.firstNamePartner, DP.lastNamePartner, DP.emailpartner, DP.phonePartner, DF.nameEN as name, 
        OP.productId, 
        OP.productId as groupByProductId, 
        WEEKDAY(CO.startDate) + 1 as dayOfWeek, SI.siteName, CO.teachersShort, CO.startDate, OP.productType, OS.size,     
        O.creaTimestamp, O.updTimestamp 
            from tbl_order O
            left outer join tbl_order_customer CU on CU.orderId = O.orderId
            left outer join tbl_order_dance_partner DP on DP.orderId = O.orderId
            left outer join tbl_order_address AD on AD.orderId = O.orderId
            left outer join tbl_order_product OP on OP.orderId = O.orderId
            left outer join tbl_order_shoe OS on OS.orderId = O.orderId
            left outer join tbl_course CO on CO.productId = OP.productId
            left outer join tbl_course_def DF on DF.courseId = CO.courseId
            left outer join tbl_site SI on SI.siteId = CO.siteId
            left outer join tbl_price_group PG on PG.priceGroup = DF.priceGroup  
            ORDER BY `O`.`orderId`  DESC"
        );
    }    

    
    protected function _getOrder($sql) 
    {    
        try{
            $result=array();
            $con = $this->db;
            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            return $result;
        }
        catch(\Exception $ex){
            return null;
        }
    }    

    public function getOrder($request, $response) 
    {
        $sql=$this->_getOrderSql();
        $arr = $this->_getOrder($sql); 

        if($arr != null) {
            return $response->withJson(array('status' => 'true','result'=>$arr) ,200);
        } else{
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }
    }

    protected function _createOrder($customer, $shoppingCartList, $currency, $language) 
    {
        $this->logger->addDebug('------_createOrder ------');

        // Step 1: Insert customer into tblOrder and return an orderId (sequence number)
        $orderId = $this->insertOrder($customer);
        $this->logger->info('Order inserted into tbl_order, returned orderId=' . $orderId);
        
        $prefix = isset($orderId)?$orderId['email']:'';
        $prefix .= isset($customer['email'])?$customer['email']:'';
        $token = uniqid($prefix, false);

        $groupByArr = $this->_groupByArr($shoppingCartList, 'productType');
        $regObj = $this->_insertRegistrations($orderId, $token, $groupByArr);
        $this->logger->info('Registrations inserted into tbl_registration for order id=' . $orderId);

        // Shoes
        $shoes=array();
        if (isset($groupByArr['shoes'])) {
            $shoes = $groupByArr['shoes'];
        }    
        
        $this->logger->info('begin Transaction _createOrder');

        $con = $this->db;
        $con->beginTransaction();

        // Step 2: Reject double registrations
        $numberOfShoes=count($shoes);
        $numberOfProducts = $numberOfShoes + $regObj['nbrAcc'];
        $products = array_merge($shoes, $regObj['acceptedRegs']);

        $this->logger->info('Before caluclating amountParts');

        // Calculate new total price
        $amountArray = $this->_amountParts($products);

        $this->logger->info('After caluclating amountParts');
        $discount=$amountArray['discount'];
        $amount=$amountArray['amount'];

        $this->logger->addDebug('OK: orderId:' . $orderId);
        $this->logger->addDebug('OK: Number of shoes:' . $numberOfShoes);
        $this->logger->addDebug('OK: Total price:' . $amount . ' ' . $currency) ;
        $this->logger->addDebug('OK: Price is reduced  with discount:' . $discount . ' ' . $currency);
        
        // Step 3: Insert the saved products tbl_order_product
        if ($numberOfProducts > 0) {
            // Update table tbl_order with $paidAmount, but only if not updated before
            $data=array('amount'=>$amount, 'discount'=>$discount);
            $sql=$this->_build_sql_update('tbl_order', $data, "orderId='$orderId'");
            if (!$this->_sqlExecute($sql)) {
                $this->logger->error('Failed to update orderId ' . $orderId . ' with paidAmount = ' . $amount);
                return $response->withJson(array('status' => 'WARNING', 'message'=>'accessToken mismatch'),500);
            }
            $numberOfInsProducts = $this->insertOrderProduct($orderId, $products);
            $con->commit();

            // Send mail with remaining products
            $responseArray = array(
                'code' => 200,
                'status' => 'OK', 
                'order' => array(
                    'id' => $orderId,
                    'amount' => $amount, 
                    'discount' => $discount, 
                    'currency' => $currency,
                ),    
                'meta'=>array(
                    'message' =>'',
                    'numberOfProducts' => $numberOfProducts,
                    'numberOfShoes' => $numberOfShoes,
                ), 
                'shoppingCartList' => $products,
                'message'=> 'OK'
           ); 
           // Add payerAlias if swish payment
           if (isset($customer['payerAlias'])) {
                $responseArray['order'] += ['payerAlias' => $customer['payerAlias']];
           }
           if (isset($customer['email'])) {
                $recipient = $customer['email'];
           } else {
                $recipient = 'paelsis@hotmail.com';
           }     
           $subject = $this->_mailSubject($orderId, $language, 'ORDER');
           $body = $this->_mailBody($orderId, $language);
           $this->logger->addDebug('mail recipient:' . $recipient);
           if ($this->_sendMail($subject, $body, $recipient)) {
                $this->logger->addDebug('Order ' . $orderId . ' was successfully processed and mail sent');
           } else {
                $this->logger->warning('Order ' . $orderId . ' was successfully processed and mail sent');
           }
           return($responseArray);
        } else {
            $con->rollBack();
            // Rollback if no remaining products
            return array(
                'code' => 206,
                'status' => 'WARNING',
                'message' => 'You have no remainig products (probably double registrations)'
            );
        }    
    }


    // Post the registration only
    public function createRegistration($request, $response) 
    {
        $data = $request->getParsedBody();
        $reg = $data;    

        $strReg = json_encode($reg);
        
        // Array with one element in (to emulate a shooping cart)
        if (!isset($reg['productType'])) {
            $reg['productType'] = 'course';
        }    
        if (!isset($reg['leader'])) {
            $reg['leader'] = true;
        }       
        // Insert order into tbl_order and get in return orderId
        $orderId = $this->insertOrder($reg);
        $reg['orderId'] = $orderId;

        // Create an array with one registration (to work with array routne below
        $arr = array(); 
        array_push($arr, $reg);
        $this->logger->info('after array_push');

        $groupByArr = $this->_groupByArr($arr, 'productType');
        $this->logger->info('after _groupByArr');

        $prefix = isset($reg['productId'])?$reg['productId']:'';
        $prefix .= isset($reg['email'])?$reg['email']:'';

        $token = uniqid($prefix, false);
        $reg['token'] = $token;
        
        $regObj = $this->_insertRegistrations($orderId, $token, $groupByArr);
        $this->logger->info('after _insertRegistrations');

        if (isset($regObj['nbrReg'])) {
            if ($regObj['nbrReg'] > 0) {
                $body = $this->_sendMailReg($reg);
                $this->logger->info('after _sendMailReg');
                return $response->withJson(array('status'=>'OK', 
                    'orderId'=> $orderId, 
                    'mailBody'=> $body,
                    'nbrReg'=> $regObj['nbrReg'],
                    'nbrAcc'=> $regObj['nbrAcc'],
                    'nbrDup'=> $regObj['nbrReg'],
                    'message'=>'Successful registration of course'), 200);
            } else {    
                $this->logger->info('No registrations, after _sendMailReg');
                return $response->withJson(array('status'=>'ERROR',
                    'orderId'=> $orderId, 
                    'body'=> $body,
                    'nbrReg'=> $regObj['nbrReg'],
                    'nbrAcc'=> $regObj['nbrAcc'],
                    'nbrDup'=> $regObj['nbrDup'],
                    'message'=>'Failed to make registration'), 204);
            } 
        } else {
            $this->logger->error('regObj[acceptedRegs] not found');
            return $response->withJson(array('status'=>'ERROR','message'=>'ERROR: No reply object (regObj) from createRegistration'), 204);
        }
    }
    
    protected function productStringForMail($productList) 
    {
        //return json_encode($productList);

        $productString = "";
        if (isset($productList)) {
            $arr = array('product', 'firstNamePartner', 'lastNamePartner');
            forEach ($productList as $pr) {
                if (strlen($productString) > 0) {
                    $productString .= ', ';
                }
                $productString .= "[" . $pr['product'];
                if (isset($pr['firstNamePartner']) && isset($pr['lastNamePartner'])) {
                    $productString  .= ' with partner ' . $pr['firstNamePartner'] . ' ' . $pr['lastNamePartner']; 
                }    
                $productString .= "]";
            } 
        }
        return $productString;
    }

    


    // Post the order
    public function createOrder($request, $response) 
    {
        $payload = $request->getParsedBody();
        if (!isset($payload['customer']) ||
            !isset($payload['shoppingCartList']) || 
            !isset($payload['currency'])) {
            return $response->withJson(array('code'=>400, 'status'=>'ERROR','message'=>'ERROR: Bad request'), 400);
        }    

        $customer=$payload['customer'];
        $shoppingCartList=$payload['shoppingCartList'];
        $currency=$payload['currency'];

        $language='SV';
        if (isset($payload['language'])) {
            $language=$payload['language'];
        }    

        // Create result array
        $responseArray=$this->_createOrder($customer, $shoppingCartList, $currency, $language);  
        if (isset($responseArray['code'])) {
            $code=$responseArray['code'];
            if ($code?$code <= 1000:false)  {
                return $response->withJson($responseArray, $code);
            } else {    
                return $response->withJson(array('code'=>$code, 'message'=>'Not acceptable'), $code);
            } 
        } else {
            return $response->withJson(array('code'=>500, 'message'=>'ERROR: No code in reply from _createOrder'), 9999);
        }
    }
}
