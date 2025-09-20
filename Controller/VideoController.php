<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Upload;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Validator;

class VideoController extends Controller
{
    /**
     * Get pre-signed URL for direct upload
     */
    public function getUploadUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fileName'   => 'required|string',
            'fileType'   => 'required|string',
            'fileSize'   => 'required|integer',
            'uploadType' => 'required|in:video,thumbnail'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $fileName    = $request->fileName;
            $fileType    = $request->fileType;
            $uploadType  = $request->uploadType;

            $timestamp   = time();
            $uniqueId    = uniqid();
            $ext         = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = "{$timestamp}_{$uniqueId}.{$ext}";

            $folderPath  = "videoUploadFolder/{$uploadType}s/" . date('Y/m/d');
            $filePath    = "{$folderPath}/{$newFileName}";

            $s3Client = new S3Client([
                'version'     => 'latest',
                'region'      => env('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key'    => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            $cmd = $s3Client->getCommand('PutObject', [
                'Bucket'       => env('AWS_BUCKET'),
                'Key'          => $filePath,
                'ContentType'  => $fileType,
                'CacheControl' => 'max-age=31536000, public'
            ]);

            $presignedUrl = (string) $s3Client
                ->createPresignedRequest($cmd, '+1 hour')
                ->getUri();

            return response()->json([
                'success' => true,
                'data'    => [
                    'uploadUrl' => $presignedUrl,
                    'filePath'  => $filePath,
                    'fileName'  => $newFileName
                ]
            ]);

        } catch (AwsException $e) {
            Log::error('S3 URL generation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function initiateMultipartUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fileName'   => 'required|string',
            'fileType'   => 'required|string',
            'fileSize'   => 'required|integer',
            'uploadType' => 'required|in:video,thumbnail'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $fileName   = $request->fileName;
        $fileType   = $request->fileType;
        $uploadType = $request->uploadType;

        $timestamp   = time();
        $uniqueId    = uniqid();
        $ext         = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = "{$timestamp}_{$uniqueId}.{$ext}";
        $folderPath  = "videoFolder/{$uploadType}s/" . date('Y/m/d');
        $filePath    = "{$folderPath}/{$newFileName}";

        $s3Client = new S3Client([
            'version'     => 'latest',
            'region'      => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);

        $result = $s3Client->createMultipartUpload([
            'Bucket'      => env('AWS_BUCKET'),
            'Key'         => $filePath,
            'ContentType' => $fileType,
            'CacheControl'=> 'max-age=31536000, public'
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'uploadId' => $result['UploadId'],
                'filePath' => $filePath,
                'fileName' => $newFileName
            ]
        ]);
    }

    public function getPartUploadUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uploadId'   => 'required|string',
            'filePath'   => 'required|string',
            'partNumber' => 'required|integer|min:1|max:10000',
            'uploadType' => 'required|in:video,thumbnail'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $s3Client = new S3Client([
            'version'     => 'latest',
            'region'      => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);

        $cmd = $s3Client->getCommand('UploadPart', [
            'Bucket'     => env('AWS_BUCKET'),
            'Key'        => $request->filePath,
            'UploadId'   => $request->uploadId,
            'PartNumber' => $request->partNumber
        ]);

        $url = (string) $s3Client->createPresignedRequest($cmd, '+1 hour')->getUri();

        return response()->json(['success' => true, 'data' => ['uploadUrl' => $url]]);
    }

    public function completeMultipartUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uploadId'   => 'required|string',
            'filePath'   => 'required|string',
            'uploadType' => 'required|in:video,thumbnail',
            'fileSize'   => 'required|integer',
            'parts'      => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $parts = json_decode($request->parts, true);

        $s3Client = new S3Client([
            'version'     => 'latest',
            'region'      => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);

        $s3Client->completeMultipartUpload([
            'Bucket'          => env('AWS_BUCKET'),
            'Key'             => $request->filePath,
            'UploadId'        => $request->uploadId,
            'MultipartUpload' => ['Parts' => $parts]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Multipart upload completed successfully',
            'data'    => [
                'filePath' => $request->filePath,
                'fileName' => basename($request->filePath)
            ]
        ]);
    }

    public function confirmUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filePath' => 'required|string',
            'fileName' => 'required|string',
            'uploadType' => 'required|in:video,thumbnail',
            'fileSize' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            if (!Storage::disk('s3')->exists($request->filePath)) {
                return response()->json(['success' => false, 'message' => 'File not found in S3'], 404);
            }

            $upload = Upload::create([
                'file_name'   => $request->fileName,
                'file_path'   => $request->filePath,
                'upload_type' => $request->uploadType,
                'file_size'   => $request->fileSize
            ]);

            return response()->json(['success' => true, 'message' => 'Upload confirmed', 'data' => $upload]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
