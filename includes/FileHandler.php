<?php
// includes/FileHandler.php

class FileHandler {
    private $uploadDir;
    private $pdo;
    private $maxFileSize = 5242880; // 5MB default
    private $debug = true;  // Set to true to enable debugging

    public function __construct($pdo, $uploadDir = 'uploads/') {
        $this->pdo = $pdo;
        $this->uploadDir = $uploadDir;
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                error_log("Failed to create upload directory: " . $this->uploadDir);
            }
        }
    }

    public function handleFileUpload($file) {
        // Debug information
        if ($this->debug) {
            error_log("File upload attempt: " . json_encode($file));
        }
        
        // Check if file was uploaded
        if (!isset($file) || !is_array($file) || empty($file['name'])) {
            if ($this->debug) error_log("No file was uploaded or file array is invalid");
            return null;
        }
        
        // Check for file upload errors and provide clear messages
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = $this->getUploadErrorMessage($file['error']);
            if ($this->debug) error_log("File upload error: " . $errorMessage);
            throw new Exception($errorMessage);
        }

        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            $errorMessage = 'File is too large. Maximum size is ' . ($this->maxFileSize / 1024 / 1024) . 'MB';
            if ($this->debug) error_log($errorMessage);
            throw new Exception($errorMessage);
        }
        
        // Check if file was actually uploaded via HTTP POST
        if (!is_uploaded_file($file['tmp_name'])) {
            if ($this->debug) error_log("File was not uploaded via HTTP POST");
            throw new Exception('Security error: File was not uploaded properly');
        }

        // Get file info
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($file['tmp_name']);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($this->debug) {
            error_log("File MIME type: " . $mimeType);
            error_log("File extension: " . $extension);
        }

        // Validate file type - first check if we have allowed types defined
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM allowed_file_types");
            $stmt->execute();
            $typeCount = $stmt->fetchColumn();
            
            if ($typeCount > 0) {
                // Check against allowed types
                $stmt = $this->pdo->prepare("SELECT * FROM allowed_file_types WHERE mime_type = ? OR extension = ?");
                $stmt->execute([$mimeType, $extension]);
                if (!$stmt->fetch()) {
                    if ($this->debug) error_log("File type not allowed: $mimeType, $extension");
                    throw new Exception("File type not allowed. Supported types: Images, Videos, Audio, Documents");
                }
            } else {
                // If no allowed types defined, use a basic whitelist
                $allowedTypes = [
                    'image/jpeg', 'image/png', 'image/gif',
                    'video/mp4', 'video/mpeg',
                    'audio/mpeg', 'audio/mp3',
                    'application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain'
                ];
                
                if (!in_array($mimeType, $allowedTypes)) {
                    if ($this->debug) error_log("File type not in basic whitelist: $mimeType");
                    throw new Exception("File type not allowed. Supported types: Images, Videos, Audio, Documents");
                }
            }
        } catch (PDOException $e) {
            // If there's a database error, log it but don't block the upload
            if ($this->debug) error_log("Database error checking file types: " . $e->getMessage());
        }

        // Generate unique filename
        $newFilename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $file['name']);
        $targetPath = $this->uploadDir . $newFilename;
        
        if ($this->debug) error_log("Target path for upload: " . $targetPath);

        // Ensure upload directory is writable
        if (!is_writable(dirname($targetPath))) {
            if ($this->debug) error_log("Upload directory is not writable: " . dirname($targetPath));
            throw new Exception('Server configuration error: Upload directory is not writable');
        }

        // Move file to upload directory
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            $error = error_get_last();
            if ($this->debug) error_log("Failed to move uploaded file: " . ($error ? $error['message'] : 'Unknown error'));
            throw new Exception('Failed to save uploaded file. Please check server permissions.');
        }

        if ($this->debug) error_log("File uploaded successfully: " . $targetPath);
        
        // Return file information
        return [
            'url' => $targetPath,
            'type' => $mimeType
        ];
    }
    
    /**
     * Get a human-readable error message for file upload errors
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the allowed size';
            case UPLOAD_ERR_PARTIAL:
                return 'The file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Server configuration error: Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Server error: Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    public function getFileTypeIcon($mimeType) {
        $icons = [
            'image/' => 'fa-image',
            'video/' => 'fa-video',
            'audio/' => 'fa-music',
            'application/pdf' => 'fa-file-pdf',
            'application/msword' => 'fa-file-word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fa-file-word',
            'text/plain' => 'fa-file-text',
            'application/zip' => 'fa-file-archive'
        ];

        foreach ($icons as $type => $icon) {
            if (strpos($mimeType, $type) === 0) {
                return $icon;
            }
        }

        return 'fa-file'; // Default icon
    }
}
