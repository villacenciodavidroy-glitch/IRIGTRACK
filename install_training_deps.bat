@echo off
REM Install training dependencies in virtual environment
echo Activating Python virtual environment...
call backend-laravel\ml_api_env\Scripts\activate.bat

echo.
echo Installing training dependencies...
echo.

pip install psycopg2-binary

echo.
echo Done! Dependencies installed.
echo You can now run: train_model.bat
pause

