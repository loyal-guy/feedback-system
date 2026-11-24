@echo off
REM FeedbackHub database backup for Windows / XAMPP
REM Double-click to run, or schedule it daily with Windows Task Scheduler.
set MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe
set DB_NAME=feedback_system
set DB_USER=root
set BACKUP_DIR=%~dp0..\storage\backups
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmm"') do set STAMP=%%i
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"
"%MYSQLDUMP%" -u %DB_USER% --single-transaction --routines %DB_NAME% > "%BACKUP_DIR%\%DB_NAME%_%STAMP%.sql"
echo Backup saved to %BACKUP_DIR%\%DB_NAME%_%STAMP%.sql
pause
