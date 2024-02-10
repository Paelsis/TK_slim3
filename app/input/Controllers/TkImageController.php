<?php

namespace App\Controllers;

class TkImageController extends Controller
{
    public function getImage($request, $response) 
    {
        $allGetVars = $request->getQueryParams();
        $con = $this->db;

        return $response->withJson(array('status' => 'OK','message'=>'Get image works fine') ,200);
    }

    protected function _rotateFile($path, $file, $angle) 
    {
        $fileName = $path . '/' . trim($file, '/');    
        if (exif_imagetype ($fileName) === IMAGETYPE_PNG) {
            $source = imagecreatefrompng($fileName);
            $rotate = imagerotate($source, -$angle, 0);
            imagepng($rotate, $fileName);
            imagedestroy($source);
            $this->logger->info('_rotateFile with imagepng: Successful rotate of ' . $path . '/' . $file . ' with ' . $angle . ' degrees');
            return true;
        } else if (exif_imagetype ($fileName) === IMAGETYPE_JPEG) {
            $source = imagecreatefromjpeg($fileName);
            $rotate = imagerotate($source, -$angle, 0);
            imagejpeg($rotate, $fileName);
            imagedestroy($source);
            $this->logger->info('_rotateFile with imagejpeg: Successful rotate of ' . $path . '/' . $file . ' with ' . $angle . ' degrees');
            return true;
        } else {
            $this->logger->error('Failed to rotate ' . $fileName);

            return false;
        }
    }    
  
    protected function _removeFile($path, $file) 
    {
        $ret = false;
        $filename_orig = $path . '/' . $file;

        if (file_exists($filename_orig)) {
            // return true;
            if (!unlink($filename_orig)) {
                $this->logger->info('File ' . $filename_orig . ' was found on disk but could not be deleted');
                return false;
            }
            $this->logger->warning('File ' . $filename_orig . ' not found on disk and cannot be deleted');
        }    

        // Remove thumbnail if existent
        $filename_thumb = $path . '/' . 
            pathinfo($file, PATHINFO_FILENAME) . '_thumb' . '.' .  pathinfo($file, PATHINFO_EXTENSION);
        if (file_exists($filename_thumb)) {
            if (!unlink($filename_thumb)) {
                $this->logger->warning('File ' . $filename_thumb . ' was found on disk but could not be deleted');
                return false;
            } 
        }
        return true;
    }

    public function getRemoveFile($request, $response) 
    {
        $payload = $request->getQueryParams();
        if (($path=$this->_imagePath($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'Path not found') , 500);
        }
        if (isset($allGetVars['file'])) {
            $file = $allGetVars['file'];
        } else {   
            return $response->withJson(array('status' => 'ERROR','message'=>'file not found') , 400);
        }

        if ($this->_removeFile($path, $file)) {
            return $response->withJson(array('status' => 'OK','message'=>'File ' . $file . ' was successfully deleted') ,200);
        } else {
            return $response->withJson(array('status' => 'WARNING','message'=>'No files were deleted') ,304);
        }
    }

    public function rotateFiles($request, $response) 
    {
        $payload = $request->getParsedBody();
        if (($path=$this->_imagePath($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'Path not found') , 500);
        }
        if (isset($payload['files'])) {
            $files = $payload['files'];
        } else {   
            return $response->withJson(array('status' => 'ERROR','message'=>'files not found in payload') , 500);
        }

        $numberOfRotatedFiles = 1;
        foreach ($files as $file) {
            if (!isset($file['name'])) {
                return $response->withJson(array('status' => 'ERROR','message'=>'file[name] not found') , 500);
            }
            if (!isset($file['rotate'])) {
                return $response->withJson(array('status' => 'ERROR','message'=>'file[rotate] not found') , 500);
            }
            if ($this->_rotateFile($path, $file['name'], $file['rotate'])) {
                $numberOfRotatedFiles++;
            } else {
                return $response->withJson(array('status' => 'ERROR','message'=>'Failed to rotate file ' . $path) , 500);
            };
        }
        if ($numberOfRotatedFiles > 0) {
            return $response->withJson(array('status' => 'OK','message'=>$numberOfRotatedFiles . 'files where rotated') ,200);
        } else {
            return $response->withJson(array('status' => 'WARNING','message'=>'No files were rotated') ,500);
        }
    }

    protected function _removeFileAndThumb($subdir, $file) {
        $cnt=0;
        if (isset($file['delete'])) {
            if (isset($file['fname'])) {
                if ($this->_removeFile($subdir, $file['fname'])) {
                    $cnt++;
                };
            }    
            if (isset($file['thumbFname'])) {
                if ($this->_removeFile($subdir, $file['thumbFname'])) {
                    $cnt++;
                };
            }    
        }    
        return($cnt);
    }    

    protected function _rotateFileAndThumb($subdir, $file) {
        $cnt=0;
        if (isset($file['rotate'])) {
            if (isset($file['fname'])) {
                if ($this->_rotateFile($subdir, $file['fname'], $file['rotate'])) {
                    $cnt++;
                };
            }    
            if (isset($file['thumbFname'])) {
                if ($this->_rotateFile($subdir, $file['thumbFname'], $file['rotate'])) {
                    $cnt++;
                };
            }    
        }    
        return $cnt;
    }    

    public function removeOrRotateImages($request, $response) 
    {
        $payload = $request->getParsedBody();
        if (($path=$this->_imagePath($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'Path not found') , 500);
        }

        if (isset($payload['files'])) {
            $files = $payload['files'];
        } else {   
            return $response->withJson(array('status' => 'ERROR','message'=>'files not found in payload') , 400);
        }

        $numberOfDeletedFiles = 0;
        $numberOfRotatedFiles = 0;
        foreach ($files as $file) {
            $numberOfDeletedFiles += $this->_removeFileAndThumb($path, $file);
            $numberOfRotatedFiles += $this->_rotateFileAndThumb($path, $file);
        }

        $result = $this->_listImagesData($path);

        if (($numberOfDeletedFiles + $numberOfRotatedFiles) > 0) {
            return $response->withJson(
                array('status' => 'OK',
                    'result'=>$result, 
                    'deleted'=>'Number of deleted:' . $numberOfDeletedFiles,
                    'rotated'=>'Number of rotated:' . $numberOfRotatedFiles), 
            200);
        } else {
            return $response->withJson(
                array('status' => 'WARNING',
                'result'=>$result,
                'deleted'=>'Number of deleted:' . $numberOfDeletedFiles,
                'rotated'=>'Number of rotated:' . $numberOfRotatedFiles, 
                'message'=>'No files were deleted or rotated') ,200);
        }
    }


    protected function _imagePath($payload) {
        $path = SLIM_PUBLIC_IMAGES_PATH;
        if (isset($payload['rootdir'])) {
            if (($payload['rootdir'] !== 'undefined') && ($payload['rootdir'] !== '.') && ($payload['rootdir'] !== null)) {
                $rootdir = $payload['rootdir'];
                $path .= '/' . trim($rootdir, '/');
                if (!is_dir($path)) {
                    $this->logger->error('_imagePath: Directory ' . $path . ' does not exist' . ' rootdir=' . $rootdir);
                    return(null);
                }    
            }    
        } else {   
            $this->logger->warning('_imagePath: No rootdir');
        }
        if (isset($payload['subdir'])) {
            if (($payload['rootdir'] !== 'undefined') && ($payload['rootdir'] !== '.') && ($payload['rootdir'] !== 'null')) {
                $subdir = $payload['subdir'];
                $path .= '/' . trim($subdir, '/');
                if (!is_dir($path)) {
                    $this->logger->error('_imagePath: Directory ' . $path . ' does not exist' . ' rootdir=' . $rootdir . ' subdir=' . $subdir);
                    return(null);
                }    
            }
        } 
        $this->logger->info('_imagePath = ' . $path);
        return($path);
    }

    protected function _createDirectory($path) 
    {
        $this->logger->addDebug('_createNewDirectory, path ' . $path);
        $ret = false;
        if (!is_dir($path)) {
            // return true;
            if (mkdir($path, 0755)) {
                $ret = true;
            } else {
                $this->logger->warning('Could not create directory ' . $path);
            }
        } else {
            $this->logger->warning('Dir ' . $path . ' already exists on disk');
        }
        return $ret;
    }


    public function createDirectory($request, $response) 
    {
        $payload = $request->getParsedBody();
        if (($path=$this->_imagePath($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'rootdir not found in payload') , 500);
        }
        if (isset($payload['folder'])) {
            $folder = $payload['folder'];
            $path .=  '/' . trim($folder, '/');
        } else {   
            return $response->withJson(array('status' => 'ERROR','message'=>'dirname not found in payload') , 500);
        }
   
        if ($this->_createDirectory($path)) {
            $this->logger->info('Dir ' . $path . ' has been created');
            $message = 'Directory with name ' . $path . ' created';
            return $response->withJson(
                array(
                    'status' => 'OK', 
                    'result'=>$path,
                    'message'=>'The directory was created'),
            200);
        } else {
            return $response->withJson(
                array('status' => 'ERROR', 
                'message'=>'Failed to create subdirectory with name ' . $folder), 
            500);
        }
    }

    public static function _deleteDirectory($dirPath) {
        if (! is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::_deleteDirectory($file);
            } else {
                unlink($file);
            }
        }
        if (rmdir($dirPath)) {
            return true;
        } else {
            return false;
        }   
    }

    public static function _moveDir($orig, $dest) {
        if (!is_dir($orig)) {
            $this->logger->error('Directory ' . $orig . ' is not a directory');
            return false;
        }
        if (rename($orig, $dest)) {
            return true;
        } else {
            $this->logger->error('Failed to rename ' . $orig . ' to ' . $dest);
            return false;
        }   
    }

    public function deleteDirectory($request, $response) 
    {
        $payload = $request->getParsedBody();
        if (($path=$this->_imagePath($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'rootdir not found in payload') , 500);
        }
        if  (!isset($payload['dir'])) {
            return $response->withJson(array('status' => 'ERROR','message'=>'Failed to delete ' . $path . '/' . $dir), 
            500);
        }
        $path .= '/' . $payload['dir'];
        
        $this->logger->addDebug('Deleting directory ' . $path);
        if (is_dir($path) === false) {
            $this->logger->addDebug('The path ' . $path . ' is not a directory');
            return $response->withJson(array('status' => 'ERROR','message'=>'Path ' . $path . ' is not a directory') , 200);
        }
        $this->logger->addDebug('before date');

        $date = date("now");
        $suffix = $date;

        $orig = $path;
        $base = basename($orig);
        $dest = SLIM_PUBLIC_IMAGES_PATH . '/' . '__trashcan/' . $base . '.' . $suffix;
        $this->logger->addDebug('after date');

        if ($this->_deleteDirectory($path)) {
            $this->logger->addDebug('Successful to move ' . $orig . ' to ' . $dest);
            return $response->withJson(
                array('status' => 'OK',
                    'message'=>'Directory ' . $orig . ' moved', 
                    'result'=>$dest),
                200);
        } else {
            $this->logger->addDebug('Failed to move ' . $orig . ' to ' . $dest);
            return $response->withJson(
                array('status' => 'ERROR',
                'message'=>'Failed move ' . $path . ' to ' . $dest), 
            500);
        }
    }

    protected function returnFunction($dirname, $basename) {
        $result='No result';
        if ($this->_deleteDirectory($dirname, $basename)) {
            return $response->withJson(
                array('status' => 'OK',
                    'message'=>'Directory ' . $dirname . $basename . ' and all its contents deleted', 
                    'result'=>$result),
                200);
        } else {
            return $response->withJson(
                array('status' => 'ERROR',
                'message'=>'Failed to delete ' . $dirname . '/' . $basename), 
            500);
        }
    }        

    public function listImages($request, $response) 
    {
        $payload = $request->getQueryParams();
        if (($path=$this->_imagePath($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'rootdir not found in payload') , 500);
        }

        $handle = opendir($path);
        $result = array();
        if ($handle) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && $this->_isImage($entry) && !$this->_isThumbnail($entry))  {
                    $result[] = $entry;
                }
            }
            closedir($handle);
        }
        usort($result, function($a, $b) {
            return $a > $b;
        });
        return $response->withJson(array('status' => 'OK','result'=>$result) ,200);
    }

    protected function _listImagesData($path)
    {
        $handle = opendir($path);
        $result = array();
        if ($handle) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && $this->_isImage($entry) && !$this->_isThumbnail($entry))  {
                    if (false == ($mtime=filemtime($path . '/' . $entry))) {
                        return $response->withJson(array('status' => 'ERROR', 'message' => 'No such mtime for ' . $path . $entry, 'result'=>$result) ,200);
                    }
                    if (false == ($mdate=date("y-m-d H:i:s",$mtime))) {
                        return $response->withJson(array('status' => 'ERROR', 'message' => 'No such mdate for mtime = ' . $mtime, 'result'=>$result) ,200);
                    }
                    if (false == ($yyddmm=date("y-m-d",$mtime))) {
                        return $response->withJson(array('status' => 'ERROR', 'message' => 'No such yymmdd for mtime = ' . $mtime, 'result'=>$result) ,200);
                    }
                    if (false == ($fsize=$this->_getFileSize($path . '/' . $entry))) {
                        return $response->withJson(array('status' => 'ERROR', 'message' => 'No filssizemdate for = ', $path . $entry, 'result'=>$result) ,200);
                    }
                    
                    /*
                    $exif_data = exif_read_data ($entry, 'ANY_TAG');
                    if (empty($exif_data['DateTimeOriginal'])) {
                        $dateTimeOriginal = null;    
                    } else {
                        $dateTimeOriginal = $exif_data['DateTimeOriginal'];
                    }
                    */

                    $filename = pathinfo($entry, PATHINFO_FILENAME);
                    $ext = pathinfo($entry, PATHINFO_EXTENSION);
                    $thumbFname = $filename . '_' . 'thumb' . '.' . $ext;
                    $image=array('fname'=>$entry, 'mdate'=>$mdate, 'yyddmm'=>$yyddmm, 'mtime'=>$mtime, 'fsize'=>$fsize, 'DateTimeOriginal'=>'undefined');
                    if (file_exists($path . '/' . $thumbFname)) {
                        $image += array('thumbFname'=>$thumbFname); 
                    }
                    $result[] = $image;
                }
            }
            closedir($handle);
        }
        usort($result, function($a, $b) {
            return $a['mtime'] > $b['mtime'];
        });
        return $result;
    }


    public function listImagesData($request, $response) 
    {
        $payload = $request->getQueryParams();
        if (($path=$this->_imagePath($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'Path not found') , 500);
        }

        if (!is_dir($path)) {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Path not found', 'path'=>$path, 'result'=>array()) ,500);
        }
        $result = $this->_listImagesData($path);
        return $response->withJson(array('status' => 'OK','result'=>$result) ,200);
    }



    public function postImages($request, $response) 
    {
        $files = $request->getUploadedFiles();
        $payload=$request->getParsedBody();
        if (($path=$this->_imagePath($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'rootdir not found in payload') , 500);
        }
        $this->log_var('UploadedFiles:', $files);
        $this->log_var('Parsed Body:', $request->getParsedBody());
        if (!isset($files['newfile_arr'])) {
            return $response->withJson(array('status' => 'ERROR','message'=>'The subdir was not set i form') ,502);
        }   
        $cnt=0;
        $okFiles = array();
        foreach($files['newfile_arr'] as $newfile) {
            if (isset($newfile)) {
                if (empty($newfile)) {
                    return $response->withJson(array('status' => 'ERROR','message'=>'The newfile is empty') ,501);
                }
            } else {
                return $response->withJson(array('status' => 'ERROR','message'=>'The newfile is undefined') ,502);
            }   
            $this->log_var('newfile', $newfile);
            $this->log_var('newfile->getError()', $newfile->getError());
            if ($newfile->getError() === UPLOAD_ERR_OK) {
                // $subdir = rtrim($subdir, '/');

                $uploadFileName = $newfile->getClientFilename();
                $uploadPath = $path . '/' . $uploadFileName;
                $this->logger->addDebug('Moving file ' . $uploadFileName . ' to ' . ' path ' . $path);
    
                $newfile->moveTo($uploadPath);
                $okFiles[] = $uploadFileName;
                $this->_createThumbnailFile($path, $uploadFileName);
            } else {
                return $response->withJson(array('status' => 'ERROR','message'=>$newfile['error']) ,503);
            } 
            $cnt++;        

        }    
        return $response->withJson(array('status' => 'OK', 'count'=>$cnt, 'path'=>$path, 'message'=>'Image/s upladed') ,200);
    }

    public function postImage($request, $response) 
    {
        $files = $request->getUploadedFiles();
        $payload = $request->getParsedBody();
        if (($path=$this->_imagePath($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'rootdir not found in payload') , 500);
        }
        if (!isset($files['newfile'])) {
            return $response->withJson(array('status' => 'ERROR','message'=>'The files[newfie] is undefined') ,501);
        }   

        if (empty($files['newfile'])) {
            return $response->withJson(array('status' => 'ERROR','message'=>'The files[newfile] is empty') ,502);
        }

        $newfile = $files['newfile'];
        if ($newfile->getError() === UPLOAD_ERR_OK) {
            // $subdir = rtrim($subdir, '/');
            $uploadFileName = $newfile->getClientFilename();
            $uploadPath = $path . '/' . $uploadFileName;
            $this->logger->addDebug('Moving file ' . $uploadFileName . ' to ' . ' dir ' . $uploadPath);

            $newfile->moveTo($uploadPath);
            $this->_createThumbnailFile($path, $uploadFileName);
            return $response->withJson(array('status' => 'OK', 'newfile'=>$newfile, 'path'=>$subdir, 'uploadFileName'=>$uploadFileName, 'message'=>'Image uploaded') ,200);
        } else {
            return $response->withJson(array('status' => 'ERROR','message'=>'The file could not be moved') ,503);
        }    
    }

    public function createThumbnails($request, $response) 
    {
        $payload = $request->getQueryParams();
        if (($path=$this->_imagePath($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'rootdir not found in payload') , 500);
        }
        ob_start(); //Start output buffer
        $result = $this->_createThumbnailsForDir($path);
        $outOfBoxInfo = ob_get_contents(); //Grab output
        ob_end_clean(); //Discard output buffer

        if ($result===null) {
            return $response->withJson(array('status' => 'ERROR', 'message'=>'Subdirectory is not found', 'result'=>'No result') ,200);
        } else if (count($result) === 0) {   
            return $response->withJson(array('status' => 'ERROR', 'message'=>'No new thumbnails created', 'result'=>array()) ,200);
        } else {
            return $response->withJson(array('status' => 'OK', 'result'=>'Succesful creation of thumbs') ,200);
        }   
    }     

    public function listThumbnails($request, $response) 
    {
        $payload = $request->getQueryParams();
        if (($path=($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'rootdir not found in payload') , 500);
        }

        $handle = opendir($path);
        $result = array();
        if ($handle) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && $this->_isThumbnail($entry)) {
                    $result[] = $entry;
                }
            }
            closedir($handle);
        }
        return $response->withJson(array('status' => 'OK', 'result'=>$result) ,200);
    }

    protected function scanAllDir($dir) {
        $result = [];
        foreach(scandir($dir) as $filename) {
          if ($filename[0] === '.') continue;
          $filePath = $dir . '/' . $filename;
          if (is_dir($filePath)) {
            foreach ($this->scanAllDir($filePath) as $childFilename) {
              $result[] = $filename . '/' . $childFilename;
            }
          } else {
            $result[] = $filename;
          }
        }
        return $result;
    }

    protected function _listAllSubdirsRecursively($path) {
        $result = [];

        foreach(glob($path . '/*' , GLOB_ONLYDIR) as $value) {
            $entry = str_replace($path, '', $value);
            $result[] = trim($entry, '/');
            $result = array_merge($result, $this->_listAllSubdirs($value));
        }
        return $result;
    }

    protected function _listAllSubdirs($path) {
        $result = [];
        foreach(glob($path . '/*' , GLOB_ONLYDIR) as $value) {
            $entry = str_replace($path, '', $value);
            $result[] = trim($entry, '/');
        }
        return $result;
    }


    public function listDirs($request, $response) 
    {
        $payload = $request->getQueryParams();
        if (($path=$this->_imagePath($payload))===null) {
            return $response->withJson(array('status' => 'ERROR','message'=>'rootdir not found in payload') , 500);
        }
        $arr = $this->_listAllSubdirs($path);
        $result = array();
        foreach($arr as $val) {
            $result[] =  str_replace('//', '/', $val);
        }
        return $response->withJson(array('status' => 'OK', 'result'=>$result) ,200);
    }    
} 
