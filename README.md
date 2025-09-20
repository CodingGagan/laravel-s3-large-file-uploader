# Laravel S3 Large File Uploader

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)  
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x-orange)](https://laravel.com)  
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6-green)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)

> 🚀 A production-ready Laravel + JavaScript solution for uploading large files (videos, images) directly to AWS S3 using **pre-signed URLs** and **multipart uploads**. Efficient, secure, and scalable.

---

## Features ✨

- ✅ Direct S3 uploads from frontend – no server overload  
- ✅ Multipart uploads for files larger than 5MB  
- ✅ Progress tracking for real-time UI updates  
- ✅ Video & image validation (size and type)  
- ✅ Automatic database confirmation after successful uploads  
- ✅ Organized S3 folder structure (by date & type)  
- ✅ Secure – AWS keys never exposed to clients  
- ✅ Handles network interruptions with chunk retry  

---

## System Architecture 🏗️

### Backend (Laravel)
- Generates pre-signed URLs  
- Initiates multipart uploads  
- Confirms completed uploads and updates DB records  

### Frontend (JavaScript)
- Handles file validation  
- Splits large files into chunks  
- Uploads chunks using pre-signed URLs  
- Tracks upload progress  
- Confirms completion and triggers database update  

---

## Installation ⚡

1. Clone the repository:

```bash
git clone https://github.com/your-username/laravel-s3-large-file-uploader.git
cd laravel-s3-large-file-uploader
```

## Install PHP dependencies:
```bash
composer install
```

## Copy .env.example to .env and configure:
```bash
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=your-region
AWS_BUCKET=your-bucket-name
```

## Run migrations:
```bash
php artisan migrate
```

## Serve the Laravel app:
```bash
php artisan serve
```
