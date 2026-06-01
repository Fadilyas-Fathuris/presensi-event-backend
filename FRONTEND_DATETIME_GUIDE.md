# Frontend DateTime Handling Guide

## Problem

QR code preview menampilkan tanggal yang salah (1 hari lebih awal) karena timezone mismatch antara frontend dan backend.

**Example**:

- Frontend input: 02 Juni 2026, 03:00 WIB
- Backend saved: 01 Juni 2025, 20:00 UTC (7 jam lebih awal)
- Display: 01 Juni 2025, 20:00 (WRONG!)

---

## Root Cause

Frontend mengirim datetime dalam format UTC atau tanpa timezone info, sedangkan backend expect datetime dalam timezone lokal (Asia/Jakarta).

---

## Solution

### Option 1: Send DateTime in Local Timezone (RECOMMENDED)

Frontend harus mengirim datetime dalam format yang sudah include timezone lokal.

#### JavaScript Example

```javascript
// ❌ WRONG - Sends in UTC
const datetime = new Date("2026-06-02T03:00:00").toISOString();
// Result: "2026-06-01T20:00:00.000Z" (UTC, 7 hours behind)

// ✅ CORRECT - Send in local timezone format
const datetime = "2026-06-02 03:00:00"; // No timezone = treated as local
// Or with explicit timezone:
const datetime = "2026-06-02T03:00:00+07:00"; // WIB (UTC+7)
```

#### React Example (DateTimePicker)

```javascript
import { DateTimePicker } from "@mui/x-date-pickers";
import { format } from "date-fns";

const QRGenerateForm = () => {
    const [validFrom, setValidFrom] = useState(new Date());

    const handleSubmit = async () => {
        // Format datetime as local time string (no timezone conversion)
        const formattedDateTime = format(validFrom, "yyyy-MM-dd HH:mm:ss");

        const response = await fetch("/api/admin/events/1/qr/generate", {
            method: "POST",
            headers: {
                Authorization: `Bearer ${token}`,
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                valid_from: formattedDateTime, // "2026-06-02 03:00:00"
                timeout_minutes: 60,
            }),
        });
    };

    return (
        <DateTimePicker
            label="Mulai Berlaku"
            value={validFrom}
            onChange={setValidFrom}
            format="dd/MM/yyyy HH:mm"
        />
    );
};
```

#### Vue.js Example

```vue
<template>
    <div>
        <input
            type="datetime-local"
            v-model="validFrom"
            @change="handleDateChange"
        />
        <button @click="generateQR">Generate QR</button>
    </div>
</template>

<script>
export default {
    data() {
        return {
            validFrom: "",
            formattedDateTime: "",
        };
    },
    methods: {
        handleDateChange() {
            // Convert datetime-local value to backend format
            // Input: "2026-06-02T03:00" (local time)
            // Output: "2026-06-02 03:00:00"
            this.formattedDateTime = this.validFrom.replace("T", " ") + ":00";
        },
        async generateQR() {
            const response = await fetch("/api/admin/events/1/qr/generate", {
                method: "POST",
                headers: {
                    Authorization: `Bearer ${this.$store.state.token}`,
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    valid_from: this.formattedDateTime,
                    timeout_minutes: 60,
                }),
            });
        },
    },
};
</script>
```

---

### Option 2: Backend Handles Timezone Conversion (IMPLEMENTED)

Backend sudah di-update untuk handle timezone conversion:

- Jika frontend kirim dalam UTC (format ISO dengan Z), backend convert ke WIB
- Jika frontend kirim tanpa timezone, backend treat sebagai WIB

**Backend Logic**:

```php
// Parse datetime from frontend
$validFrom = \Carbon\Carbon::parse($validated['valid_from']);

// If parsed as UTC, convert to app timezone (Asia/Jakarta)
if ($validFrom->timezone->getName() === 'UTC' && config('app.timezone') !== 'UTC') {
    $validFrom->setTimezone(config('app.timezone'));
}
```

---

## DateTime Format Reference

### Accepted Formats

| Format               | Example                     | Timezone | Recommended              |
| -------------------- | --------------------------- | -------- | ------------------------ |
| ISO 8601 with Z      | `2026-06-02T03:00:00.000Z`  | UTC      | ❌ No (needs conversion) |
| ISO 8601 with offset | `2026-06-02T03:00:00+07:00` | WIB      | ✅ Yes                   |
| SQL DateTime         | `2026-06-02 03:00:00`       | Local    | ✅ Yes (best)            |
| ISO 8601 no timezone | `2026-06-02T03:00:00`       | Local    | ✅ Yes                   |

### Format Conversion Examples

```javascript
// Get current datetime in different formats

// 1. SQL DateTime format (RECOMMENDED)
const sqlFormat = new Date().toISOString().slice(0, 19).replace("T", " ");
// "2026-06-02 03:00:00"

// 2. ISO with timezone offset
const isoWithOffset =
    new Date()
        .toLocaleString("sv-SE", {
            timeZone: "Asia/Jakarta",
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        })
        .replace(" ", "T") + "+07:00";
// "2026-06-02T03:00:00+07:00"

// 3. Using date-fns library
import { format } from "date-fns";
const formatted = format(new Date(), "yyyy-MM-dd HH:mm:ss");
// "2026-06-02 03:00:00"

// 4. Using moment.js (if still using)
import moment from "moment";
const formatted = moment().format("YYYY-MM-DD HH:mm:ss");
// "2026-06-02 03:00:00"
```

---

## Display DateTime from Backend

When displaying datetime from backend response, convert to user's local timezone:

```javascript
// Backend returns: "2026-06-02T03:00:00.000000Z" or "2026-06-02 03:00:00"

// Option 1: Display as-is (if backend already in local timezone)
const displayTime = response.data.qr_code.valid_from;

// Option 2: Format for better display
import { format, parseISO } from "date-fns";
import { id } from "date-fns/locale";

const displayTime = format(
    parseISO(response.data.qr_code.valid_from),
    "dd MMMM yyyy, HH:mm",
    { locale: id },
);
// "02 Juni 2026, 03:00"

// Option 3: Relative time
import { formatDistanceToNow } from "date-fns";
import { id } from "date-fns/locale";

const relativeTime = formatDistanceToNow(
    parseISO(response.data.qr_code.valid_from),
    { addSuffix: true, locale: id },
);
// "dalam 2 jam"
```

---

## Testing Checklist

### Frontend

- [ ] DateTimePicker shows current local time
- [ ] Selected datetime sent in correct format
- [ ] No timezone conversion to UTC before sending
- [ ] Preview shows correct datetime after generation

### Backend

- [ ] Receives datetime in expected format
- [ ] Stores datetime correctly in database
- [ ] Returns datetime in consistent format
- [ ] QR code validation uses correct timezone

### Integration

- [ ] Generate QR at 03:00 WIB → Saved as 03:00 WIB ✓
- [ ] Preview shows 03:00 WIB (not 20:00 UTC) ✓
- [ ] QR valid from 03:00 WIB (not 10:00 WIB) ✓
- [ ] Expired at correct time (03:00 + timeout) ✓

---

## Common Mistakes

### ❌ Mistake 1: Using toISOString()

```javascript
// WRONG - Converts to UTC
const datetime = new Date("2026-06-02 03:00").toISOString();
// Result: "2026-06-01T20:00:00.000Z" (7 hours behind!)
```

### ❌ Mistake 2: Not handling timezone in DatePicker

```javascript
// WRONG - DatePicker returns UTC
<DatePicker
    value={date}
    onChange={(newDate) => {
        // newDate is in UTC!
        setDate(newDate.toISOString()); // Wrong!
    }}
/>
```

### ✅ Correct Approach

```javascript
// CORRECT - Keep in local timezone
<DatePicker
    value={date}
    onChange={(newDate) => {
        // Format as local time string
        const formatted = format(newDate, "yyyy-MM-dd HH:mm:ss");
        setDate(formatted); // Correct!
    }}
/>
```

---

## Debugging

### Check what frontend sends

```javascript
console.log("Sending datetime:", {
    input: validFrom,
    formatted: formattedDateTime,
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
});
```

### Check backend logs

```bash
tail -f storage/logs/laravel.log | grep "QR Generate"
```

Backend will log:

```
QR Generate - Input valid_from: {"input":"2026-06-02 03:00:00","parsed_timezone":"Asia/Jakarta","parsed_datetime":"2026-06-02 03:00:00","app_timezone":"Asia/Jakarta"}
```

---

## Summary

**Best Practice**:

1. ✅ Frontend: Send datetime in SQL format (`YYYY-MM-DD HH:mm:ss`) without timezone
2. ✅ Backend: Parse as local timezone (Asia/Jakarta)
3. ✅ Display: Format for user-friendly display
4. ✅ Avoid: Using `.toISOString()` or UTC conversion

**Format to Use**:

```
2026-06-02 03:00:00  ← Use this format!
```

**NOT**:

```
2026-06-01T20:00:00.000Z  ← Don't use this!
```
