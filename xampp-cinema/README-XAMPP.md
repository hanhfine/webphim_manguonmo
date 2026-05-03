# XAMPP PHP Cinema App

Thu muc `xampp-cinema/` la mot ung dung PHP thuan de chay trong XAMPP, tach rieng khoi phan WordPress.

## Chay voi XAMPP

1. Mo XAMPP Control Panel.
2. Bat `Apache` va `MySQL`.
3. Copy thu muc `xampp-cinema` vao `htdocs`.

Vi du:

- Windows: `C:\xampp\htdocs\xampp-cinema`
- macOS: `/Applications/XAMPP/xamppfiles/htdocs/xampp-cinema`

4. Mo phpMyAdmin:

```text
http://localhost/phpmyadmin
```

5. Tao database moi ten:

```text
cinema_booking_xampp
```

6. Chon database vua tao, vao tab `Import`, sau do import file:

```text
xampp-cinema/database/cinema_booking_xampp.sql
```

7. Neu cau hinh MySQL cua ban khac mac dinh XAMPP, sua file:

[database.php](/Users/nguyenhoangduong/Documents/webphim_manguonmo/xampp-cinema/config/database.php)

Mac dinh app dang dung:

- host: `127.0.0.1`
- port: `3306`
- database: `cinema_booking_xampp`
- username: `root`
- password: rong

8. Mo ung dung:

```text
http://localhost/xampp-cinema/
```

## Ket noi voi plugin WordPress

App nay da ho tro che do lay du lieu truc tiep tu plugin `cinema-booking` qua REST API.

1. Mo plugin WordPress vao trang `Cinema Booking -> Settings & Integration`.
2. Copy:
   - `Integration API Base`
   - `Integration API Key`
3. Sua file:

[database.php](/Users/nguyenhoangduong/Documents/webphim_manguonmo/xampp-cinema/config/database.php)

4. Doi:

```php
'data_source' => 'wordpress_api',
```

5. Dan `base_url` va `api_key` cua plugin vao muc `wordpress_api`.

Sau khi doi sang `wordpress_api`, neu thieu `base_url`, thieu `api_key`, hoac plugin WordPress khong phan hoi, app se hien thong bao loi ket noi ro rang thay vi am tham quay ve database cu.

Khi do web khach hang `xampp-cinema` se:
- lay phim tu plugin WordPress
- lay suat chieu va so do ghe tu plugin
- gui booking ve plugin WordPress
- tra cuu don dat ve tu plugin WordPress

## Trang co san

- `index.php`: danh sach phim va bo loc
- `movie.php`: chi tiet phim va suat chieu
- `booking.php`: chon ghe va dat ve
- `ticket.php`: trang ve sau khi dat thanh cong
- `my-bookings.php`: tra cuu lich su ve theo email hoac ma dat ve

## Database mau

File SQL da kem:

- 3 phim
- 2 rap
- 2 phong chieu
- so do ghe mau
- 5 suat chieu
- 2 don dat ve demo

Ban co the import xong la xem giao dien ngay.

## Luu y

- App nay dung `PDO` va prepared statements.
- Dat ghe duoc kiem tra trong transaction de tranh trung ghe da dat.
- Thanh toan online chua duoc noi vao gateway that. Ban co the mo rong sau.
