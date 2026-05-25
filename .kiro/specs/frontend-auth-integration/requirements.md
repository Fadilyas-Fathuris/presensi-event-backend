# Requirements Document

## Introduction

Dokumen ini mendefinisikan requirements untuk integrasi frontend dengan backend API sistem Presensi Event Alumni. Backend telah dibangun menggunakan Laravel 11 dengan Laravel Sanctum untuk autentikasi API. Dokumentasi ini ditujukan untuk developer frontend agar dapat mengintegrasikan aplikasi mereka dengan backend API yang sudah tersedia, mencakup fitur registrasi, login, logout, dan pengambilan profil pengguna.

## Glossary

- **Frontend_Application**: Aplikasi client-side yang akan mengkonsumsi API backend
- **Backend_API**: REST API yang dibangun dengan Laravel 11 dan Sanctum
- **Access_Token**: Token autentikasi Bearer yang dihasilkan oleh Sanctum untuk mengakses protected endpoints
- **Alumni**: Pengguna dengan role alumni yang terdaftar dalam sistem
- **Admin**: Pengguna dengan role admin yang memiliki hak akses khusus
- **Token_Storage**: Mekanisme penyimpanan token di sisi client (localStorage, sessionStorage, atau secure storage)
- **Request_Header**: HTTP header yang berisi informasi autentikasi
- **Validation_Error**: Error response dengan status code 422 yang berisi detail kesalahan validasi
- **Authentication_Error**: Error response dengan status code 401 yang menandakan kredensial tidak valid atau token tidak valid
- **Single_Session_Policy**: Kebijakan dimana hanya satu token aktif per user, token lama akan dihapus saat login baru

## Requirements

### Requirement 1: Registrasi Alumni Baru

**User Story:** Sebagai developer frontend, saya ingin mengintegrasikan fitur registrasi alumni, sehingga pengguna baru dapat mendaftar dan langsung mendapatkan access token.

#### Acceptance Criteria

1. THE Frontend_Application SHALL mengirim POST request ke endpoint `/api/auth/register` dengan Content-Type `application/json`
2. THE Frontend_Application SHALL menyertakan field wajib: name, gender, email, password, password_confirmation, dan tanggal_lahir dalam request body
3. THE Frontend_Application SHALL menyertakan field opsional: phone dan angkatan dalam request body jika tersedia
4. THE Frontend_Application SHALL memvalidasi bahwa gender hanya berisi nilai "Laki-laki" atau "Perempuan" sebelum mengirim request
5. THE Frontend_Application SHALL memvalidasi bahwa password minimal 8 karakter sebelum mengirim request
6. THE Frontend_Application SHALL memvalidasi bahwa password dan password_confirmation memiliki nilai yang sama sebelum mengirim request
7. THE Frontend_Application SHALL memvalidasi bahwa email memiliki format email yang valid sebelum mengirim request
8. THE Frontend_Application SHALL memvalidasi bahwa tanggal_lahir tidak lebih dari hari ini sebelum mengirim request
9. WHEN registrasi berhasil (status 201), THE Frontend_Application SHALL menyimpan access_token dari response ke Token_Storage
10. WHEN registrasi berhasil (status 201), THE Frontend_Application SHALL menyimpan data user dari response untuk keperluan UI
11. WHEN registrasi berhasil (status 201), THE Frontend_Application SHALL mengarahkan pengguna ke halaman dashboard atau home
12. IF validation error terjadi (status 422), THEN THE Frontend_Application SHALL menampilkan pesan error spesifik untuk setiap field yang gagal validasi
13. THE Frontend_Application SHALL menangani network error dengan menampilkan pesan error yang user-friendly

### Requirement 2: Login Pengguna

**User Story:** Sebagai developer frontend, saya ingin mengintegrasikan fitur login, sehingga pengguna yang sudah terdaftar dapat masuk ke sistem dan mendapatkan access token.

#### Acceptance Criteria

1. THE Frontend_Application SHALL mengirim POST request ke endpoint `/api/auth/login` dengan Content-Type `application/json`
2. THE Frontend_Application SHALL menyertakan field email dan password dalam request body
3. THE Frontend_Application SHALL memvalidasi bahwa email memiliki format email yang valid sebelum mengirim request
4. THE Frontend_Application SHALL memvalidasi bahwa password tidak kosong sebelum mengirim request
5. WHEN login berhasil (status 200), THE Frontend_Application SHALL menyimpan access_token dari response ke Token_Storage
6. WHEN login berhasil (status 200), THE Frontend_Application SHALL menyimpan data user dari response untuk keperluan UI
7. WHEN login berhasil (status 200), THE Frontend_Application SHALL mengarahkan pengguna ke halaman dashboard atau home
8. IF authentication error terjadi (status 401), THEN THE Frontend_Application SHALL menampilkan pesan "Email atau password salah"
9. IF validation error terjadi (status 422), THEN THE Frontend_Application SHALL menampilkan pesan error spesifik untuk setiap field yang gagal validasi
10. THE Frontend_Application SHALL menangani network error dengan menampilkan pesan error yang user-friendly
11. THE Frontend_Application SHALL menginformasikan pengguna bahwa login baru akan menghapus sesi login sebelumnya (Single_Session_Policy)

### Requirement 3: Logout Pengguna

**User Story:** Sebagai developer frontend, saya ingin mengintegrasikan fitur logout, sehingga pengguna dapat keluar dari sistem dengan aman dan token mereka dihapus.

#### Acceptance Criteria

1. THE Frontend_Application SHALL mengirim POST request ke endpoint `/api/auth/logout` dengan Content-Type `application/json`
2. THE Frontend_Application SHALL menyertakan Access_Token dalam Request_Header dengan format `Authorization: Bearer {token}`
3. WHEN logout berhasil (status 200), THE Frontend_Application SHALL menghapus access_token dari Token_Storage
4. WHEN logout berhasil (status 200), THE Frontend_Application SHALL menghapus data user yang tersimpan
5. WHEN logout berhasil (status 200), THE Frontend_Application SHALL mengarahkan pengguna ke halaman login
6. IF authentication error terjadi (status 401), THEN THE Frontend_Application SHALL menghapus token lokal dan mengarahkan ke halaman login
7. THE Frontend_Application SHALL menangani network error dengan tetap menghapus token lokal dan mengarahkan ke halaman login

### Requirement 4: Pengambilan Profil Pengguna

**User Story:** Sebagai developer frontend, saya ingin mengintegrasikan fitur pengambilan profil, sehingga aplikasi dapat menampilkan data pengguna yang sedang login.

#### Acceptance Criteria

1. THE Frontend_Application SHALL mengirim GET request ke endpoint `/api/auth/me`
2. THE Frontend_Application SHALL menyertakan Access_Token dalam Request_Header dengan format `Authorization: Bearer {token}`
3. WHEN request berhasil (status 200), THE Frontend_Application SHALL menyimpan atau memperbarui data user dari response
4. WHEN request berhasil (status 200), THE Frontend_Application SHALL menampilkan data user di UI
5. IF authentication error terjadi (status 401), THEN THE Frontend_Application SHALL menghapus token lokal dan mengarahkan pengguna ke halaman login
6. THE Frontend_Application SHALL memanggil endpoint ini saat aplikasi pertama kali dimuat untuk memverifikasi token yang tersimpan
7. THE Frontend_Application SHALL menangani network error dengan menampilkan pesan error yang user-friendly tanpa menghapus token

### Requirement 5: Manajemen Token Autentikasi

**User Story:** Sebagai developer frontend, saya ingin mengelola token autentikasi dengan aman, sehingga pengguna tetap terautentikasi dan data mereka terlindungi.

#### Acceptance Criteria

1. THE Frontend_Application SHALL menyimpan Access_Token di Token_Storage yang aman (localStorage untuk web, secure storage untuk mobile)
2. THE Frontend_Application SHALL menyertakan Access_Token dalam Request_Header untuk setiap request ke protected endpoints
3. THE Frontend_Application SHALL menggunakan format `Authorization: Bearer {token}` untuk Request_Header
4. WHEN token tidak tersedia di Token_Storage, THE Frontend_Application SHALL mengarahkan pengguna ke halaman login
5. WHEN response 401 diterima dari Backend_API, THE Frontend_Application SHALL menghapus token dan mengarahkan ke halaman login
6. THE Frontend_Application SHALL memvalidasi keberadaan token sebelum mengakses halaman yang memerlukan autentikasi
7. THE Frontend_Application SHALL tidak menyimpan password pengguna di Token_Storage atau memory
8. WHERE aplikasi mendukung "Remember Me", THE Frontend_Application SHALL menggunakan localStorage untuk token persistence
9. WHERE aplikasi tidak mendukung "Remember Me", THE Frontend_Application SHALL menggunakan sessionStorage untuk token yang akan hilang saat browser ditutup

### Requirement 6: Penanganan Error dan Validasi

**User Story:** Sebagai developer frontend, saya ingin menangani error dengan baik, sehingga pengguna mendapatkan feedback yang jelas saat terjadi kesalahan.

#### Acceptance Criteria

1. WHEN validation error (status 422) diterima, THE Frontend_Application SHALL menampilkan pesan error untuk setiap field yang gagal validasi
2. WHEN authentication error (status 401) diterima, THE Frontend_Application SHALL menampilkan pesan "Sesi Anda telah berakhir, silakan login kembali"
3. WHEN network error terjadi, THE Frontend_Application SHALL menampilkan pesan "Tidak dapat terhubung ke server, periksa koneksi internet Anda"
4. WHEN server error (status 500) terjadi, THE Frontend_Application SHALL menampilkan pesan "Terjadi kesalahan pada server, silakan coba lagi nanti"
5. THE Frontend_Application SHALL menampilkan loading indicator saat mengirim request ke Backend_API
6. THE Frontend_Application SHALL menonaktifkan tombol submit saat request sedang diproses untuk mencegah double submission
7. THE Frontend_Application SHALL menampilkan pesan error dalam bahasa Indonesia yang mudah dipahami pengguna
8. THE Frontend_Application SHALL menyediakan cara untuk menutup atau menghilangkan pesan error setelah ditampilkan

### Requirement 7: Validasi Input di Client-Side

**User Story:** Sebagai developer frontend, saya ingin memvalidasi input pengguna di client-side, sehingga dapat memberikan feedback cepat sebelum mengirim request ke server.

#### Acceptance Criteria

1. THE Frontend_Application SHALL memvalidasi bahwa field name tidak kosong dan maksimal 255 karakter
2. THE Frontend_Application SHALL memvalidasi bahwa field email memiliki format email yang valid dan maksimal 255 karakter
3. THE Frontend_Application SHALL memvalidasi bahwa field password minimal 8 karakter
4. THE Frontend_Application SHALL memvalidasi bahwa field password_confirmation sama dengan password
5. THE Frontend_Application SHALL memvalidasi bahwa field gender hanya berisi "Laki-laki" atau "Perempuan"
6. THE Frontend_Application SHALL memvalidasi bahwa field phone maksimal 20 karakter jika diisi
7. THE Frontend_Application SHALL memvalidasi bahwa field angkatan maksimal 10 karakter jika diisi
8. THE Frontend_Application SHALL memvalidasi bahwa field tanggal_lahir memiliki format date yang valid dan tidak lebih dari hari ini
9. THE Frontend_Application SHALL menampilkan pesan validasi secara real-time saat pengguna mengisi form
10. THE Frontend_Application SHALL menonaktifkan tombol submit jika ada field yang tidak valid

### Requirement 8: Response Data Structure

**User Story:** Sebagai developer frontend, saya ingin memahami struktur response dari API, sehingga dapat memproses data dengan benar.

#### Acceptance Criteria

1. WHEN registrasi atau login berhasil, THE Backend_API SHALL mengembalikan response dengan struktur: `{ success: true, message: string, data: { user: object, access_token: string, token_type: "Bearer" } }`
2. WHEN logout berhasil, THE Backend_API SHALL mengembalikan response dengan struktur: `{ success: true, message: string }`
3. WHEN get profile berhasil, THE Backend_API SHALL mengembalikan response dengan struktur: `{ success: true, data: { user: object } }`
4. WHEN validation error terjadi, THE Backend_API SHALL mengembalikan response dengan struktur: `{ success: false, message: "Validation failed", errors: object }`
5. WHEN authentication error terjadi, THE Backend_API SHALL mengembalikan response dengan struktur: `{ success: false, message: string }`
6. THE user object dalam response SHALL berisi: id, name, gender, email, phone, angkatan, role, status, created_at, updated_at
7. THE user object dalam response SHALL NOT berisi: password, remember_token
8. THE Frontend_Application SHALL memeriksa field success dalam response untuk menentukan apakah request berhasil atau gagal

### Requirement 9: Security Best Practices

**User Story:** Sebagai developer frontend, saya ingin mengimplementasikan security best practices, sehingga aplikasi aman dari serangan umum.

#### Acceptance Criteria

1. THE Frontend_Application SHALL tidak menyimpan password dalam bentuk plain text di memory atau storage
2. THE Frontend_Application SHALL menghapus token dari memory dan storage saat logout
3. THE Frontend_Application SHALL menggunakan HTTPS untuk semua komunikasi dengan Backend_API di production
4. THE Frontend_Application SHALL tidak menampilkan Access_Token di console log atau error message di production
5. THE Frontend_Application SHALL mengimplementasikan CSRF protection jika menggunakan cookie-based authentication
6. THE Frontend_Application SHALL memvalidasi dan sanitize semua input pengguna sebelum mengirim ke Backend_API
7. THE Frontend_Application SHALL mengimplementasikan rate limiting di UI untuk mencegah brute force attack pada form login
8. WHERE aplikasi berjalan di browser, THE Frontend_Application SHALL menggunakan Content Security Policy (CSP) headers
9. THE Frontend_Application SHALL menangani XSS dengan melakukan proper escaping saat menampilkan data user di UI

### Requirement 10: API Base URL Configuration

**User Story:** Sebagai developer frontend, saya ingin mengkonfigurasi base URL API dengan mudah, sehingga aplikasi dapat berjalan di berbagai environment (development, staging, production).

#### Acceptance Criteria

1. THE Frontend_Application SHALL menyimpan base URL Backend_API dalam environment variable atau configuration file
2. THE Frontend_Application SHALL menggunakan base URL yang berbeda untuk development, staging, dan production environment
3. THE Frontend_Application SHALL menambahkan base URL sebagai prefix untuk semua API endpoint
4. THE Frontend_Application SHALL memvalidasi bahwa base URL diakhiri tanpa trailing slash atau menangani trailing slash secara konsisten
5. WHERE base URL tidak dikonfigurasi, THE Frontend_Application SHALL menampilkan error message yang jelas saat startup

### Requirement 11: HTTP Client Configuration

**User Story:** Sebagai developer frontend, saya ingin mengkonfigurasi HTTP client dengan benar, sehingga semua request ke API memiliki header dan timeout yang sesuai.

#### Acceptance Criteria

1. THE Frontend_Application SHALL mengatur Content-Type header menjadi `application/json` untuk semua POST request
2. THE Frontend_Application SHALL mengatur Accept header menjadi `application/json` untuk semua request
3. THE Frontend_Application SHALL mengatur timeout untuk request minimal 30 detik untuk menghindari timeout prematur
4. THE Frontend_Application SHALL mengimplementasikan request interceptor untuk menambahkan Authorization header secara otomatis jika token tersedia
5. THE Frontend_Application SHALL mengimplementasikan response interceptor untuk menangani error 401 secara global
6. THE Frontend_Application SHALL mengimplementasikan retry logic untuk network error dengan maksimal 3 kali percobaan
7. WHERE request memerlukan autentikasi, THE Frontend_Application SHALL menambahkan Authorization header dengan format `Bearer {token}`

### Requirement 12: User Data Persistence

**User Story:** Sebagai developer frontend, saya ingin menyimpan data user secara persisten, sehingga aplikasi tidak perlu memanggil API /me setiap kali komponen di-render.

#### Acceptance Criteria

1. WHEN login atau registrasi berhasil, THE Frontend_Application SHALL menyimpan data user di state management (Redux, Vuex, Context API, dll)
2. WHEN aplikasi pertama kali dimuat dan token tersedia, THE Frontend_Application SHALL memanggil endpoint `/api/auth/me` untuk mendapatkan data user terbaru
3. WHEN data user berhasil diambil, THE Frontend_Application SHALL menyimpan data tersebut di state management
4. THE Frontend_Application SHALL menggunakan data user dari state management untuk menampilkan informasi di UI
5. WHEN logout, THE Frontend_Application SHALL menghapus data user dari state management
6. THE Frontend_Application SHALL memperbarui data user di state management jika ada perubahan dari API
7. WHERE aplikasi menggunakan localStorage untuk persistence, THE Frontend_Application SHALL menyimpan data user di localStorage dengan key yang jelas (misal: `user_data`)

### Requirement 13: Loading dan UI States

**User Story:** Sebagai developer frontend, saya ingin mengelola loading dan UI states dengan baik, sehingga pengguna mendapatkan feedback visual yang jelas.

#### Acceptance Criteria

1. WHEN request sedang diproses, THE Frontend_Application SHALL menampilkan loading indicator (spinner, progress bar, skeleton screen)
2. WHEN request sedang diproses, THE Frontend_Application SHALL menonaktifkan form input dan tombol submit
3. WHEN request berhasil, THE Frontend_Application SHALL menghilangkan loading indicator dan menampilkan success message atau redirect
4. WHEN request gagal, THE Frontend_Application SHALL menghilangkan loading indicator dan menampilkan error message
5. THE Frontend_Application SHALL menggunakan loading state yang berbeda untuk operasi yang berbeda (login loading, logout loading, fetch profile loading)
6. THE Frontend_Application SHALL menampilkan loading indicator maksimal 30 detik sebelum menampilkan timeout error
7. WHERE multiple request dilakukan bersamaan, THE Frontend_Application SHALL menampilkan loading indicator hingga semua request selesai

### Requirement 14: Dokumentasi Integrasi untuk Developer

**User Story:** Sebagai developer frontend, saya ingin memiliki dokumentasi integrasi yang lengkap, sehingga dapat mengimplementasikan fitur dengan cepat dan benar.

#### Acceptance Criteria

1. THE dokumentasi SHALL menyediakan contoh request dan response untuk setiap endpoint
2. THE dokumentasi SHALL menyediakan contoh kode untuk setiap operasi (register, login, logout, get profile) dalam berbagai bahasa/framework (JavaScript/Fetch, Axios, React, Vue, Angular)
3. THE dokumentasi SHALL menjelaskan struktur data user yang dikembalikan oleh API
4. THE dokumentasi SHALL menjelaskan semua possible error codes dan cara menanganinya
5. THE dokumentasi SHALL menjelaskan validation rules untuk setiap field input
6. THE dokumentasi SHALL menyediakan contoh implementasi token management
7. THE dokumentasi SHALL menyediakan contoh implementasi error handling
8. THE dokumentasi SHALL menyediakan contoh implementasi HTTP client configuration dengan interceptors
9. THE dokumentasi SHALL menjelaskan Single_Session_Policy dan implikasinya
10. THE dokumentasi SHALL menyediakan troubleshooting guide untuk masalah umum (CORS, 401 errors, validation errors)
