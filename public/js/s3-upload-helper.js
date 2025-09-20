class S3UploadHelper {
    constructor() {
        this.uploadQueue = [];
        this.isUploading = false;
    }

    /**
     * Upload file to S3 using multipart upload
     * @param {File} file - The file to upload
     * @param {string} uploadType - 'video' or 'thumbnail'
     * @param {Function} onProgress - Progress callback
     * @param {Function} onSuccess - Success callback
     * @param {Function} onError - Error callback
     */
    async uploadFile(file, uploadType, onProgress, onSuccess, onError) {
        try {
            // Step 1: Initialize multipart upload
            const initResponse = await this.initiateMultipartUpload(file, uploadType);
            if (!initResponse.success) throw new Error(initResponse.message);

            const { uploadId, filePath, fileName } = initResponse.data;

            // Step 2: Upload parts
            const uploadResult = await this.uploadParts(file, uploadId, filePath, uploadType, onProgress);
            if (!uploadResult.success) throw new Error(uploadResult.message);

            // Step 3: Complete multipart upload
            const completeResult = await this.completeMultipartUpload(
                uploadId, filePath, uploadType, file.size, uploadResult.parts
            );
            if (!completeResult.success) throw new Error(completeResult.message);

            // Step 4: Confirm upload (save record in DB)
            const confirmResult = await this.confirmUpload(filePath, fileName, uploadType, file.size);

            onSuccess(confirmResult.data);

        } catch (error) {
            console.error('Upload failed:', error);
            onError(error.message);
        }
    }

    /**
     * Step 1: Initialize multipart upload
     */
    async initiateMultipartUpload(file, uploadType) {
        const formData = new FormData();
        formData.append('fileName', file.name);
        formData.append('fileType', file.type);
        formData.append('fileSize', file.size);
        formData.append('uploadType', uploadType);

        const response = await fetch('/api/uploads/initiate-multipart-upload', {
            method: 'POST',
            body: formData
        });

        return await response.json();
    }

    /**
     * Step 2a: Get pre-signed URL for a part
     */
    async getPartUploadUrl(uploadId, filePath, partNumber, uploadType) {
        const formData = new FormData();
        formData.append('uploadId', uploadId);
        formData.append('filePath', filePath);
        formData.append('partNumber', partNumber);
        formData.append('uploadType', uploadType);

        const response = await fetch('/api/uploads/get-part-upload-url', {
            method: 'POST',
            body: formData
        });

        return await response.json();
    }

    /**
     * Step 2b: Upload parts in chunks
     */
    async uploadParts(file, uploadId, filePath, uploadType, onProgress) {
        const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        const parts = [];

        for (let partNumber = 1; partNumber <= totalChunks; partNumber++) {
            const start = (partNumber - 1) * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);

            const partUrlResponse = await this.getPartUploadUrl(uploadId, filePath, partNumber, uploadType);
            if (!partUrlResponse.success) throw new Error(`Failed to get upload URL for part ${partNumber}`);

            const uploadResult = await this.uploadPart(chunk, partUrlResponse.data.uploadUrl, partNumber);
            if (!uploadResult.success) throw new Error(`Failed to upload part ${partNumber}`);

            parts.push({ PartNumber: partNumber, ETag: uploadResult.etag });

            const progress = (partNumber / totalChunks) * 100;
            onProgress(progress);
        }

        return { success: true, parts };
    }

    /**
     * Step 2c: Upload a single part
     */
    async uploadPart(chunk, uploadUrl, partNumber) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', uploadUrl);
            xhr.setRequestHeader('Content-Type', 'application/octet-stream');

            xhr.onload = () => {
                if (xhr.status === 200) {
                    resolve({ success: true, etag: xhr.getResponseHeader('ETag') });
                } else {
                    reject(new Error(`Part ${partNumber} upload failed`));
                }
            };

            xhr.onerror = () => reject(new Error(`Part ${partNumber} network error`));
            xhr.send(chunk);
        });
    }

    /**
     * Step 3: Complete multipart upload
     */
    async completeMultipartUpload(uploadId, filePath, uploadType, fileSize, parts) {
        const formData = new FormData();
        formData.append('uploadId', uploadId);
        formData.append('filePath', filePath);
        formData.append('uploadType', uploadType);
        formData.append('fileSize', fileSize);
        formData.append('parts', JSON.stringify(parts));

        const response = await fetch('/api/uploads/complete-multipart-upload', {
            method: 'POST',
            body: formData
        });

        return await response.json();
    }

    /**
     * Step 4: Confirm upload (save DB record)
     */
    async confirmUpload(filePath, fileName, uploadType, fileSize) {
        const formData = new FormData();
        formData.append('filePath', filePath);
        formData.append('fileName', fileName);
        formData.append('uploadType', uploadType);
        formData.append('fileSize', fileSize);

        const response = await fetch('/api/uploads/confirm-upload', {
            method: 'POST',
            body: formData
        });

        return await response.json();
    }

    /**
     * Validate file before upload
     */
    validateFile(file, uploadType) {
        const maxSizes = {
            video: 1024 * 1024 * 1024, // 1GB
            thumbnail: 5 * 1024 * 1024  // 5MB
        };

        const allowedTypes = {
            video: ['video/mp4', 'video/webm'],
            thumbnail: ['image/jpeg', 'image/png', 'image/webp']
        };

        if (file.size > maxSizes[uploadType]) {
            throw new Error(`File size exceeds maximum allowed size for ${uploadType}`);
        }
        if (!allowedTypes[uploadType].includes(file.type)) {
            throw new Error(`File type not allowed for ${uploadType}`);
        }

        return true;
    }
}

// Export for Node.js usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = S3UploadHelper;
}
