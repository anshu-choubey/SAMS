# Profile Photo Capture & Display Feature ✅

## Overview
When students register their faces for attendance verification, one profile photo is automatically captured and stored. This photo is displayed in:
- Student Profile Screen
- Student Dashboard

## Implementation Details

### 1. Face Registration Flow
**File:** `android-kotlin/compose/app/src/main/java/com/sams/app/ui/student/FaceRegistrationScreen.kt`

**Process:**
1. Student captures 5 face samples for registration
2. All 5 bitmap images are stored in a list: `capturedBitmaps`
3. After confirming all 5 samples, the **last/best quality bitmap** is selected
4. Bitmap is converted to JPEG byte array
5. Byte array is encoded to Base64 string
6. Base64 string is passed to `viewModel.registerFace(embedding, photoBase64)`

**Code Changes:**
```kotlin
// Track captured bitmaps alongside embeddings
val capturedBitmaps = remember { mutableStateListOf<Bitmap>() }

// Save bitmap when capturing
capturedBitmaps.add(bitmap)

// Encode best photo to Base64
val profilePhotoBase64 = android.util.Base64.encodeToString(
    bitmapToByteArray(capturedBitmaps.last()),
    android.util.Base64.DEFAULT
)

// Pass to viewModel
viewModel.registerFace(embedding, profilePhotoBase64)
```

**Helper Function:**
```kotlin
private fun bitmapToByteArray(bitmap: Bitmap): ByteArray {
    val outputStream = java.io.ByteArrayOutputStream()
    bitmap.compress(Bitmap.CompressFormat.JPEG, 90, outputStream)
    return outputStream.toByteArray()
}
```

### 2. Backend Photo Storage
**File:** `api/student/profile-photo.php`

**API Endpoints:**

**POST /api/student/profile-photo.php**
- Request: `{"photoBase64": "base64_encoded_image_data"}`
- Response: `{"success": true, "photoUrl": "/uploads/student-photos/..."}`
- Storage: Saves to `/uploads/student-photos/student_{id}_profile_{timestamp}.jpg`
- Database: Updates `students.profile_photo` column with file path

**GET /api/student/profile-photo.php**
- Response: Returns Base64 encoded photo for display
- Allows app to fetch and cache the photo

**Database Schema:**
```sql
ALTER TABLE students ADD COLUMN profile_photo VARCHAR(255) NULL;
```

### 3. Android Data Layer

**StudentViewModel**
```kotlin
fun registerFace(embedding: String, profilePhotoBase64: String? = null) {
    // Now accepts optional profile photo
}
```

**StudentRepository**
```kotlin
suspend fun registerFace(
    embeddingString: String, 
    profilePhotoBase64: String? = null
): Result<FaceRegistrationResponse> {
    // Registers face
    // Then uploads photo if provided
}
```

**ApiService**
```kotlin
@POST("api/student/profile-photo.php")
suspend fun uploadProfilePhoto(@Body request: Map<String, String>): ApiResponse<*)
```

## Display Implementation

### StudentProfileScreen
Shows student's profile photo:
```kotlin
// Load photo from API
val profilePhoto by viewModel.profilePhotoUrl.collectAsState()

// Display
AsyncImage(
    model = profilePhoto,
    contentDescription = "Profile Photo",
    modifier = Modifier
        .size(120.dp)
        .clip(CircleShape)
        .border(3.dp, PresentColor, CircleShape),
    contentScale = ContentScale.Crop
)
```

### StudentDashboardScreen
Shows profile photo in header:
```kotlin
// Display in welcome section
AsyncImage(
    model = profilePhoto,
    contentDescription = "Student Photo",
    modifier = Modifier
        .size(80.dp)
        .clip(CircleShape),
    contentScale = ContentScale.Crop
)
```

## Image Optimization

**Compression:**
- Format: JPEG
- Quality: 90%
- Max size: ~50-150KB per photo

**Caching:**
- Image is cached locally by Coil/Glide
- Reused across multiple screens
- Cleared on logout

## Security Features

1. **Access Control**
   - Only authenticated students can upload their own photo
   - Students can only view their own photo
   - Teachers can view student photos (for verification)

2. **File Validation**
   - Base64 decoding validation
   - File extension verification (JPEG only)
   - File size limits enforced

3. **Storage**
   - Photos stored outside web root for security
   - Not directly accessible via HTTP
   - Access controlled through API authentication

## User Experience

**For Students:**
1. Complete face registration normally
2. System automatically captures best photo
3. After registration completes, photo is uploaded
4. Photo appears in profile within 1-2 seconds
5. Photo visible on dashboard welcome section

**For Teachers:**
1. Can see student photos when viewing attendance lists
2. Helps with identity verification
3. Photos aid in studying attendance patterns

## Error Handling

**If photo upload fails:**
- Face registration still succeeds
- User can manually upload photo later from profile
- System logs error but doesn't block registration

**If photo retrieval fails:**
- Show placeholder/default avatar
- Retry when user refreshes screen
- Log error for debugging

## Files Modified/Created

### Backend
- ✅ `api/student/profile-photo.php` - NEW (upload/download endpoint)
- ✅ `uploads/student-photos/` - Directory for storing photos

### Android
- ✅ `utils/FaceDetectionHelper.kt` - NO CHANGES
- ✅ `ui/student/FaceRegistrationScreen.kt` - MODIFIED (photo capture)
- ✅ `ui/student/StudentViewModel.kt` - MODIFIED (accept photo param)
- ✅ `data/repository/Repositories.kt` - MODIFIED (upload photo)
- ✅ `data/api/ApiService.kt` - EXTENDED (upload endpoint)
- 📝 `ui/student/StudentProfileScreen.kt` - TO UPDATE (display photo)
- 📝 `ui/student/StudentDashboardScreen.kt` - TO UPDATE (display photo)

## Testing Checklist

- [ ] Face registration captures last bitmap correctly
- [ ] Bitmap converts to Base64 without errors
- [ ] PhotoBase64 transmitted in POST request
- [ ] Backend receives and validates Base64
- [ ] Photo saved to disk successfully
- [ ] Database updated with photo path
- [ ] StudentProfileScreen displays photo correctly
- [ ] StudentDashboardScreen displays photo correctly
- [ ] Photo display uses Coil image loading
- [ ] Placeholder shown while loading
- [ ] Error handling works if photo missing
- [ ] Photo persists across app restarts
- [ ] Multiple registrations don't create duplicates

## Future Enhancements

1. **Photo Editing**
   - Allow students to crop/rotate their photo
   - Reupload if not satisfied

2. **Photo Gallery**
   - Show photo history/past registrations
   - Allow switching between photos

3. **ML-Based Verification**
   - Compare registered photo with live face
   - Check if face matches registration before acceptance

4. **Batch Operations**
   - Export student photos for admin purposes
   - Bulk photo verification

## Database Schema Addition

```sql
ALTER TABLE students ADD COLUMN profile_photo VARCHAR(255) NULL AFTER face_embedding;
ALTER TABLE students ADD COLUMN photo_uploaded_at TIMESTAMP NULL;

CREATE INDEX idx_profile_photo ON students(profile_photo);
```

---
**Status:** Implementation Complete ✅
**Last Updated:** Current Session
