# IQX AI - Plugin cào dữ liệu và viết lại nội dung

Plugin WordPress tự động cào dữ liệu bài viết từ cafef.vn, gửi đến yescale.io API để viết lại nội dung bằng AI, và tạo bài viết mới trên trang WordPress.

## Tính năng chính

* Tự động cào bài viết từ trang CafeF theo lịch đặt trước
* Sử dụng API của yescale.io để viết lại nội dung bằng AI
* Lưu trữ cơ sở dữ liệu để tránh trùng lặp bài viết
* Cài đặt linh hoạt về thời gian cào, số lượng bài viết, và model AI
* Tạo bài viết WordPress từ nội dung đã viết lại
* Giao diện quản trị trực quan để theo dõi và quản lý các bài viết

## Cài đặt

1. Tải lên và cài đặt plugin thông qua menu WordPress Plugin
2. Kích hoạt plugin
3. Vào IQX AI > Cài đặt để cấu hình API token và các tùy chọn khác
4. Bật chức năng cào tự động hoặc sử dụng nút "Chạy cào dữ liệu ngay" trong Bảng điều khiển

## Cấu hình

### Cài đặt API

* API Token: Token xác thực của yescale.io
* Model AI: Nhập tên model AI để sử dụng (mặc định: gpt-4o)

### Cài đặt cào dữ liệu

* Bật cào dữ liệu: Bật/tắt chức năng cào tự động
* Tần suất cào dữ liệu: Tần suất cào (Mỗi giờ, Hai lần mỗi ngày, Mỗi ngày)
* Số bài viết mỗi lần: Số lượng bài viết cào mỗi lần chạy

### Cài đặt bài viết

* Tự động đăng bài: Tự động tạo bài viết WordPress từ nội dung đã viết lại
* Trạng thái bài viết: Trạng thái mặc định của bài viết (Bản nháp, Xuất bản, Chờ duyệt)
* Tác giả bài viết: Tác giả mặc định cho bài viết
* Chuyên mục bài viết: Chuyên mục mặc định cho bài viết

## Làm việc với Git

### Sử dụng Git với plugin này

```bash
# Clone dự án
git clone <repository-url> iqx-ai

# Di chuyển vào thư mục dự án
cd iqx-ai

# Tạo một nhánh mới để phát triển
git checkout -b feature/ten-tinh-nang

# Sau khi phát triển xong, commit các thay đổi
git add .
git commit -m "Mô tả về thay đổi của bạn"

# Đẩy code lên repository
git push origin feature/ten-tinh-nang
```

### Lưu ý về .gitignore

Dự án sử dụng file `.gitignore` để loại trừ các file không cần thiết khi đẩy lên git repository:

- File log sẽ không được theo dõi bởi git
- Thư mục `logs` được giữ lại nhưng các file trong đó sẽ không được theo dõi
- Các file cấu hình local, file tạm, file build sẽ bị loại trừ

## Hỗ trợ

Nếu bạn gặp vấn đề với plugin, vui lòng liên hệ với chúng tôi qua email hoặc tạo issue trên repository.
