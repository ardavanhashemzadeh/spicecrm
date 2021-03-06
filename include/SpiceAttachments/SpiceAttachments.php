<?php

use Slim\Http\UploadedFile;

class SpiceAttachments
{

    public static function getAttachmentsForBean($beanName, $beanId, $lastN = 10)
    {
        global $current_user, $db, $beanFiles, $beanList;
        $attachments = array();

        if ($GLOBALS['db']->dbType == 'mssql') {
            $attachmentsRes = $db->query("SELECT TOP $lastN qn.*,u.user_name FROM spiceattachments AS qn LEFT JOIN users AS u ON u.id=qn.user_id WHERE qn.bean_id='{$beanId}' AND qn.bean_type='{$beanName}' AND qn.deleted = 0 ORDER BY qn.trdate DESC");
        } else {
            $attachmentsRes = $db->limitQuery("SELECT qn.*,u.user_name FROM spiceattachments AS qn LEFT JOIN users AS u ON u.id=qn.user_id WHERE qn.bean_id='{$beanId}' AND qn.bean_type='{$beanName}' AND qn.deleted = 0 ORDER BY qn.trdate DESC", 0, $lastN);
        }

        if ($GLOBALS['db']->dbType == 'mssql' || $db->getRowCount($attachmentsRes) > 0) {
            while ($thisAttachment = $db->fetchByAssoc($attachmentsRes)) {


                $attachments[] = array(
                    'id' => $thisAttachment['id'],
                    'user_id' => $thisAttachment['user_id'],
                    'user_name' => $thisAttachment['user_name'],
                    'date' => $GLOBALS['timedate']->to_display_date_time($thisAttachment['trdate']),
                    'text' => nl2br($thisAttachment['text']),
                    'filename' => $thisAttachment['filename'],
                    'filesize' => $thisAttachment['filesize'],
                    'file_mime_type' => $thisAttachment['file_mime_type'],
                    'url' => "index.php?module=SpiceThemeController&action=attachment_download&id=" . $thisAttachment['id'],
                    'thumbnail' => $thisAttachment['thumbnail']
                );
            }
        }

        return json_encode($attachments);
    }

    public static function getAttachmentsForBeanHashFiles($beanName, $beanId, $lastN = 10)
    {
        global $current_user, $db, $beanFiles, $beanList;
        $attachments = array();

        if ($GLOBALS['db']->dbType == 'mssql') {
            $attachmentsRes = $db->query("SELECT TOP $lastN qn.*,u.user_name FROM spiceattachments AS qn LEFT JOIN users AS u ON u.id=qn.user_id WHERE qn.bean_id='{$beanId}' AND qn.bean_type='{$beanName}' AND qn.deleted = 0 ORDER BY qn.trdate DESC");
        } else {
            $attachmentsRes = $db->limitQuery("SELECT qn.*,u.user_name FROM spiceattachments AS qn LEFT JOIN users AS u ON u.id=qn.user_id WHERE qn.bean_id='{$beanId}' AND qn.bean_type='{$beanName}' AND qn.deleted = 0 ORDER BY qn.trdate DESC", 0, $lastN);
        }

        if ($GLOBALS['db']->dbType == 'mssql' || $db->getRowCount($attachmentsRes) > 0) {
            while ($thisAttachment = $db->fetchByAssoc($attachmentsRes)) {
                $file = base64_encode(file_get_contents("upload://" . $thisAttachment['id']));
                $attachments[] = array(
                    'id' => $thisAttachment['id'],
                    'user_id' => $thisAttachment['user_id'],
                    'user_name' => $thisAttachment['user_name'],
                    'date' => $GLOBALS['timedate']->to_display_date_time($thisAttachment['trdate']),
                    'text' => nl2br($thisAttachment['text']),
                    'filename' => $thisAttachment['filename'],
                    'filesize' => $thisAttachment['filesize'],
                    'file_mime_type' => $thisAttachment['file_mime_type'],
                    'file' => $file
                );
            }
        }

        return json_encode($attachments);
    }

    public static function getAttachmentsCount($lastN = 10)
    {
        global $current_user, $db;
        $attachmentsRec = $db->fetchByAssoc($db->query("SELECT count(*) AS noteCount FROM spiceattachments WHERE bean_id='{$_REQUEST['record']}' AND bean_type='{$_REQUEST['module']}'  AND deleted = 0"));

        return $attachmentsRec['noteCount'];
    }

    public static function saveAttachment($beanName, $beanId, $post)
    {
        global $current_user, $db;
        $guid = create_guid();

        require_once('include/upload_file.php');
        $upload_file = new UploadFile('file');
        if (isset($_FILES['file']) && $upload_file->confirm_upload()) {
            $filename = $upload_file->get_stored_file_name();
            $file_mime_type = $upload_file->mime_type;
            $filesize = $upload_file->get_uploaded_file_size();
            $filemd5 = $upload_file->get_uploaded_file_md5();
            $upload_file->use_proxy = $_FILES['file']['proxy'] ? true : false;
            $upload_file->final_move($filemd5, false);
        }

        // if we have an image create a thumbnail
        $thumbnail = self::createThumbnail($filemd5, $file_mime_type);

        $db->query("INSERT INTO spiceattachments (id, bean_type, bean_id, user_id, trdate, filename, filesize, filemd5, text, thumbnail, deleted, file_mime_type) VALUES ('{$guid}', '{$beanName}', '{$beanId}', '" . $current_user->id . "', '" . gmdate('Y-m-d H:i:s') . "', '{$filename}', '{$filesize}', '{$filemd5}', '{$_POST['text']}', '{$thumbnail}', 0, '{$file_mime_type}')");
        $attachments[] = array(
            'id' => $guid,
            'user_id' => $current_user->id,
            'user_name' => $current_user->user_name,
            'date' => $GLOBALS['timedate']->to_display_date_time(gmdate('Y-m-d H:i:s')),
            'text' => nl2br($_POST['text']),
            'filename' => $filename,
            'filesize' => $filesize,
            'file_mime_type' => $file_mime_type,
            'thumbnail' => $thumbnail,
            'url' => "index.php?module=SpiceThemeController&action=attachment_download&id=" . $guid
        );
        return json_encode($attachments);
    }

    public static function saveAttachmentHashFiles($beanName, $beanId, $post)
    {
        global $current_user, $db, $sugar_config;
        $guid = create_guid();

        require_once('include/upload_file.php');
        $upload_file = new UploadFile('file');

        $decodedFile = base64_decode($post['file']);
        $upload_file->set_for_soap($post['filename'], $decodedFile);


        $ext_pos = strrpos($upload_file->stored_file_name, ".");
        $upload_file->file_ext = substr($upload_file->stored_file_name, $ext_pos + 1);
        if (in_array($upload_file->file_ext, $sugar_config['upload_badext'])) {
            $upload_file->stored_file_name .= ".txt";
            $upload_file->file_ext = "txt";
        }

        $filename = $upload_file->get_stored_file_name();
        $file_mime_type = $post['filemimetype'] ?: $upload_file->getMimeSoap($filename);
        $filesize = strlen($decodedFile);
        $filemd5 = md5($decodedFile);

        $upload_file->final_move($guid);

        // if we have an image create a thumbnail
        $thumbnail = self::createThumbnail($guid, $file_mime_type);

        $db->query("INSERT INTO spiceattachments (id, bean_type, bean_id, user_id, trdate, filename, filesize, filemd5, text, thumbnail, deleted, file_mime_type) VALUES ('{$guid}', '{$beanName}', '{$beanId}', '" . $current_user->id . "', '" . gmdate('Y-m-d H:i:s') . "', '{$filename}', '{$filesize}', '{$filemd5}', '{$post['text']}', '$thumbnail', 0, '{$file_mime_type}')");
        $file = base64_encode(file_get_contents("upload://" . $guid));

        $attachments[] = array(
            'id' => $guid,
            'user_id' => $current_user->id,
            'user_name' => $current_user->user_name,
            'date' => $GLOBALS['timedate']->to_display_date_time(gmdate('Y-m-d H:i:s')),
            'text' => nl2br($post['text']),
            'filename' => $filename,
            'filesize' => $filesize,
            'file_mime_type' => $file_mime_type,
            'file' => $file,
            'thumbnail' => $thumbnail,
        );
        return json_encode($attachments);
    }

    public static function saveEmailAttachment($beanName, $beanId, $payload)
    {
        global $current_user, $db;
        $guid = create_guid();

        // if we have an image create a thumbnail
        $thumbnail = self::createThumbnail($payload->filemd5, $payload->mime_type);

        $db->query("INSERT INTO spiceattachments (id, bean_type, bean_id, user_id, trdate, filename, filesize, filemd5, text, thumbnail, deleted, file_mime_type) 
                        VALUES ('{$guid}', '{$beanName}', '{$beanId}', '" . $current_user->id . "', '" . gmdate('Y-m-d H:i:s') . "', 
                        '{$payload->filename}', '{$payload->filesize}', '{$payload->filemd5}', '{$_POST['text']}', '{$thumbnail}', 0, '{$payload->mime_type}')");
        $attachments[] = array(
            'id' => $guid,
            'user_id' => $current_user->id,
            'user_name' => $current_user->user_name,
            'date' => $GLOBALS['timedate']->to_display_date_time(gmdate('Y-m-d H:i:s')),
            'text' => nl2br($_POST['text']),
            'filename' => $payload->filename,
            'filesize' => $payload->filesize,
            'file_mime_type' => $payload->file_mime_type,
            'thumbnail' => $thumbnail,
            'url' => "index.php?module=SpiceThemeController&action=attachment_download&id=" . $guid
        );
        return json_encode($attachments);
    }

    public static function saveRestAttachment($beanName, $beanId, UploadedFile $uploaded_file)
    {
        global $current_user, $db;
        $guid = create_guid();
        $directory = '';
        $supportedimagetypes = ['jpeg', 'png', 'gif', 'bmp'];

        if ($uploaded_file->getError() === UPLOAD_ERR_OK) {
            $filename = self::moveUploadedFile($directory, $uploaded_file);
            die(var_dump($uploaded_file));
            if (file_exists($uploaded_file->file)) {
                die('file ' . $uploaded_file->file . ' exists');
            } else {
                die('file ' . $uploaded_file->file . ' doesnt exist');
            }
            $filemd5  = md5_file($uploaded_file->file);

            // todo generate thumbnail
            $filetypearray = explode('/', $uploaded_file->getClientMediaType());
            if (count($filetypearray) == 2 && $filetypearray[0] == 'image' && array_search($filetypearray[1], $supportedimagetypes) >= 0) {



                if (list($width, $height) = getimagesize($uploaded_file->file)) {
                    if ($width > $height) {
                        $newwidth = 30;
                        $newheight = round(30 * $height / $width);
                    } else {
                        $newwidth = round(30 * $width / $height);
                        $newheight = 30;
                    }

                    $thumb = imagecreatetruecolor($newwidth, $newheight);

                    // create
                    $createfunction = 'imagecreatefrom' . $filetypearray[1];
                    $source = $createfunction($uploaded_file->file);

                    imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
                    ob_start();
                    imagejpeg($thumb);
                    $thumbnail = base64_encode(ob_get_contents());
                    ob_end_clean();
                    imagedestroy($thumb);
                }



            }
        }

        /*require_once('include/upload_file.php');
        $upload_file = new UploadFile('file');
        if (isset($_FILES['file']) && $upload_file->confirm_upload()) {
            $filename = $upload_file->get_stored_file_name();
            $file_mime_type = $upload_file->mime_type;
            $filesize = $upload_file->get_uploaded_file_size();
            $filemd5 = $upload_file->get_uploaded_file_md5();
            $upload_file->use_proxy = $_FILES['file']['proxy'] ? true : false;
            $upload_file->final_move($filemd5, false);
        }*/

        $db->query(
            "INSERT INTO spiceattachments (
                  id, 
                  bean_type, 
                  bean_id, 
                  user_id, 
                  trdate, 
                  filename, 
                  filesize, 
                  filemd5, 
                  thumbnail, 
                  deleted, 
                  file_mime_type
                  ) VALUES (
                  '{$guid}', 
                  '{$beanName}', 
                  '{$beanId}', 
                  '" . $current_user->id . "', 
                  '" . gmdate('Y-m-d H:i:s') . "', 
                  '" . $uploaded_file->getClientFilename() . "', 
                  '" . $uploaded_file->getSize() . "', 
                  '{$filemd5}', 
                  '{$thumbnail}', 
                  0, 
                  '" . $uploaded_file->getClientMediaType() . "'
                )"
        );
        $attachments[] = array(
            'id' => $guid,
            'user_id' => $current_user->id,
            'user_name' => $current_user->user_name,
            'date' => $GLOBALS['timedate']->to_display_date_time(gmdate('Y-m-d H:i:s')),
            //'text' => nl2br($_POST['text']),
            'filename' => $uploaded_file->getClientFilename(),
            'filesize' => $uploaded_file->getSize(),
            'file_mime_type' => $uploaded_file->getClientMediaType(),
            'thumbnail' => $thumbnail,
            'url' => "index.php?module=SpiceThemeController&action=attachment_download&id=" . $guid
        );
        return json_encode($attachments);
    }

    public static function deleteAttachment($attachmentId)
    {
        global $current_user, $db;
        $db->query("UPDATE spiceattachments SET deleted = 1 WHERE id='{$attachmentId}'" . (!$current_user->is_admin ? " AND user_id='" . $current_user->id . "'" : ""));

        // todo: delete also file if MD5 is no longer used anywhere
    }

    public static function getAttachment($attachmentId)
    {
        global $current_user, $db, $beanFiles, $beanList;
        $attachment = array();

        $attachmentsRes = $db->query("SELECT * FROM spiceattachments WHERE id = '$attachmentId'");

        while ($thisAttachment = $db->fetchByAssoc($attachmentsRes)) {
            $file = base64_encode(file_get_contents("upload://" . ($thisAttachment['filemd5'] ?: $thisAttachment['id'])));
            $attachment = array(
                'id' => $thisAttachment['id'],
                'user_id' => $thisAttachment['user_id'],
                'user_name' => $thisAttachment['user_name'],
                'date' => $GLOBALS['timedate']->to_display_date_time($thisAttachment['trdate']),
                'text' => nl2br($thisAttachment['text']),
                'filename' => $thisAttachment['filename'],
                'filesize' => $thisAttachment['filesize'],
                'file_mime_type' => $thisAttachment['file_mime_type'],
                'file' => $file
            );
        }

        return json_encode($attachment);
    }

    public static function downloadAttachment($attachmentId)
    {
        global $db;

        $query = "SELECT filename, file_mime_type, filemd5, filesize FROM spiceattachments ";
        $query .= "WHERE id= '" . $db->quote($attachmentId) . "'";
        $rs = $GLOBALS['db']->query($query);
        $row = $GLOBALS['db']->fetchByAssoc($rs);
        $download_location = "upload://" . ($row['filemd5'] ?: $attachmentId);
        $name = $row['name'];
        $mime_type = $row['file_mime_type'];

        // make sure to clean the buffer
        while (ob_get_level() && @ob_end_clean()) ;

        header("Pragma: public");
        header("Cache-Control: maxage=1, post-check=0, pre-check=0");
        header('Content-type: ' . $row['file_mime_type']);
        header("Content-Disposition: attachment; filename=\"".$row['filename']."\";");
        header("X-Content-Type-Options: nosniff");
        header("Content-Length: " . filesize($download_location));
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 2592000));
        readfile($download_location);
    }

    /**
     * Moves the uploaded file to the upload directory and assigns it a unique name
     * to avoid overwriting an existing uploaded file.
     *
     * @param string $directory directory to which the file is moved
     * @param UploadedFile $uploaded file uploaded file to move
     * @return string filename of moved file
     */
    public static function moveUploadedFile($directory, UploadedFile $uploadedFile)
    {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }

    private static function createThumbnail($filename, $mime_type)
    {
        $supportedimagetypes = ['jpeg', 'png', 'gif', 'bmp'];
        $filetypearray = explode('/', $mime_type);
        if (count($filetypearray) == 2 && strtolower($filetypearray[0]) == 'image' && array_search(strtolower($filetypearray[1]), $supportedimagetypes) >= 0) {
            if (list($width, $height) = getimagesize("upload://" . $filename)) {
                if ($width > $height) {
                    $newwidth = 30;
                    $newheight = round(30 * $height / $width);
                } else {
                    $newwidth = round(30 * $width / $height);
                    $newheight = 30;
                }

                $thumb = imagecreatetruecolor($newwidth, $newheight);

                // create
                $createfunction = 'imagecreatefrom' . strtolower($filetypearray[1]);
                $source = $createfunction("upload://" . $filename);

                imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
                ob_start();
                imagejpeg($thumb);
                $thumbnail = base64_encode(ob_get_contents());
                ob_end_clean();
                imagedestroy($thumb);

                return $thumbnail;
            }
        }

        return '';
    }
}