<?php
/**
 * Supabase Service
 * Centralized service for all Supabase Storage interactions
 */

class SupabaseService {
    private $url;
    private $anonKey;
    private $serviceKey;
    private $bucket;
    private $ch;

    public function __construct() {
        $this->url = SUPABASE_URL;
        $this->anonKey = SUPABASE_ANON_KEY;
        $this->serviceKey = SUPABASE_SERVICE_KEY;
        $this->bucket = SUPABASE_BUCKET;

        if (empty($this->url) || empty($this->anonKey)) {
            throw new Exception('Supabase configuration missing. Please set SUPABASE_URL and SUPABASE_ANON_KEY in .env');
        }
    }

    /**
     * Upload a file to Supabase Storage
     * @param string $filePath Local file path
     * @param string $remotePath Remote storage path
     * @param string $contentType MIME type
     * @return array
     */
    public function uploadFile($filePath, $remotePath, $contentType = 'application/octet-stream') {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("Local file not found: $filePath");
            }

            $fileContent = file_get_contents($filePath);
            $endpoint = "$this->url/storage/v1/object/$this->bucket/$remotePath";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->anonKey,
                    'Content-Type: ' . $contentType,
                ],
                CURLOPT_POSTFIELDS => $fileContent,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 && $httpCode !== 201) {
                logMessage("Supabase upload failed: $response", 'error', ['endpoint' => $endpoint, 'httpCode' => $httpCode]);
                throw new Exception("Upload failed with status $httpCode");
            }

            logMessage("File uploaded successfully to Supabase: $remotePath", 'info');

            return [
                'success' => true,
                'path' => $remotePath,
                'url' => "$this->url/storage/v1/object/public/$this->bucket/$remotePath",
                'message' => 'File uploaded successfully'
            ];
        } catch (Exception $e) {
            logMessage("Supabase upload error: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Download a file from Supabase Storage
     * @param string $remotePath Remote storage path
     * @param string $savePath Local path to save file
     * @return array
     */
    public function downloadFile($remotePath, $savePath = null) {
        try {
            $endpoint = "$this->url/storage/v1/object/$this->bucket/$remotePath";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->anonKey,
                ],
            ]);

            $fileContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("Download failed with status $httpCode");
            }

            if ($savePath) {
                if (!file_put_contents($savePath, $fileContent)) {
                    throw new Exception("Failed to save file to $savePath");
                }
                logMessage("File downloaded from Supabase: $remotePath", 'info');
            }

            return [
                'success' => true,
                'content' => $fileContent,
                'size' => strlen($fileContent),
                'message' => 'File downloaded successfully'
            ];
        } catch (Exception $e) {
            logMessage("Supabase download error: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a file from Supabase Storage
     * @param string $remotePath Remote storage path
     * @return array
     */
    public function deleteFile($remotePath) {
        try {
            $endpoint = "$this->url/storage/v1/object/$this->bucket/$remotePath";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->anonKey,
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("Delete failed with status $httpCode: $response");
            }

            logMessage("File deleted from Supabase: $remotePath", 'info');

            return [
                'success' => true,
                'message' => 'File deleted successfully'
            ];
        } catch (Exception $e) {
            logMessage("Supabase delete error: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * List files in a directory
     * @param string $path Directory path
     * @return array
     */
    public function listFiles($path = '') {
        try {
            $endpoint = "$this->url/storage/v1/object/list/$this->bucket";
            if (!empty($path)) {
                $endpoint .= '?prefix=' . urlencode($path);
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->anonKey,
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("List files failed with status $httpCode");
            }

            $data = json_decode($response, true);

            return [
                'success' => true,
                'files' => $data,
                'message' => 'Files listed successfully'
            ];
        } catch (Exception $e) {
            logMessage("Supabase list error: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get file metadata
     * @param string $remotePath Remote storage path
     * @return array
     */
    public function getFileMetadata($remotePath) {
        try {
            $endpoint = "$this->url/storage/v1/object/info/$this->bucket/$remotePath";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->anonKey,
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("Get metadata failed with status $httpCode");
            }

            $metadata = json_decode($response, true);

            return [
                'success' => true,
                'metadata' => $metadata,
                'message' => 'Metadata retrieved successfully'
            ];
        } catch (Exception $e) {
            logMessage("Supabase metadata error: " . $e->getMessage(), 'error');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get public URL for a file
     * @param string $remotePath Remote storage path
     * @return string
     */
    public function getPublicUrl($remotePath) {
        return "$this->url/storage/v1/object/public/$this->bucket/$remotePath";
    }
}

?>
