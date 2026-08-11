@echo off
REM Nightly DB backup — point Windows Task Scheduler at this file.
REM Keeps the last 30 backups, deletes anything older automatically.

set BACKUP_DIR=C:\xampp\htdocs\creator-db\backups
set MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe
set DB_NAME=creator_db
set DB_USER=root

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

for /f "tokens=1-4 delims=/ " %%a in ('date /t') do set DATESTAMP=%%c-%%a-%%b
set TIMESTAMP=%DATESTAMP%_%time:~0,2%%time:~3,2%
set TIMESTAMP=%TIMESTAMP: =0%

"%MYSQLDUMP%" -u %DB_USER% %DB_NAME% > "%BACKUP_DIR%\backup_%TIMESTAMP%.sql"

REM Delete backups older than 30 days
forfiles /p "%BACKUP_DIR%" /m *.sql /d -30 /c "cmd /c del @path" 2>nul

echo Backup complete: %BACKUP_DIR%\backup_%TIMESTAMP%.sql
