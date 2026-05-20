@echo off
echo Updating database for Absensi SMK...
echo.

REM Cek apakah PHP terinstall
php --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: PHP tidak ditemukan. Pastikan PHP terinstall dan ada di PATH.
    echo.
    echo Cara mengatasi:
    echo 1. Install XAMPP atau WAMP
    echo 2. Tambahkan PHP ke PATH environment variable
    echo 3. Atau jalankan: C:\path\to\php\php.exe update_database.php
    pause
    exit /b 1
)

REM Jalankan update database
php update_database.php

if errorlevel 1 (
    echo.
    echo Update database gagal!
) else (
    echo.
    echo Update database berhasil!
)

echo.
pause