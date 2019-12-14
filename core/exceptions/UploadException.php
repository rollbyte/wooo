<?php

namespace wooo\core\exceptions;

class UploadException extends \Exception
{
    private static $messages = [
        UPLOAD_ERR_INI_SIZE => 'File is too large.',
        UPLOAD_ERR_FORM_SIZE => 'File is too large.',
        UPLOAD_ERR_PARTIAL => 'File partial upload failed.',
        UPLOAD_ERR_NO_FILE => 'No file uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'TMP path not set.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'PHP-extension stopped file upload.'
    ];
    
    public function __construct($code)
    {
        parent::__construct(self::$messages[$code] ?? 'Unknown upload error', $code);
    }
}
