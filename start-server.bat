@echo off
@echo off
echo ========================================
echo Starting PHP Live Server
echo ========================================
echo.
echo Server will be available at: http://localhost:8000
echo Open in browser: http://localhost:8000/index.html
echo (Do NOT open the HTML file directly - use the URL above)
echo Project directory: %CD%
echo.
echo Press Ctrl+C to stop the server
echo.
echo ========================================
echo.

cd /d "%~dp0"
php -S localhost:8000

