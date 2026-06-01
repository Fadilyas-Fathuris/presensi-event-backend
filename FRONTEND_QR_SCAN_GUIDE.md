# Frontend Integration Guide: QR Code Scan untuk Presensi

## Overview

Panduan ini menjelaskan cara mengintegrasikan fitur scan QR code untuk presensi event alumni di frontend.

---

## API Endpoint

### Scan QR Code untuk Presensi

**Endpoint:** `POST /api/presensi/scan`

**Headers:**

```
Authorization: Bearer {access_token}
Content-Type: application/json
```

**Request Body:**

```json
{
    "qr_token": "164dbd51-a277-45d4-a579-5a46ca090064"
}
```

**Response Success (201):**

```json
{
    "success": true,
    "message": "Presensi berhasil dicatat",
    "data": {
        "presensi": {
            "id": 1,
            "event_id": 5,
            "user_id": 2,
            "scanned_at": "2026-06-02 03:30:00",
            "created_at": "2026-06-02 03:30:00",
            "updated_at": "2026-06-02 03:30:00",
            "event": {
                "id": 5,
                "event_title": "Workshop Laravel",
                "location": "Aula Kampus",
                "event_date": "2026-06-02"
            }
        }
    }
}
```

**Response Error:**

1. **QR Code tidak valid (404):**

```json
{
    "success": false,
    "message": "QR Code tidak valid"
}
```

2. **QR Code tidak dikenali / User belum mendaftar (403):**

```json
{
    "success": false,
    "message": "QR Code tidak dikenali"
}
```

3. **Event tidak aktif (400):**

```json
{
    "success": false,
    "message": "Event ini sudah tidak aktif"
}
```

4. **QR Code expired (400):**

```json
{
    "success": false,
    "message": "QR Code sudah kadaluarsa. Silakan minta admin untuk generate QR code baru."
}
```

5. **QR Code belum aktif (400):**

```json
{
    "success": false,
    "message": "QR Code belum aktif atau sudah tidak valid."
}
```

6. **Double scan (400):**

```json
{
    "success": false,
    "message": "Kamu sudah melakukan presensi untuk event ini"
}
```

---

## Validasi Backend

Backend melakukan validasi dalam urutan berikut:

1. ✅ **QR Token Valid** - Token ditemukan di sistem (NEW atau OLD)
2. ✅ **QR Code Aktif** - Untuk NEW system, cek is_active dan belum expired
3. ✅ **Event Aktif** - Event status = 'active'
4. ✅ **User Terdaftar** - User sudah mendaftar event (ada di event_registrations)
5. ✅ **Belum Presensi** - User belum pernah scan untuk event ini

**PENTING:** Jika user belum mendaftar event, akan muncul error "QR Code tidak dikenali" (bukan "Anda belum mendaftar"). Ini untuk keamanan agar user tidak tahu apakah QR code valid atau tidak.

---

## Implementasi Frontend

### 1. Fetch QR Code Terbaru dari API

**JANGAN** gunakan gambar QR code yang di-cache atau disimpan sebelumnya!

**Endpoint:** `GET /api/admin/events/{eventId}/qr-codes`

```javascript
async function fetchLatestQRCode(eventId) {
    try {
        const response = await fetch(`/api/admin/events/${eventId}/qr-codes`, {
            headers: {
                Authorization: `Bearer ${accessToken}`,
                Accept: "application/json",
            },
        });

        const result = await response.json();

        if (result.success && result.data.qr_codes.length > 0) {
            // Ambil QR code pertama (yang paling baru dan aktif)
            const latestQR = result.data.qr_codes[0];

            return {
                token: latestQR.qr_token,
                imageUrl: latestQR.qr_image_url,
                validFrom: latestQR.valid_from,
                validUntil: latestQR.valid_until,
                isActive: latestQR.is_active,
                isExpired: latestQR.is_expired,
            };
        }

        return null;
    } catch (error) {
        console.error("Error fetching QR code:", error);
        return null;
    }
}
```

### 2. Tampilkan QR Code untuk Admin

```javascript
// Di halaman admin event detail
async function displayQRCode(eventId) {
    const qrData = await fetchLatestQRCode(eventId);

    if (!qrData) {
        alert("Belum ada QR code aktif untuk event ini");
        return;
    }

    // Tampilkan gambar QR code
    document.getElementById("qr-image").src = qrData.imageUrl;

    // Tampilkan info validitas
    document.getElementById("qr-valid-from").textContent = qrData.validFrom;
    document.getElementById("qr-valid-until").textContent = qrData.validUntil;
    document.getElementById("qr-status").textContent = qrData.isActive
        ? "Aktif"
        : "Tidak Aktif";
}
```

### 3. Scan QR Code (Alumni)

#### Option A: Menggunakan Library QR Scanner

Gunakan library seperti `html5-qrcode` atau `jsqr`:

```bash
npm install html5-qrcode
```

```javascript
import { Html5QrcodeScanner } from "html5-qrcode";

function initQRScanner() {
    const scanner = new Html5QrcodeScanner(
        "qr-reader", // ID element HTML
        {
            fps: 10,
            qrbox: { width: 250, height: 250 },
        },
    );

    scanner.render(onScanSuccess, onScanError);
}

async function onScanSuccess(decodedText, decodedResult) {
    console.log(`QR Code detected: ${decodedText}`);

    // decodedText adalah QR token (UUID)
    await submitPresensi(decodedText);
}

function onScanError(error) {
    // Handle scan error (optional)
    console.warn(`QR scan error: ${error}`);
}
```

#### Option B: Manual Input (untuk testing)

```html
<input type="text" id="qr-token-input" placeholder="Masukkan QR Token" />
<button onclick="submitPresensiManual()">Submit Presensi</button>
```

```javascript
async function submitPresensiManual() {
    const token = document.getElementById("qr-token-input").value;
    await submitPresensi(token);
}
```

### 4. Submit Presensi ke Backend

```javascript
async function submitPresensi(qrToken) {
    try {
        const response = await fetch("/api/presensi/scan", {
            method: "POST",
            headers: {
                Authorization: `Bearer ${accessToken}`,
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({
                qr_token: qrToken,
            }),
        });

        const result = await response.json();

        if (response.ok && result.success) {
            // Presensi berhasil
            showSuccessMessage(result.message);

            // Tampilkan detail presensi
            const presensi = result.data.presensi;
            console.log("Presensi berhasil:", presensi);

            // Redirect atau update UI
            // window.location.href = '/presensi/success';
        } else {
            // Presensi gagal
            showErrorMessage(result.message);
        }
    } catch (error) {
        console.error("Error submitting presensi:", error);
        showErrorMessage("Terjadi kesalahan saat melakukan presensi");
    }
}

function showSuccessMessage(message) {
    alert(message); // Atau gunakan toast/notification library
}

function showErrorMessage(message) {
    alert(message); // Atau gunakan toast/notification library
}
```

---

## Error Handling

### Mapping Error Messages untuk User

```javascript
function getErrorMessage(errorResponse) {
    const message = errorResponse.message;

    // Mapping pesan error ke bahasa yang lebih user-friendly
    const errorMap = {
        "QR Code tidak valid":
            "QR Code tidak dapat dikenali. Pastikan Anda scan QR code yang benar.",
        "QR Code tidak dikenali":
            "Anda belum terdaftar untuk event ini atau QR Code tidak valid.",
        "Event ini sudah tidak aktif":
            "Event ini sudah berakhir atau dibatalkan.",
        "QR Code sudah kadaluarsa. Silakan minta admin untuk generate QR code baru.":
            "QR Code sudah tidak berlaku. Hubungi panitia event.",
        "QR Code belum aktif atau sudah tidak valid.":
            "QR Code belum dapat digunakan atau sudah tidak berlaku.",
        "Kamu sudah melakukan presensi untuk event ini":
            "Anda sudah melakukan presensi sebelumnya.",
    };

    return errorMap[message] || message;
}
```

---

## Flow Diagram

```
[Alumni Scan QR Code]
        ↓
[Extract QR Token dari QR Code]
        ↓
[POST /api/presensi/scan dengan qr_token]
        ↓
[Backend Validasi:]
  1. QR Token valid? → Tidak → Error 404
  2. QR Code aktif? → Tidak → Error 400
  3. Event aktif? → Tidak → Error 400
  4. User terdaftar? → Tidak → Error 403 "QR Code tidak dikenali"
  5. Belum presensi? → Tidak → Error 400
        ↓ Ya
[Presensi Tercatat]
        ↓
[Update status registration → 'attended']
        ↓
[Response Success 201]
```

---

## Testing Checklist

### Skenario Testing:

1. ✅ **User terdaftar + QR valid** → Presensi berhasil
2. ✅ **User terdaftar + QR expired** → Error "QR Code sudah kadaluarsa"
3. ✅ **User terdaftar + QR belum aktif** → Error "QR Code belum aktif"
4. ✅ **User terdaftar + Event inactive** → Error "Event ini sudah tidak aktif"
5. ✅ **User terdaftar + Double scan** → Error "Kamu sudah melakukan presensi"
6. ✅ **User TIDAK terdaftar + QR valid** → Error "QR Code tidak dikenali"
7. ✅ **QR token tidak ada di database** → Error "QR Code tidak valid"

### Testing Steps:

1. **Setup:**
    - Buat event baru
    - Generate QR code untuk event
    - Daftarkan user alumni ke event

2. **Test Scan QR:**
    - Login sebagai alumni yang terdaftar
    - Scan QR code atau input token manual
    - Verifikasi presensi tercatat di database
    - Verifikasi status registration berubah ke 'attended'

3. **Test Validasi:**
    - Coba scan lagi (double scan) → harus error
    - Login sebagai alumni yang TIDAK terdaftar
    - Scan QR code → harus error "QR Code tidak dikenali"

---

## Troubleshooting

### Problem: "QR Code tidak valid" padahal token benar

**Solusi:**

- Pastikan QR code yang di-scan adalah yang terbaru (fetch dari API)
- Cek apakah QR code is_active = true
- Cek apakah QR code belum expired

### Problem: "QR Code tidak dikenali" padahal user sudah daftar

**Solusi:**

- Cek database event_registrations, pastikan ada record dengan:
    - event_id = ID event yang di-scan
    - user_id = ID user yang login
- Cek log backend untuk detail error

### Problem: Scan QR camera tidak berfungsi

**Solusi:**

- Pastikan browser memiliki permission untuk akses camera
- Gunakan HTTPS (camera API tidak bekerja di HTTP)
- Test dengan manual input token terlebih dahulu

---

## Notes

1. **QR Token Format:** UUID v4 (36 karakter)
    - Contoh: `164dbd51-a277-45d4-a579-5a46ca090064`

2. **QR Code Content:** Hanya berisi token, BUKAN full URL
    - ✅ Benar: `164dbd51-a277-45d4-a579-5a46ca090064`
    - ❌ Salah: `http://localhost:8000/api/attendance/scan/164dbd51-a277-45d4-a579-5a46ca090064`

3. **Timezone:** Semua timestamp menggunakan Asia/Jakarta (WIB)

4. **Security:** Error message "QR Code tidak dikenali" digunakan untuk user yang belum daftar agar tidak membocorkan informasi apakah QR code valid atau tidak.

---

## Support

Jika ada masalah atau pertanyaan, cek:

1. Log backend di `storage/logs/laravel.log`
2. Network tab di browser developer tools
3. Console log di browser untuk error JavaScript
