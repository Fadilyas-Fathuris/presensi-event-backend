# Frontend Integration Guide - Image Upload Features

## Overview

Panduan integrasi frontend untuk fitur upload gambar yang baru ditambahkan:

- **Event Poster Upload** (Admin)
- **User Avatar Upload & Delete** (Alumni)

---

## 1. Event Poster Upload (Admin Side)

### API Endpoints

```
POST /api/admin/events          - Create event with poster
PUT /api/admin/events/{id}      - Update event with poster
```

### Request Format

**Content-Type**: `multipart/form-data`

### Form Data Fields

```javascript
const formData = new FormData();
formData.append("category_id", "1");
formData.append("event_title", "Reuni Akbar 2025");
formData.append("description", "Deskripsi event");
formData.append("location", "Aula Pesantren");
formData.append("event_date", "2025-12-01");
formData.append("start_time", "08:00");
formData.append("end_time", "17:00");
formData.append("quota", "100");
formData.append("poster", fileInput.files[0]); // File object
```

### File Validation (Frontend)

```javascript
function validatePosterFile(file) {
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ["image/jpeg", "image/jpg", "image/png", "image/webp"];

    if (file.size > maxSize) {
        throw new Error("Ukuran file maksimal 5MB");
    }

    if (!allowedTypes.includes(file.type)) {
        throw new Error("Format file harus JPG, JPEG, PNG, atau WebP");
    }

    return true;
}
```

### Example Implementation (React/Vue)

```javascript
// React Example
const handleEventSubmit = async (formData) => {
    const form = new FormData();

    // Add all form fields
    Object.keys(formData).forEach((key) => {
        if (key === "poster" && formData[key]) {
            validatePosterFile(formData[key]);
            form.append("poster", formData[key]);
        } else if (formData[key]) {
            form.append(key, formData[key]);
        }
    });

    try {
        const response = await fetch("/api/admin/events", {
            method: "POST",
            headers: {
                Authorization: `Bearer ${token}`,
                // DON'T set Content-Type for FormData
            },
            body: form,
        });

        const result = await response.json();

        if (result.success) {
            console.log("Event created:", result.data.event);
            // result.data.event.poster_url contains the image URL
        }
    } catch (error) {
        console.error("Upload failed:", error);
    }
};
```

### Response Format

```json
{
    "success": true,
    "message": "Event created successfully",
    "data": {
        "event": {
            "id":

                alt={event.event_title}
                className="event-poster"
                onError={(e) => {
                    e.target.style.display = 'none'; // Hide if image fails to load
                }}
            />
        )}
        <h3>{event.event_title}</h3>
        <p>{event.description}</p>
    </div>
);
```

---

## 2. User Avatar Upload (Alumni Side)

### API Endpoints

```
POST /api/auth/profile/avatar    - Upload avatar
DELETE /api/auth/profile/avatar  - Delete avatar
```

### Upload Avatar

**Content-Type**: `multipart/form-data`

```javascript
const uploadAvatar = async (file) => {
    // Validate file
    const maxSize = 2 * 1024 * 1024; // 2MB
    const allowedTypes = ["image/jpeg", "image/jpg", "image/png", "image/webp"];

    if (file.size > maxSize) {
        throw new Error("Ukuran file maksimal 2MB");
    }

    if (!allowedTypes.includes(file.type)) {
        throw new Error("Format file harus JPG, JPEG, PNG, atau WebP");
    }

    const formData = new FormData();
    formData.append("avatar", file);

    try {
        const response = await fetch("/api/auth/profile/avatar", {
            method: "POST",
            headers: {
                Authorization: `Bea
rer ${token}`,
            },
            body: formData,
        });

        const result = await response.json();

        if (result.success) {
            return result.data.avatar_url;
        }
    } catch (error) {
        console.error("Avatar upload failed:", error);
        throw error;
    }
};
```

### Delete Avatar

```javascript
const deleteAvatar = async () => {
    try {
        const response = await fetch('/api/auth/profile/avatar', {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/jso
n'
            }
        });

        const result = await response.json();

        if (result.success) {
            console.log('Avatar deleted successfully');
            return true;
        }
    } catch (error) {
        console.error('Avatar deletion failed:', error);
        throw error;
    }
};
```

### Avatar Component Example

```javascript
const AvatarUpload = ({ user, onAvatarChange }) => {
    const [uploading, setUploading] = useState(false);
    const fileInputRef = useRef(null);

    const handleFileSelect = async (event) => {
        const file = event.target.files[0];
        if (!file) return;

        setUploading(true);
        try {
            const avatarUrl = await uploadAvatar(file);
            onAvatarChange(avatarUrl);
        } catch (error) {
            alert(error.message);
        } finally {
            setUploading(false);
        }
    };

    const handleDeleteAvatar = async () => {
        if (confirm("Hapus foto profil?")) {
            try {
                await deleteAvatar();
                onAvatarChange(null);
            } catch (error) {
                alert("Gagal menghapus foto profil");
            }
        }
    };

    return (
        <div className="avatar-upload">
            <div className="avatar-preview">
                {user.avatar_url ? (
                    <img
                        src={user.avatar_url}
                        alt="Avatar"
                        className="avatar-image"
                    />
                ) : (
                    <div className="avatar-placeholder">
                        {user.first_name?.[0]}
                        {user.last_name?.[0]}
                    </div>
                )}
            </div>

            <div className="avatar-actions">
                <input
                    type="file"
                    ref={fileInputRef}
                    onChange={handleFileSelect}
                    accept="image/jpeg,image/jpg,image/png,image/webp"
                    style={{ display: "none" }}
                />

                <button
                    onClick={() => fileInputRef.current?.click()}
                    disabled={uploading}
                >
                    {uploading ? "Uploading..." : "Upload Foto"}
                </button>

                {user.avatar_url && (
                    <button onClick={handleDeleteAvatar} className="delete-btn">
                        Hapus Foto
                    </button>
                )}
            </div>
        </div>
    );
};
```

---

## 3. Alumni - Viewing Event Posters

Event posters otomatis muncul di response API yang sudah ada:

### Get Events (Alumni)

```
GET /api/
events
GET /api/events/{id}
```

### Response includes poster_url

```json
{
    "success": true,
    "data": {
        "events": [
            {
                "id": 1,
                "event_title": "Reuni Akbar 2025",
                "poster_url": "http://localhost:8000/storage/event-posters/abc123.jpg",
                "description": "...",
                "location": "...",
                "event_date": "2025-12-01"
            }
        ]
    }
}
```

### Display in Alumni Event List

```javascript
const AlumniEventList = ({ events }) => (
    <div className="events-grid">
        {events.map((event) => (
            <div key={event.id} className="event-card">
                {event.poster_url && (
                    <div className="event-poster-container">
                        <img
                            src={event.poster_url}
                            alt={event.event_title}
                            className="event-poster"
                            loading="lazy"
                        />
                    </div>
                )}
                <div className="event-content">
                    <h3>{event.event_title}</h3>
                    <p className="event-date">{event.event_date}</p>
                    <p className="event-location">{event.location}</p>
                    <p className="event-description">{event.description}</p>
                </div>
            </div>
        ))}
    </div>
);
```

---

## 4. Error Handling

### Common Error Responses

```json
// File too large
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "poster": ["The poster field must not be greater than 5120 kilobytes."]
    }
}

// Invalid file type
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "avatar": ["The avatar field must be a file of type: jpg, jpeg, png, webp."]
    }
}

// No avatar to delete
{
    "success": false,
    "message": "No avatar to delete"
}
```

### Frontend Error Handling

```javascript
const handleApiError = (response) => {
    if (response.errors) {
        // Validation errors
        const errorMessages = Object.values(response.errors).flat();
        throw new Error(errorMessages.join(', '));
    } else {
        // General error
        throw new Error(response.message || 'Terjadi kesalahan
');
    }
};
```

---

## 5. CSS Styling Suggestions

```css
/* Event Poster */
.event-poster {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 12px;
}

.event-poster-container {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
    background: #f5f5f5;
}

/* Avatar */
.avatar-image {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e0e0e0;
}

.avatar-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #007bff;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 24px;
}

.avatar-upload {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.avatar-actions {
    display: flex;
    gap: 8px;
}
```

---

## 6. Important Notes

1. **Content-Type**: Jangan set `Content-Type: application/json` untuk FormData
2. **File Validation**: Lakukan validasi di frontend sebelum upload
3. **Loading States**: Tampilkan loading indicator saat upload
4. **Error Handli
   ng**: Handle semua kemungkinan error dengan user-friendly messages
5. **Image Optimization**: Pertimbangkan resize gambar di frontend sebelum upload untuk performa
6. **Lazy Loading**: Gunakan lazy loading untuk poster di event list
7. **Fallback**: Sediakan placeholder jika gambar gagal load

---

## 7. Testing Checklist

### Admin - Event Poster

- [ ] Upload poster saat create event
- [ ] Upload poster saat update event
- [ ] Replace poster yang sudah ada
- [ ] Validasi ukuran file (max 5MB)
- [ ] Validasi format file
- [ ] Preview poster sebelum su
      bmit
- [ ] Display poster di event list
- [ ] Display poster di event detail

### Alumni - Avatar

- [ ] Upload avatar pertama kali
- [ ] Replace avatar yang sudah ada
- [ ] Delete avatar
- [ ] Validasi ukuran file (max 2MB)
- [ ] Validasi format file
- [ ] Display avatar di profile
- [ ] Fallback ke initial jika tidak ada avatar

### Alumni - View Event Posters

- [ ] Poster muncul di event list
- [ ] Poster muncul di event detail
- [ ] Lazy loading berfungsi
- [ ] Fallback jika poster tidak ada
- [ ] Responsive di mobile

---

Dengan panduan ini, frontend developer dapat mengintegrasikan fitur image upload dengan mudah dan konsisten.
