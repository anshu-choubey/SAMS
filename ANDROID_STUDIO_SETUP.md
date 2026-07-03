# Android Studio Setup Guide - SAMS Compose App

## Folder Structure Fixed ✅

The `android-kotlin/compose/` folder has been reorganized to match Android Studio's expected structure:

```
android-kotlin/compose/
├── app/                              # Main Android app module
│   ├── build.gradle.kts             # App-level Gradle config
│   ├── proguard-rules.pro           # Release build rules
│   └── src/
│       ├── main/
│       │   ├── java/com/sams/app/   # All Kotlin source code
│       │   │   ├── data/            # API, Models, Repository
│       │   │   ├── di/              # Dependency injection (Hilt)
│       │   │   ├── service/         # Firebase FCM service
│       │   │   ├── ui/              # Jetpack Compose screens
│       │   │   │   ├── auth/        # Login/Auth screens
│       │   │   │   ├── student/     # Student feature screens
│       │   │   │   ├── teacher/     # Teacher feature screens
│       │   │   │   ├── common/      # Shared screens
│       │   │   │   ├── navigation/  # Navigation routes
│       │   │   │   └── theme/       # UI theming
│       │   │   └── utils/           # Helper utilities
│       │   ├── res/                 # Android resources
│       │   │   └── values/          # Strings, colors, etc
│       │   └── AndroidManifest.xml  # App manifest
│       ├── test/                    # Unit test directory
│       └── androidTest/             # Instrumentation test directory
├── gradle/                           # Gradle wrapper & versions
│   └── libs.versions.toml           # Dependency versions
├── build.gradle.kts                 # Project-level Gradle config
├── settings.gradle.kts              # Gradle settings
├── local.properties                 # Local SDK/NDK paths
├── .gitignore                       # Git ignore rules
└── README.md                        # This documentation

```

## Opening in Android Studio

### Method 1: Direct Open (Recommended)
```bash
# Open the compose directory as a project
# File → Open → Select: android-kotlin/compose
# Android Studio will auto-detect the Gradle structure
```

### Method 2: From Command Line
```bash
cd /Users/anshu/sams-backend/android-kotlin/compose
open -a "Android Studio" .
```

### Method 3: Using Gradle Wrapper
```bash
cd android-kotlin/compose
./gradlew build
./gradlew installDebug  # Install on connected device
```

## Initial Setup Steps

1. **Sync Gradle Files**
   - Android Studio should auto-sync when you open
   - If not: File → Sync Now

2. **Update SDK Version** (if prompted)
   - compileSdk: 35
   - minSdk: 26
   - targetSdk: 35

3. **Configure Local Properties**
   ```properties
   # Edit local.properties:
   sdk.dir=/Users/anshu/Library/Android/sdk
   ndk.dir=/Users/anshu/Library/Android/ndk/...
   ```

4. **Download Dependencies**
   ```bash
   ./gradlew dependencies
   ```

5. **Build the Project**
   ```bash
   ./gradlew assembleDebug
   ```

## Running on Device/Emulator

### Via Android Studio
1. Click Run button or press `Shift + F10`
2. Select target device/emulator

### Via Command Line
```bash
./gradlew installDebug
adb shell am start -n com.sams.app/com.sams.app.ui.MainActivity
```

## Troubleshooting

### Issue: "Project not recognized"
**Solution:**
- Close project
- File → Invalidate Caches
- Reopen project
- File → Sync Now

### Issue: "Kotlin not recognized"
**Solution:**
- File → Project Structure
- Verify compileSdk >= 33
- File → Sync Now

### Issue: "Firebase not configured"
**Solution:**
Add google-services.json:
```bash
# Place in: app/google-services.json
# Download from Firebase Console
```

### Issue: "ML Kit dependencies missing"
**Solution:**
Native dependencies auto-install on first build:
```bash
./gradlew build  # Takes 2-3 minutes for first build
```

## Key Configuration Files

### app/build.gradle.kts
- Android SDK configuration
- Dependencies (Retrofit, Hilt, Compose, ML Kit, Firebase, etc)
- Build flavors & signing configs

### build.gradle.kts (Project)
- Plugins for Android, Kotlin, Hilt, Google Services
- Common build configurations

### AndroidManifest.xml
```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
```

## Building Release APK

```bash
# Requires signing key configured in build.gradle.kts
./gradlew assembleRelease

# Or create AAB for Google Play
./gradlew bundleRelease

# Output location:
# app/build/outputs/apk/release/
# app/build/outputs/bundle/release/
```

## API Configuration

Update Base URL in `app/src/main/java/com/sams/app/data/api/ApiClient.kt`:

```kotlin
const val BASE_URL = "https://sams-backend-73451-bca7cff1a531.herokuapp.com/"
```

## Architecture

This is an **MVVM + Repository Pattern** app:

```
UI (Compose)
    ↓
ViewModel (State Management)
    ↓
Repository (Data Access Layer)
    ↓
API Service + Local DB
```

## Features Implemented

✅ Student Dashboard  
✅ Teacher Dashboard  
✅ Face Registration  
✅ Attendance Marking  
✅ Schedule Viewing  
✅ Notifications (FCM)  
✅ Profile Management  

## Dependencies Highlights

- **Jetpack Compose** - Modern UI toolkit
- **Hilt** - Dependency injection
- **Retrofit** - HTTP client
- **Room** - Local database
- **Firebase** - Cloud messaging
- **ML Kit** - Face detection
- **CameraX** - Camera integration  
- **Google Play Services** - Location & geofencing

## Issues or Questions?

Refer to:
1. [Android Developer Docs](https://developer.android.com/)
2. [Jetpack Compose Docs](https://developer.android.com/jetpack/compose)
3. [Hilt Documentation](https://dagger.dev/hilt/)
