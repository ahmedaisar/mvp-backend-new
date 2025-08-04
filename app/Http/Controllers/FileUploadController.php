<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Resort;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Exception;

class FileUploadController extends Controller
{
    /**
     * Upload files (images, documents, etc.)
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'type' => 'required|in:image,document,video,audio',
            'category' => 'nullable|string|max:50',
            'alt_text' => 'nullable|string|max:255',
            'resize' => 'nullable|boolean',
            'dimensions' => 'nullable|array',
            'dimensions.width' => 'nullable|integer|min:100|max:2000',
            'dimensions.height' => 'nullable|integer|min:100|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $type = $request->input('type');
            $category = $request->input('category', 'general');
            
            // Validate file type
            $allowedMimes = $this->getAllowedMimeTypes($type);
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File type not allowed for ' . $type,
                ], 422);
            }

            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;
            
            // Determine storage path
            $basePath = $this->getStoragePath($type, $category);
            $fullPath = $basePath . '/' . $filename;

            // Process image if needed
            if ($type === 'image' && $request->boolean('resize')) {
                $processedFile = $this->processImage($file, $request->input('dimensions', []));
                $stored = Storage::disk('public')->put($fullPath, $processedFile);
            } else {
                $stored = $file->storeAs($basePath, $filename, 'public');
            }

            if (!$stored) {
                throw new Exception('Failed to store file');
            }

            // Generate file metadata
            $metadata = [
                'original_name' => $originalName,
                'filename' => $filename,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'type' => $type,
                'category' => $category,
                'path' => $fullPath,
                'url' => asset('storage/' . str_replace('uploads/', '', $fullPath)),
                'alt_text' => $request->input('alt_text'),
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now()->toISOString(),
            ];

            // Add image-specific metadata
            if ($type === 'image') {
                $imageSize = getimagesizefromstring(Storage::disk('public')->get($fullPath));
                if ($imageSize) {
                    $metadata['dimensions'] = [
                        'width' => $imageSize[0],
                        'height' => $imageSize[1],
                    ];
                }
            }

            // Log the upload
            AuditLog::log('file_uploaded', null, auth()->user(), [
                'filename' => $originalName,
                'type' => $type,
                'size' => $file->getSize(),
                'category' => $category,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $metadata,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload multiple files at once
     */
    public function uploadMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|max:10',
            'files.*' => 'required|file|max:10240',
            'type' => 'required|in:image,document,video,audio',
            'category' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $uploadedFiles = [];
        $errors = [];

        foreach ($request->file('files') as $index => $file) {
            try {
                $singleRequest = new Request([
                    'type' => $request->input('type'),
                    'category' => $request->input('category'),
                    'resize' => $request->input('resize'),
                    'dimensions' => $request->input('dimensions'),
                ]);
                $singleRequest->files->set('file', $file);

                $result = $this->upload($singleRequest);
                $resultData = json_decode($result->getContent(), true);

                if ($resultData['success']) {
                    $uploadedFiles[] = $resultData['data'];
                } else {
                    $errors[] = [
                        'file_index' => $index,
                        'filename' => $file->getClientOriginalName(),
                        'error' => $resultData['message'],
                    ];
                }
            } catch (Exception $e) {
                $errors[] = [
                    'file_index' => $index,
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => count($uploadedFiles) > 0,
            'message' => count($uploadedFiles) . ' files uploaded successfully',
            'data' => [
                'uploaded_files' => $uploadedFiles,
                'errors' => $errors,
                'total_uploaded' => count($uploadedFiles),
                'total_errors' => count($errors),
            ],
        ]);
    }

    /**
     * Get file information
     */
    public function getFile(Request $request, $filename)
    {
        try {
            $type = $request->query('type', 'image');
            $category = $request->query('category', 'general');
            
            $basePath = $this->getStoragePath($type, $category);
            $fullPath = $basePath . '/' . $filename;

            if (!Storage::disk('public')->exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            $fileContent = Storage::disk('public')->get($fullPath);
            $storagePath = storage_path('app/public/' . $fullPath);
            $mimeType = file_exists($storagePath) ? mime_content_type($storagePath) : 'application/octet-stream';
            $size = Storage::disk('public')->size($fullPath);

            $metadata = [
                'filename' => $filename,
                'path' => $fullPath,
                'url' => asset('storage/' . str_replace('uploads/', '', $fullPath)),
                'mime_type' => $mimeType,
                'size' => $size,
                'type' => $type,
                'category' => $category,
                'last_modified' => Storage::disk('public')->lastModified($fullPath),
            ];

            return response()->json([
                'success' => true,
                'data' => $metadata,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete file
     */
    public function deleteFile(Request $request, $filename)
    {
        try {
            $type = $request->query('type', 'image');
            $category = $request->query('category', 'general');
            
            $basePath = $this->getStoragePath($type, $category);
            $fullPath = $basePath . '/' . $filename;

            if (!Storage::disk('public')->exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            // Delete the file
            $deleted = Storage::disk('public')->delete($fullPath);

            if ($deleted) {
                // Log the deletion
                AuditLog::log('file_deleted', null, auth()->user(), [
                    'filename' => $filename,
                    'type' => $type,
                    'category' => $category,
                    'path' => $fullPath,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'File deleted successfully',
                ]);
            } else {
                throw new Exception('Failed to delete file');
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List files in a category
     */
    public function listFiles(Request $request)
    {
        try {
            $type = $request->query('type', 'image');
            $category = $request->query('category', 'general');
            $page = $request->query('page', 1);
            $limit = min($request->query('limit', 20), 100);
            
            $basePath = $this->getStoragePath($type, $category);
            
            if (!Storage::disk('public')->exists($basePath)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'files' => [],
                        'pagination' => [
                            'current_page' => 1,
                            'total_pages' => 0,
                            'total_files' => 0,
                        ],
                    ],
                ]);
            }

            $allFiles = Storage::disk('public')->files($basePath);
            $totalFiles = count($allFiles);
            $totalPages = ceil($totalFiles / $limit);
            $offset = ($page - 1) * $limit;
            
            $paginatedFiles = array_slice($allFiles, $offset, $limit);
            
            $files = [];
            foreach ($paginatedFiles as $filePath) {
                $filename = basename($filePath);
                $storagePath = storage_path('app/public/' . $filePath);
                $files[] = [
                    'filename' => $filename,
                    'path' => $filePath,
                    'url' => asset('storage/' . str_replace('uploads/', '', $filePath)),
                    'size' => Storage::disk('public')->size($filePath),
                    'mime_type' => file_exists($storagePath) ? mime_content_type($storagePath) : 'application/octet-stream',
                    'last_modified' => Storage::disk('public')->lastModified($filePath),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'files' => $files,
                    'pagination' => [
                        'current_page' => (int) $page,
                        'total_pages' => $totalPages,
                        'total_files' => $totalFiles,
                        'per_page' => $limit,
                    ],
                    'filter' => [
                        'type' => $type,
                        'category' => $category,
                    ],
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error listing files: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get storage usage statistics
     */
    public function getStorageStats(Request $request)
    {
        try {
            $stats = [
                'total_size' => 0,
                'file_count' => 0,
                'by_type' => [],
                'by_category' => [],
            ];

            $types = ['image', 'document', 'video', 'audio'];
            
            foreach ($types as $type) {
                $typePath = "uploads/{$type}";
                if (Storage::disk('public')->exists($typePath)) {
                    $typeFiles = Storage::disk('public')->allFiles($typePath);
                    $typeSize = 0;
                    
                    foreach ($typeFiles as $file) {
                        $fileSize = Storage::disk('public')->size($file);
                        $typeSize += $fileSize;
                        $stats['total_size'] += $fileSize;
                        $stats['file_count']++;
                        
                        // Get category from path
                        $pathParts = explode('/', $file);
                        $category = $pathParts[2] ?? 'general';
                        
                        if (!isset($stats['by_category'][$category])) {
                            $stats['by_category'][$category] = [
                                'size' => 0,
                                'count' => 0,
                            ];
                        }
                        
                        $stats['by_category'][$category]['size'] += $fileSize;
                        $stats['by_category'][$category]['count']++;
                    }
                    
                    $stats['by_type'][$type] = [
                        'size' => $typeSize,
                        'count' => count($typeFiles),
                    ];
                }
            }

            // Convert sizes to human readable format
            $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);
            
            foreach ($stats['by_type'] as &$typeStats) {
                $typeStats['size_formatted'] = $this->formatBytes($typeStats['size']);
            }
            
            foreach ($stats['by_category'] as &$categoryStats) {
                $categoryStats['size_formatted'] = $this->formatBytes($categoryStats['size']);
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting storage stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get allowed MIME types for file type
     */
    protected function getAllowedMimeTypes($type)
    {
        return match($type) {
            'image' => [
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 
                'image/webp', 'image/svg+xml', 'image/bmp', 'image/tiff'
            ],
            'document' => [
                'application/pdf', 'text/plain', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation'
            ],
            'video' => [
                'video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo',
                'video/webm', 'video/x-flv', 'video/3gpp'
            ],
            'audio' => [
                'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp3',
                'audio/mp4', 'audio/aac', 'audio/flac'
            ],
            default => []
        };
    }

    /**
     * Get storage path for file type and category
     */
    protected function getStoragePath($type, $category)
    {
        return "uploads/{$type}/{$category}";
    }

    /**
     * Process image (resize, optimize)
     */
    protected function processImage($file, $dimensions = [])
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file);
        
        // Resize if dimensions provided
        if (!empty($dimensions['width']) || !empty($dimensions['height'])) {
            $width = $dimensions['width'] ?? null;
            $height = $dimensions['height'] ?? null;
            
            if ($width && $height) {
                $image->resize($width, $height);
            } elseif ($width) {
                $image->scale(width: $width);
            } elseif ($height) {
                $image->scale(height: $height);
            }
        }

        // Encode and return
        return $image->encode();
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
