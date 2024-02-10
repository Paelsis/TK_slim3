<?php

namespace App\Controllers;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ava status
define("AVA_OPEN", "AV");
define("AVA_CLOSED_ON_REQUEST", "CR");
define("AVA_CLOSED", "CC");
const SHOP_DIR = './images/shop'; 


class TkInventoryController extends Controller
{
    protected function _logObject($obj) 
    {
        foreach($obj as $key => $val) {
            $this->logger->addDebug('  ' . $key . ' = '  . $val);
        }
    }    

    protected function _logList($list) {
        $cnt=0;
        foreach($list as $obj) {
            $this->logger->addDebug('Object:' . $cnt++);
            $this->_logObject($obj);
        }   
    }


    protected function _execute($sql) 
    {
        try {
            $con = $this->db;
            $sth = $con->prepare($sql);
            $sth->execute();
            $this->logger->addDebug('OK: Successful INSERT:' . $sql);
        } catch(\Exception $ex) {
            $this->logger->addDebug('ERROR: Failed to execute SQL-statement:' . $sql);
            return false;
        }
        return true;
    }    

    // Reduce object to only contain the fields given by names of array $keys
    protected function _objectReduceToProps($obj, $props)
    {
        $reduced_obj=array();
        foreach($props as $p) {
            if (array_key_exists($p, $obj)) {
                // $this->logger->addDebug('OK: $f=' . $f . ' $p[$f]=' . $p[$f]);
                $reduced_obj[$p] = $obj[$p];
            } else {
                $this->logger->addDebug('_objectReduceToProps: ERROR: Key ' . $p . ' does not exist');
            }
        }    
        return $reduced_obj;
    }




    // Reduce the objects of an array $arr to only include the fields given by names of array $keys     
    protected function _arrayReduceToProps($arr, $props) 
    {
        $reducedArr=array();
        foreach($arr as $obj) {
            $reducedObj = $this->_objectReduceToProps($obj, $props);
            array_push($reducedArr, $reducedObj);
        } 
        return $reducedArr; 
    }


    protected function _selectProductDef($productId) 
    {
        try{
            $con = $this->db;
            $sql = "SELECT D.*, P.price AS priceGroupPrice FROM tbl_product_def D, tbl_price_group P where productId = '$productId' and D.priceGroup = P.priceGroup";
            $row = $con->query($sql)->fetch();
            return($row);
        }
        catch(\Exception $ex){
            return (null);
        }
    }

    protected function _getImages($imageDirectory, $thumbnails) 
    {
        $allowed = array('.gif','.jpg','.jpeg','.png');
        $imageArr = [];

        // Check if imageDirectory exists
        if (is_dir($imageDirectory))
        {
            $opendirectory = opendir($imageDirectory);

            while (($entry = readdir($opendirectory)) !== false)
            {
                if (!in_array(strtolower(strrchr($entry, '.')), $allowed)) {
                    continue;
                }

                // If thumbnail
                if ($thumbnails) {
                    if (strpos($entry, '_thumb') === false) {
                        continue;
                    }    
                } else {
                    if (strpos($entry, '_thumb') !== false) {
                        continue;
                    }    
                }

                $lastModified = filemtime($imageDirectory . '/' . $entry);
                $path_parts=pathinfo($entry);
                $basename = $path_parts['basename'];
                $productId = str_replace('_thumb', '', strtok($basename, '.'));
                $sequenceNumber = strtok('.');
                if (!is_numeric($sequenceNumber)) {
                    $sequenceNumber=0;
                }
                $imageArr[] = ['filename'=>$entry, 'sequenceNumber'=>$sequenceNumber, 'productId'=>$productId, 'lastModified' =>$lastModified];
            }
            // Sort images by productId and sequence number
            if (count($imageArr) > 0) {
                usort($imageArr, function($a, $b)
                {
                    $ret = strcmp($a['productId'], $b['productId']);
                    if ($ret === 0) {
                        $ret = $a['sequenceNumber'] - $b['sequenceNumber'];     
                    }
                    return $ret;
                });
            } else {
                $this->logger->warning('Directory ' . $imageDirectory . ' contains no images');
            }

            closedir($opendirectory);
        } else {
            $this->logger->error('Directory ' . $imageDirectory . ' does not exist');
            return(null);
        }
        return($imageArr);
    }    


    protected function _selectProductImages() 
    {
        try{
            $con = $this->db;
            $sql = "SELECT image, sequenceNumber, productId FROM tbl_product_image order by productId, sequenceNumber asc";
            $row = $con->query($sql);
            $arr = [];
            foreach ($con->query($sql) as $row) {
                $arr[$row['image']] = $row;
            }
            return($arr);
        }
        catch(\Exception $ex){
            return (null);
        }
    }

    public function getImages($request, $response)
    {
        $queryParams = $request->getQueryParams();
        
        $imageDir = '.';
        if (isset($queryParams['subdir'])) {
            $imageDir .=  '/' . $queryParams['subdir']; 
        } 

        $thumbnail = false;
        if (isset($queryParams['thumbnail'])) {
            $thumbnail = true;
        } 
        $result=$this->_getImages($imageDir, $thumbnail);

        if ($result === null) {
            return $response->withJson(array('status' => 'ERROR','result'=>null, 'message'=>'No directory') ,200);
        } else if (count($result)===0) {
            $message = 'No entries found in directory ' . $imageDir;
            return $response->withJson(array('status' => 'NOT FOUND', 'result'=>$result, 'message'=>$message),200);
        } else{
            return $response->withJson(array('status' => 'OK','result'=>$result) ,200);
        }   
    }    

    protected function _updateProductId($oldProductId, $newProductId)
    {
        
        // Update tbl_product_def  with productId or insert new record of tbl_product_def if it does not exist before 
        $sql="UPDATE `tbl_product_def` SET `productId` = '" . $newProductId . "' where `productId`='" . $oldProductId . "'";
        if ($this->_execute($sql)) {
            $this->logger->addDebug('OK: Update of tbl_product_def with new productId:' . $sql);
        } else {  
            // Try to insert productId 
            $insertObj=array('productId'=>$newProductId);
            $this->_insertProductDef($insertObj);
        }

        // Updaet tbl_product_inv with new product Id
        $sql="UPDATE `tbl_product_inv` SET `productId` = '" . $newProductId . "' where `productId`='" . $oldProductId . "'";
        if ($this->_execute($sql)) {
            $this->logger->addDebug('OK: Update of tbl_product_inv with new productId OK:' . $sql);
        } else {    
            $this->logger->error('ERROR: Failed to update tbl_product_inv with new productId');
            return(false);
        }

        return(true);
    }


    protected function _renameImage($imageDirectory, $pr, $updateDatabase) 
    {
        $sequenceNumber = $pr['sequenceNumber']; 
        $oldFile=null;
        $replyImage = $pr;

        $replyImage += ['code'=>401];
       
        if (strlen($pr['filename']) > 0) {
            $oldFile=$imageDirectory . '/' .$pr['filename'];
            if (!file_exists($oldFile)) {
                    $replyImage['code']=406;
                    $replyImage +=['message'=>'Old file ' . $oldFile . ' does not exist on disk'];
                    return($replyImage);
            }   
        } else {
            $this->logger->addDebug('No image for id ' . $pr['id']);
            return(false);
        }   
  
        $path_parts=pathinfo($oldFile);
        $oldFilename = $path_parts['basename'];
        $oldProductId = strtok($oldFilename, '.');
        $ext=$path_parts['extension'];
        // $id=$pr['id'];
        $newProductId=$pr['productId'];
        $newFilename=$newProductId . '.' . $sequenceNumber . '.' . $ext;
        $newFile=$imageDirectory . $newFilename;
        if (strcmp($oldFile, $newFile)===0) {
            // If oldFile is newFile try just to insert productId
            /*
            if ($updateDatabase) {
                $insertObj=array('productId'=>$newProductId, 'comment'=>$pr['comment']);
                $this->_insertProductDef($insertObj);
            } 
            */   
            $replyImage['code']=402;
            $replyImage +=['message'=>'File ' . $newFile . ' has the same name as before. No name change done.'];
            return($replyImage);
        } else if (file_exists($newFile)) {
            $this->logger->addDebug('Failed to rename file for product Id ' . $oldFile . ' to ' . $newFile . ' for old productId ' . $oldProductId);
            $replyImage['code']=403;
            $replyImage +=['message'=>'New file ' . $newFile . ' exists already. No rename done !'];
            return($replyImage);
        } else if (rename($oldFile, $newFile)===true) {
            if ($updateDatabase) {
                if ($this->_updateProductId($oldProductId, $newProductId)) {
                    $this->logger->addDebug('Renamed ' . $oldFile . ' to ' . $newFile . ' for productId ' . $oldProductId);
                } else {
                    $this->logger->error('ERROR: failed to update productId' . $oldProductId . ' to ' . $newProductId . ' in tables tbl_product_def and tbl_product_inv');
                    $replyImage['code']=404;
                    $replyImage += ['message'=>'ERROR'];
                    return($replyImage);
                }
            } 
            $replyImage['filename'] = $newFilename;
            $replyImage['code']=200;
            $replyImage += ['message'=>'OK'];
            $this->logger->addDebug('_renameImage: renamed ' . $oldFile . ' to ' . $newFile . ' for new productId ' . $newProductId);
            $this->logger->addDebug(' $replyImage[filename] ' . $replyImage['filename']);
            return($replyImage);
        } else {
            $this->logger->addDebug('Failed to rename file for product Id ' . $oldFile . ' to ' . $newFile . ' for productId ' . $oldProductId);
            $replyImage['code']=405;
            $replyImage +=['message'=>'Failed to rename file' . $oldFile . ' to ' . $newFile];
            return($replyImage);
        }
     
    }    


    public function renameImage($request, $response) 
    {
        $input = $request->getParsedBody();
        $image=$input['image'];
        $imageDir=SHOP_DIR;
        if (isset($input['subdir'])) {
            $imageDir .= $input['subdir'] . '/'; 
        } 
       
        $this->logger->addDebug(' image=' . $image['filename'] . 
            ' productId=' . $image['productId'] . 
            ' sequenceNumber='  . $image['sequenceNumber'] .
            ' imageDir='  . $imageDir);

            $this->logger->addDebug(' image=' . $image['filename'] . ' productId=' . $image['productId']. ' sequenceNumber='  . $image['sequenceNumber']);
        
        if (($replyImage=$this->_renameImage($imageDir, $image, true))!= null) {
            return $response->withJson(array('status' => 'true', 'result'=>$replyImage) ,200);
        } else {
            $image +=['result'=>'false'];
            return $response->withJson(array('status' => 'No entries updated in database', 'result'=>$replyImage),422);
        }
    }

    protected function _selectProductDefAll() 
    {
        try{
            $con = $this->db;
            $sql = "SELECT D.*, IFNULL(P.price, 0) AS price, D.priceGroup as priceGroupDEF, P.priceGroup as priceGroupPG FROM tbl_product_def D left outer join tbl_price_group P on D.priceGroup = P.priceGroup";
            $row = $con->query($sql);
            $productDefArr = [];
            foreach ($con->query($sql) as $row) {
                $productDefArr[$row['productId']] = $row;
            }
            return($productDefArr);
        }
        catch(\Exception $ex){
            return (null);
        }
    }

    protected function _getProducts($imageDir)
    {
        // Get all image names from IMAGE_DIR
        $imagesArr=$this->_getImages($imageDir, true);
        if($imagesArr === null) {
            $this->logger->error('WARNING: Image dir not foundNo images found in dir ERROR:' . $imageDir);
            return(null);
        }    
        // Get all tbl_product_def records
        $productDefArr=$this->_selectProductDefAll();
        // Get all tbl_product_inv records
        $productInvArr=$this->_selectProductInvAll();

        $productArr = array();

        // Loop through all images and add them to sufficient productId
        foreach ($imagesArr as $image) {
            $productId = $image['productId'];
            $sequenceNumber = $image['sequenceNumber'];
            if (!is_numeric($sequenceNumber)) {
                $sequenceNumber=0;
            }

            if (isset($productArr[$productId])) {
                $prod=$productArr[$productId];

                // Add images
                if (!isset($prod['images'])) {
                    $prod += ['images'=>[]];
                }    
                $prod['images'][] = $image['filename']; 
                sort($prod['images']);

                $productArr[$productId] = $prod;
            } else {
                // First time the productId is found
                $prod = array('productId'=>$productId);

                // Add props from tbl_product_def (if found)
                if (isset($productDefArr[$productId])) {
                    $prod += $productDefArr[$productId];
                };    

                // Add props from tbl_product_inv (of found)
                if (isset($productInvArr[$productId])) {
                    $prod += $productInvArr[$productId]; 
                } 
                $prod['images'][] = $image['filename'];

                $productArr[$productId] = $prod;
            } 
        }
        // Change from productId as index to 0,1,2 ... index */
        $productArrIndex = array();
        foreach ($productArr as $pr) {
            $productArrIndex[] = $pr;
        };

        return ($productArrIndex);
    }


    public function getProducts($request, $response)
    {
        $queryParams = $request->getQueryParams();

        $imageDir=SHOP_DIR;
        if (isset($queryParams['subdir'])) {
            $imageDir .= $queryParams['subdir'] . '/'; 
        } 

        $result=$this->_getProducts($imageDir);
        if($result != null){
            return $response->withJson(array('status' => 'OK','result'=>$result) ,200);
        } else{
            return $response->withJson(array('status' => 'ERROR', 'message'=>'No entries found directory . $imageDir'),422);
        }   
    }     



    protected function _renameImages($imageDir, $products, $updateDatabase) {
        // Rename images in array
        $update_count=0;
        foreach ($products as $p) {
            if ($this->_renameImage($imageDir, $p, $updateDatabase)) {
                $update_count++;
            }    
        } 
        return ($update_count);        
    }            


    public function renameImages($request, $response) 
    {
        $input = $request->getParsedBody();
        $products=$input['products'];

        $imageDir=SHOP_DIR;
        if (isset($input['subdir'])) {
            $imageDir .= $input['subdir'] . '/'; 
        } 

        
        $update_count=0;
        $this->logger->addDebug('Number of fetched products:' . count($products));

        // Rename images with new productId.<imageNumber>.jpg
        $update_count=$this->_renameImages($imageDir, $products, true);
        $this->logger->addDebug('Number of renamed images:' . $update_count);

        $result=$this->_getImages($imageDir, false);    

        if($result) {
            return $response->withJson(array('status' => 'true', 'update_count'=>$update_count, 'result'=>$result) ,200);
        } else{
            return $response->withJson(array('status' => 'No entries updated in database'),422);
        }
    }


    protected function _selectProductInv($productId) 
    {
        try{
            $con = $this->db;
            $sql = "SELECT productId, heel, size, counter 
                FROM tbl_product_inv
                where productId = '$productId'";
            
            $result = [];

            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            return($result);
        }
        catch(\Exception $ex){
            return (null);
        }
    }

    protected function _selectProductInvAll() 
    {
        try{
            $con = $this->db;
            $sql = "SELECT productId, heel, size, counter FROM tbl_product_inv";
            
            $invArr = [];

            foreach ($con->query($sql) as $row) {
                $productId = $row['productId'];
                if (isset($invArr[$productId])) {
                    $invArr[$productId]['inv'][] = $row ;
                } else {
                    $arr=array();
                    $arr[] = $row;
                    // $result[$row['productId']] = [];
                    $invArr[$productId] = ['inv'=> $arr];
                }   
            }
            return($invArr);
        }
        catch(\Exception $ex){
            return (null);
        }
    }



    protected function _select($productId) 
    {
        try{
            $con = $this->db;
            $sql = "SELECT `productId`, `image`, imageNumber, priceGroup, color, heel, size, `counter` from tbl_product_inv where productId='$productId'";
            $result = null;

            foreach ($con->query($sql) as $row) {
                $result[] = $row;
            }
            return($result);
        }
        catch(\Exception $ex){
            return (null);
        }
    }

    protected function _delete($table, $productId)
    {
        $con = $this->db;
        // Delete records from tbl_product_inv 
        try {
            $sql="DELETE FROM $table WHERE productId='$productId'";
            $sth = $con->prepare($sql);
            $sth->execute();
        } catch (PDOException $e) {
            $this->logger->addDebug('sql:' . $sql . "<br!" . $e->getMessage());
        }
    }    

    protected function _updateProductDef($product)
    {
        $table='tbl_product_def';
        $productId=$product['productId'];

        // Delete record for $productId in $table       
        $this->_delete($table, $productId);

        // Reduce to sub-set of product props
        $props=['productId', 'productType', 'priceGroup','gender', 'color', 'brandId', 'openToe', 'openHeel', 'comment'];
        $reducedObject =  $this->_objectReduceToProps($product, $props);
        $sql = $this->_build_sql_insert_or_update($table, $reducedObject);
        $this->logger->addDebug('sql:' . $sql);
        try {
            $this->_execute($sql);
        } catch (PDOException $e) {
            // Delete failure is OK case if record did not exist before
            $this->logger->addDebug('sql:' . $sql . "<br!" . $e->getMessage());
        }
        return (true);
    }    


    protected function _updateProductInv($productId, $inventoryList) 
    {
        if (count($inventoryList) > 0) {
            // Delete all records in table tbl_product_inv for productId        
            $this->_delete('tbl_product_inv', $productId);

            // Reduce to subset of inv props
            $table='tbl_product_inv';
            $props = ['productId', 'heel', 'size', 'counter'];
            foreach($inventoryList as $inv) {
                $reducedObject = $this->_objectReduceToProps($inv, $props); 
                $sql = $this->_build_sql_insert($table, $reducedObject);
                $this->logger->addDebug('sql:' . $sql);
                try {
                    $this->_execute($sql);
                } catch (PDOException $e) {
                    // Insert failure is never OK case since productIdalways is deleted first
                    $this->logger->addDebug('sql:' . $sql . "<br!" . $e->getMessage());
                    return(false);
                }
            }
        }
        return(true);
    }

    public function getProductInventory($request, $response)
    {
        $result=$this->_selectProductInvAll();
        if($result != null){
            return $response->withJson(array('status' => 'true','result'=>$result) ,200);
        } else{
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }   
    }     

    public function getProductDef($request, $response)
    {
        $result=$this->_selectProductDefAll();
        if($result != null){
            return $response->withJson(array('status' => 'true','result'=>$result) ,200);
        } else{
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }   
    }     

    public function updateProductDef($request, $response)
    {
        $input = $request->getParsedBody();
        $product=$input['product'];    
        $productId=$product['productId'];
        
        // Delete old entries for productId
        if ($this->_updateProductDef($product)) {
            $this->logger->addDebug('Table ProductDef for productId:' . $productId . ' is updated');
        } 
        // Select the updated inventory
        $result=$this->_selectProductDef($productId);
        if($result != null) {
            $this->logger->addDebug('Successful update of inventory:' . $productId);
            return $response->withJson(array('status' => 'true','result'=>$result) ,200);
        } else{
            $this->logger->addDebug('Failed to update inventory for productId:' . $productId);
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }   
    }


    public function updateProductInventory($request, $response)
    {
        $input = $request->getParsedBody();
        $productId=$input['productId'];
        $inventoryList=$input['inventoryList'];    
        
        // Delete old entries for productId
        if ($this->_updateProductInv($productId, $inventoryList)) {
            $this->logger->addDebug('Table tbl_product_inv for ProductId:' . $productId . ' is updated');
        } 
        // Select the updated inventory
        $result=$this->_selectProductInv($productId);
        if($result != null) {
            $this->logger->addDebug('Successful update of tbl_product_inv:' . $productId);
            return $response->withJson(array('status' => 'true','result'=>$result) ,200);
        } else{
            $this->logger->addDebug('Failed to update tbl_product_inv for productId:' . $productId);
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }   
    }

    public function updateProduct($request, $response)
    {
        $input = $request->getParsedBody();
        $product=$input['product'];
        $inventoryList=$input['inventoryList'];    
        $productId=$product['productId'];

        // Delete old entries for productId
        if ($this->_updateProductDef($product)) {
            $this->logger->addDebug('Table ProductDef for productId:' . $productId . ' is updated');
        } 
        // Select the updated product def
        $productNew=$this->_selectProductDef($productId);
        
        // Delete old entries for productId
        if ($this->_updateProductInv($productId, $inventoryList)) {
            $this->logger->addDebug('Table tbl_product_inv for ProductId:' . $productId . ' is updated');
        } 
        // Select the updated inventory
        $inventoryListNew=$this->_selectProductInv($productId);
        if($productNew != null) {
            $this->logger->addDebug('Successful update of tbl_product_def and tbl_product_inv for productId:' . $productId);
            return $response->withJson(array('status' => 'true','product'=>$productNew, 'inventoryList'=>$inventoryListNew) ,200);
        } else{
            $this->logger->addDebug('Failed to update tbl_product_inv for productId:' . $productId);
            return $response->withJson(array('status' => 'No entries found in database'),422);
        }   
    }
}

