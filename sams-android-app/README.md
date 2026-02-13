# SAMS - Student Attendance Management System

A modern Android application for managing student attendance using face recognition and location verification.

## Features

### For Students
- 📱 **Dashboard**: View attendance statistics, today's schedule, and quick actions
- 📅 **Schedule**: Weekly class schedule with attendance status
- ✅ **Mark Attendance**: Mark attendance with face verification and location check
- 👤 **Face Registration**: Register face for attendance verification
- 📊 **Attendance History**: View complete attendance records
- 🔔 **Notifications**: Receive class and attendance notifications

### For Teachers
- 📱 **Dashboard**: View assigned subjects, today's classes, and active sessions
- 📅 **Schedule**: Weekly teaching schedule
- ▶️ **Start Class**: Start attendance session with location verification  
- 👥 **Class Attendance**: Live attendance view with manual marking option
- 📊 **Attendance Reports**: View class attendance reports
- 🔔 **Notifications**: Receive attendance alerts and updates

## Tech Stack

- **Language**: Kotlin 2.0
- **UI**: Jetpack Compose with Material3
- **Architecture**: MVVM with Repository pattern
- **DI**: Hilt
- **Networking**: Retrofit + Kotlin Serialization
- **Local Storage**: DataStore Preferences
- **Camera**: CameraX
- **ML**: ML Kit Face Detection
- **Location**: Google Play Services Location
- **Push Notifications**: Firebase Cloud Messaging

## Project Structure

```
app/src/main/java/com/sams/app/
├── data/
│   ├── api/          # Retrofit API service
│   ├── models/       # Data classes
│   └── repository/   # Repositories & SessionManager
├── di/               # Hilt DI modules
├── service/          # Firebase messaging service
└── ui/
    ├── auth/         # Login screen
    ├── components/   # Reusable UI components
    ├── navigation/   # Navigation setup
    ├── notifications/# Notifications screen
    ├── student/      # Student screens
    ├── teacher/      # Teacher screens
    └── theme/        # Material3 theme
```

## Setup

1. Clone the repository
2. Add your `google-services.json` from Firebase Console
3. Update the API base URL in `NetworkModule.kt`
4. Build and run the app

## Configuration

- **API Base URL**: Update in `di/NetworkModule.kt`
- **Firebase**: Add `google-services.json` to `app/` folder
- **Location Radius**: Configurable in attendance marking

## Requirements

- Android 8.0 (API 26) or higher
- Camera permission for face registration
- Location permission for attendance marking
- Internet connection

## Building

```bash
./gradlew assembleDebug
```

## License

This project is part of the SAMS backend system.
