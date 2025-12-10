@echo off
echo ========================================
echo  重启 PHP 服务以清除 OPcache
echo ========================================
echo.

echo [1/3] 停止所有 PHP-CGI 进程...
taskkill /F /IM php-cgi.exe >nul 2>&1
if %errorlevel% equ 0 (
    echo ✓ PHP-CGI 进程已停止
) else (
    echo ! 没有找到运行中的 PHP-CGI 进程
)
echo.

echo [2/3] 等待 2 秒...
timeout /t 2 /nobreak >nul
echo.

echo [3/3] 请在 phpstudy 控制面板中点击"启动"按钮重启 PHP
echo.
echo ========================================
echo  操作提示
echo ========================================
echo 1. 打开 phpstudy 控制面板
echo 2. 在"网站"或"首页"中找到 PHP 服务
echo 3. 点击"启动"或"重启"按钮
echo.
echo 完成后，刷新网页测试批量同步功能
echo ========================================
pause
