# SAMS - Smart Attendance Management System

A full-featured Jetpack Compose Android application for managing student attendance using face recognition and GPS verification.

## Features

### Student Features
- 📱 Dashboard with attendance statistics
- 📅 View weekly class schedule
- 👤 Face registration for biometric verification
- ✅ Mark attendance with face + GPS verification
- 📊 View attendance history
- 🔔 Push notifications for class sessions

### Teacher Features
- 📱 Dashboard with class overview
- 📅 View teaching schedule
- 🎯 Start/End attendance sessions
- 📋 View real-time class attendance
- ✏️ Manual attendance correction
- 🔔 Push notifications

## Tech Stack

- **UI**: Jetpack Compose with Material3
- **Architecture**: MVVM with Repository pattern
- **DI**: Hilt
- **Networking**: Retrofit + Kotlin Serialization
- **Storage**: DataStore Preferences
- **Camera**: CameraX
- **Face Detection**: ML Kit Face Detection
- **Location**: Google Play Services Location
- **Push Notifications**: Firebase Cloud Messaging
- **Navigation**: Navigation Compose

## Project Structure

```
compose/
├── app/
│   ├── MainActivity.kt          # Main entry point
│   └── SAMSApplication.kt       # Hilt Application
├── data/
│   ├── api/
│   │   └── ApiService.kt        # Retrofit API definitions
│   ├── models/
│   │   └── Models.kt            # All data models
│   └── repository/
│       ├── Repositories.kt      # Repository implementations
│       └── SessionManager.kt    # Session/token management
├── di/
│   ├── NetworkModule.kt         # Hilt network dependencies
│   └── RepositoryModule.kt      # Hilt repository dependencies
├── service/
│   └── SAMSFirebaseMessagingService.kt  # FCM service
├── ui/
│   ├── auth/
│   │   ├── AuthViewModel.kt
│   │   └── LoginScreen.kt
│   ├── common/
│   │   ├── NotificationViewModel.kt
│   │   └── NotificationsScreen.kt
│   ├── navigation/
│   │   └── Navigation.kt        # Nav host & routes
│   ├── student/
│   │   ├── StudentViewModel.kt
│   │   ├── StudentDashboardScreen.kt
│   │   ├── StudentScheduleScreen.kt
│   │   ├── AttendanceHistoryScreen.kt
│   │   ├── MarkAttendanceScreen.kt
│   │   ├── FaceRegistrationScreen.kt
│   │   └── StudentProfileScreen.kt
│   ├── teacher/
│   │   ├── TeacherViewModel.kt
│   │   ├── TeacherDashboardScreen.kt
│   │   ├── TeacherScheduleScreen.kt
│   │   ├── StartClassScreen.kt
│   │   ├── ClassAttendanceScreen.kt
│   │   └── TeacherProfileScreen.kt
│   └── theme/
│       ├── Color.kt
│       ├── Theme.kt
│       └── Type.kt
├── utils/
│   ├── FaceDetectionHelper.kt   # ML Kit face detection
│   └── LocationHelper.kt        # GPS utilities
├── res/
│   └── values/
│       ├── colors.xml
│       ├── strings.xml
│       └── themes.xml
├── AndroidManifest.xml
├── build.gradle.kts             # App-level build config
└── gradle/
    └── libs.versions.toml       # Version catalog
```

## Setup Instructions

### Prerequisites
- Android Studio Hedgehog or newer
- JDK 17+
- Android SDK 34

### Configuration

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd sams-android
   ```

2. **Configure server URL**
   
   Edit `di/NetworkModule.kt` and update the BASE_URL:
   ```kotlin
   private const val BASE_URL = "http://YOUR_SERVER_IP:8000/"
   ```

3. **Setup Firebase**
   - Create a Firebase project at [Firebase Console](https://console.firebase.google.com)
   - Add an Android app with package name `com.sams.app`
   - Download `google-services.json` and place it in the `app/` directory
   - Enable Cloud Messaging in Firebase settings

4. **Build and Run**
   ```bash
   ./gradlew assembleDebug
   ```

## API Endpoints

The app expects the following backend endpoints:

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/public/login.php` | User login |
| POST | `/api/public/logout.php` | User logout |
| GET | `/api/student/dashboard.php` | Student dashboard data |
| GET | `/api/student/schedule.php` | Student weekly schedule |
| GET | `/api/student/attendance-history.php` | Attendance history |
| POST | `/api/student/mark-attendance.php` | Mark attendance |
| POST | `/api/student/register-face.php` | Register face embedding |
| GET | `/api/student/profile.php` | Get student profile |
| GET | `/api/teacher/dashboard.php` | Teacher dashboard data |
| GET | `/api/teacher/schedules.php` | Teacher schedule |
| POST | `/api/teacher/start-session.php` | Start attendance session |
| POST | `/api/teacher/end-session.php` | End attendance session |
| GET | `/api/teacher/class-attendance.php` | Get class attendance |
| POST | `/api/teacher/manual-attendance.php` | Mark manual attendance |
| GET | `/api/notifications/list.php` | Get notifications |
| POST | `/api/notifications/mark-read.php` | Mark notification read |
| POST | `/api/fcm/register.php` | Register FCM token |

## Attendance Verification

The app uses a dual verification system:

1. **Face Verification** (85% confidence threshold)
   - Student face is compared against registered face embedding
   - Uses ML Kit Face Detection for face extraction
   - Cosine similarity for face comparison

2. **GPS Verification** (50m radius)
   - Student must be within 50 meters of teacher's location
   - Uses Haversine formula for distance calculation

## Test Accounts

| Role | Email | Password |
|------|-------|----------|
| Student | choubey@gmail.com | password |
| Teacher | teacher@sams.com | password |
| Admin | admin@sams.com | password |

## License

MIT License
