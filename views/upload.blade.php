{{-- resources/views/file_upload_modal.blade.php --}}

@extends('layouts.app') {{-- Adjust according to your layout --}}

@section('content')
<div class="container mt-5">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
        Add New Item
    </button>
</div>

{{-- Generic Upload Modal --}}
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Add New Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    {{-- Generic Name & Category --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="item_name" class="form-label">Item Name *</label>
                            <input type="text" class="form-control" id="item_name" name="item_name" placeholder="Enter item name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Category *</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                            </select>
                        </div>
                    </div>

                    {{-- File Upload --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="main_file" class="form-label">Upload File *</label>
                            <input type="file" class="form-control" id="main_file" name="main_file">
                            <small class="text-muted">Supported formats: MP4, AVI, MOV, WebM. Max size: 1GB</small>
                            <div class="progress mt-2" id="fileProgress" style="display:none;">
                                <div class="progress-bar" role="progressbar" style="width:0%"></div>
                            </div>
                            <div id="filePreview" class="mt-2" style="display:none;"></div>
                        </div>

                        {{-- Thumbnail Upload --}}
                        <div class="col-md-6 mb-3">
                            <label for="thumbnail" class="form-label">Upload Thumbnail *</label>
                            <input type="file" class="form-control" id="thumbnail" name="thumbnail" accept="image/*">
                            <small class="text-muted">Square image recommended. Max size: 5MB</small>
                            <div class="progress mt-2" id="thumbnailProgress" style="display:none;">
                                <div class="progress-bar" role="progressbar" style="width:0%"></div>
                            </div>
                            <div id="thumbnailPreview" class="mt-2" style="display:none;"></div>
                        </div>
                    </div>

                    {{-- Active Checkbox --}}
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Full-Screen Loader --}}
<div id="fullScreenLoader" style="display:none;">
    <div style="position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.8); z-index:9999; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(5px);">
        <div style="background:white; padding:30px; border-radius:15px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.3); min-width:300px;">
            <div class="spinner-border text-primary mb-3" style="width:3rem; height:3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5 class="text-primary mb-2">Uploading Files</h5>
            <p class="text-muted mb-0">Please wait while your files are being uploaded...</p>
            <div class="mt-3">
                <small class="text-muted">Do not close this window or refresh the page</small>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let isUploading = false;
let uploadedFilePath = null;
let uploadedThumbnailPath = null;

function uploadFileHandler(file, type) {
    try {
        s3UploadHelper.validateFile(file, type);
        isUploading = true;
        disableModal();

        const progressId = type === 'main_file' ? '#fileProgress' : '#thumbnailProgress';
        const previewId = type === 'main_file' ? '#filePreview' : '#thumbnailPreview';

        updateLoaderMessage(`Preparing ${type} upload...`);
        $(progressId).show().find('.progress-bar').css('width', '0%').text('0%');

        s3UploadHelper.uploadFile(
            file,
            type,
            (progress) => {
                $(progressId + ' .progress-bar').css('width', progress + '%').text(Math.round(progress) + '%');
                updateLoaderMessage(`Uploading ${type}: ${Math.round(progress)}%`);
            },
            (result) => {
                if(type==='main_file') uploadedFilePath = result.filePath;
                else uploadedThumbnailPath = result.filePath;
                $(progressId).hide();
                $(previewId).html('<div class="alert alert-success">'+type.charAt(0).toUpperCase()+type.slice(1)+' uploaded successfully!</div>');
                isUploading = false;
                checkUploadStatus();
            },
            (error) => {
                $(progressId).hide();
                $(previewId).html('<div class="alert alert-danger">'+type+' upload failed: '+error+'</div>');
                isUploading = false;
                checkUploadStatus();
            }
        );
    } catch (error) {
        const previewId = type === 'main_file' ? '#filePreview' : '#thumbnailPreview';
        $(previewId).html('<div class="alert alert-danger">' + error.message + '</div>');
        isUploading = false;
        checkUploadStatus();
    }
}

function disableModal() {
    $('#uploadForm input, #uploadForm select, #uploadForm textarea').prop('disabled', true);
    $('#uploadForm button[type="submit"]').prop('disabled', true);
    $('#fullScreenLoader').show();
    $('#uploadModal').off('hide.bs.modal').on('hide.bs.modal', function(e){
        if(isUploading){ e.preventDefault(); e.stopPropagation(); }
    });
}

function enableModal() {
    $('#uploadForm input, #uploadForm select, #uploadForm textarea').prop('disabled', false);
    $('#uploadForm button[type="submit"]').prop('disabled', false);
    $('#fullScreenLoader').fadeOut(300);
    $('#uploadModal').off('hide.bs.modal');
}

function updateLoaderMessage(message) {
    $('#fullScreenLoader p').text(message);
}

function checkUploadStatus() {
    if(!isUploading) enableModal();
}

$('#main_file').on('change', function(){ uploadFileHandler(this.files[0], 'main_file'); });
$('#thumbnail').on('change', function(){ uploadFileHandler(this.files[0], 'thumbnail'); });
</script>
@endsection
