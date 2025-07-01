#!/bin/bash

# Script thiết lập cho plugin IQX AI
echo "Đang thiết lập plugin IQX AI..."

# Tạo thư mục logs nếu chưa tồn tại
if [ ! -d "logs" ]; then
    echo "Tạo thư mục logs..."
    mkdir -p logs
fi

# Đảm bảo file log tồn tại
touch logs/scraper.log
touch logs/api.log

# Đặt quyền cho các thư mục và file
echo "Đặt quyền cho các thư mục và file..."
chmod 755 .
chmod -R 755 includes
chmod 755 logs
chmod 644 logs/.htaccess
chmod 644 logs/scraper.log
chmod 644 logs/api.log
chmod 644 *.php
chmod 644 *.md
chmod 644 .gitignore

echo "Khởi tạo Git nếu chưa được khởi tạo..."
if [ ! -d .git ]; then
    git init
    git add .
    git commit -m "Khởi tạo dự án IQX AI"
fi

echo "Thiết lập hoàn tất!"
echo ""
echo "Hướng dẫn sử dụng:"
echo "1. Tải plugin lên thư mục wp-content/plugins của WordPress"
echo "2. Kích hoạt plugin trong trang quản trị WordPress"
echo "3. Vào IQX AI > Cài đặt để cấu hình plugin"
echo "4. Bắt đầu cào dữ liệu và tạo bài viết"
echo ""
echo "Cảm ơn bạn đã sử dụng IQX AI!" 