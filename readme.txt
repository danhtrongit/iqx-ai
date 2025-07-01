=== IQX AI ===
Contributors: iqxteam
Tags: content, scraper, ai, rewriter, auto-post
Requires at least: 5.6
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tự động lấy dữ liệu bài viết từ cafef.vn và viết lại chuẩn SEO bằng API của yescale.io.

== Description ==

IQX AI là plugin WordPress tự động lấy dữ liệu bài viết từ cafef.vn, gửi đến yescale.io API để viết lại nội dung, và tạo bài viết mới trên trang WordPress của bạn.

Tính năng chính:

* Tự động cào bài viết từ trang CafeF theo lịch đặt trước
* Sử dụng API của yescale.io để viết lại nội dung bằng AI
* Lưu trữ cơ sở dữ liệu để tránh trùng lặp bài viết
* Cài đặt linh hoạt về thời gian cào, số lượng bài viết, và model AI
* Tạo bài viết WordPress từ nội dung đã viết lại
* Giao diện quản trị trực quan để theo dõi và quản lý các bài viết

== Installation ==

1. Tải lên và cài đặt plugin thông qua menu WordPress Plugin
2. Kích hoạt plugin
3. Vào IQX AI > Settings để cấu hình API token và các tùy chọn khác
4. Bật chức năng cào tự động hoặc sử dụng nút "Run Scraper Now" trong Dashboard

== Configuration ==

Cấu hình API:

* API Token: Token xác thực của yescale.io
* AI Model: Lựa chọn model AI để sử dụng (mặc định: gpt-4o)

Cấu hình cào dữ liệu:

* Enable Scraping: Bật/tắt chức năng cào tự động
* Scraping Frequency: Tần suất cào (hourly, twice daily, daily)
* Articles Per Run: Số lượng bài viết cào mỗi lần

Cấu hình bài viết:

* Auto Publish Posts: Tự động tạo bài viết WordPress từ nội dung đã viết lại
* Post Status: Trạng thái của bài viết (draft, publish, pending)
* Post Author: Tác giả mặc định cho bài viết
* Post Category: Chuyên mục mặc định cho bài viết

== Frequently Asked Questions ==

= Làm thế nào để có API token của yescale.io? =

Bạn cần đăng ký tài khoản tại yescale.io và tạo API token từ bảng điều khiển của họ.

= Plugin có thể cào bài viết từ các trang web khác không? =

Phiên bản hiện tại chỉ hỗ trợ cafef.vn. Các phiên bản tiếp theo sẽ bổ sung thêm nhiều nguồn khác.

= Làm thế nào để kiểm soát chất lượng nội dung viết lại? =

Bạn có thể điều chỉnh model AI trong cài đặt. Các model cao cấp hơn thường cho kết quả tốt hơn.

== Screenshots ==

1. Dashboard hiển thị thống kê và trạng thái
2. Trang quản lý các bài viết đã cào
3. Trang cài đặt plugin

== Changelog ==

= 1.0.0 =
* Phiên bản đầu tiên 