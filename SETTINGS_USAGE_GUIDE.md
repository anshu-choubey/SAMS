# Using Settings Methods in Android Compose App

## Overview
The compose app now has three dedicated methods to fetch and use individual settings from the backend:
- `loadAttendanceSettings()`
- `loadSystemSettings()` 
- `loadAppInfo()`

Plus the main method:
- `loadAppSettings()` - Fetches all settings at once (called in init)

## Implementation Details

### 1. API Service Methods
```kotlin
// Get all settings at once
@GET("api/public/settings.php")
suspend fun getAppSettings(@Query("type") type: String = "all"): ApiResponse<AppSettingsConfig>

// Get attendance settings only
@GET("api/public/settings.php")
suspend fun getAttendanceSettings(): ApiResponse<AttendanceSettings>

// Get system settings only
@GET("api/public/settings.php")
suspend fun getSystemSettings(): ApiResponse<SystemSettings>

// Get app info only
@GET("api/public/settings.php")
suspend fun getAppInfo(): ApiResponse<AppInfo>
```

### 2. Settings Repository Methods
```kotlin
suspend fun getAppSettings(forceRefresh: Boolean = false): Result<AppSettingsConfig>
suspend fun getAttendanceSettings(): Result<AttendanceSettings>
suspend fun getSystemSettings(): Result<SystemSettings>
suspend fun getAppInfo(): Result<AppInfo>

fun getCachedSettings(): AppSettingsConfig? = cachedSettings
fun getCachedAttendanceSettings(): AttendanceSettings? = cachedSettings?.attendance
fun getCachedSystemSettings(): SystemSettings? = cachedSettings?.system
fun getCachedAppInfo(): AppInfo? = cachedSettings?.appInfo
fun clearCache()
```

### 3. StudentViewModel Methods
```kotlin
fun loadAppSettings()                    // Auto-called in init()
fun refreshSettings()                    // Force refresh from API
fun loadAttendanceSettings()             // Load attendance settings
fun loadSystemSettings()                 // Load system settings
fun loadAppInfo()                        // Load app info
```

## Usage Examples

### Example 1: Use in MarkAttendanceScreen
```kotlin
@Composable
fun MarkAttendanceScreen(viewModel: StudentViewModel = hiltViewModel()) {
    // Settings are auto-loaded in init
    val gpsRadius = viewModel.getGpsRadius()  // 50 meters default
    val faceThreshold = viewModel.getFaceConfidenceThreshold()  // 75% default
    val isGpsEnabled = viewModel.isGpsVerificationEnabled()  // true default
    
    // Or get all settings at once
    val appSettings by viewModel.appSettings.collectAsState()
    
    LaunchedEffect(Unit) {
        // Optionally refresh specific settings
        viewModel.loadAttendanceSettings()  // Fetch fresh attendance settings
    }
    
    // Use in your UI
    if (isGpsEnabled) {
        // Show GPS requirement message
        Text("GPS verification required within ${gpsRadius}m")
    }
    
    if (viewModel.isFaceVerificationEnabled()) {
        // Show face verification UI
        Text("Face confidence threshold: ${faceThreshold}%")
    }
}
```

### Example 2: Use in StudentDashboardScreen
```kotlin
@Composable
fun StudentDashboardScreen(viewModel: StudentViewModel = hiltViewModel()) {
    val appSettings by viewModel.appSettings.collectAsState()
    val settingsLoaded by viewModel.settingsLoaded.collectAsState()
    
    LaunchedEffect(Unit) {
        if (!settingsLoaded) {
            // Settings will auto-load, but you can also manually load
            viewModel.loadAppInfo()  // Get institution name, logo, etc.
            viewModel.loadSystemSettings()  // Get academic year, semester
        }
    }
    
    // Show institution info
    appSettings?.appInfo?.let { appInfo ->
        Text("Institution: ${appInfo.institution}")
        Text("Academic Year: ${appSettings?.system?.academicYear}")
        Text("Current Semester: ${appSettings?.system?.currentSemester}")
    }
    
    // Show attendance info
    appSettings?.attendance?.let { attendance ->
        Text("Minimum Attendance: ${attendance.minimumThreshold}%")
        Text("Late Threshold: ${attendance.lateThresholdMinutes} minutes")
    }
}
```

### Example 3: Manual Refresh with Error Handling
```kotlin
@Composable
fun SettingsDebugScreen(viewModel: StudentViewModel = hiltViewModel()) {
    val appSettings by viewModel.appSettings.collectAsState()
    
    Column {
        Button(onClick = { viewModel.loadAttendanceSettings() }) {
            Text("Refresh Attendance Settings")
        }
        
        Button(onClick = { viewModel.loadSystemSettings() }) {
            Text("Refresh System Settings")
        }
        
        Button(onClick = { viewModel.loadAppInfo() }) {
            Text("Refresh App Info")
        }
        
        Button(onClick = { viewModel.refreshSettings() }) {
            Text("Refresh All Settings")
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Display current settings
        appSettings?.let {
            Text("GPS Radius: ${it.attendance.gpsRadius}m")
            Text("Face Threshold: ${it.attendance.faceConfidenceThreshold}%")
            Text("Session Timeout: ${it.system.sessionTimeout}s")
            Text("Institution: ${it.appInfo.institution}")
        }
    }
}
```

### Example 4: Conditional UI Based on Settings
```kotlin
@Composable
fun AttendanceVerificationScreen(viewModel: StudentViewModel = hiltViewModel()) {
    val appSettings by viewModel.appSettings.collectAsState()
    
    appSettings?.attendance?.let { attendance ->
        Column {
            // Only show GPS verification if enabled
            if (attendance.enableGpsVerification) {
                GPSVerificationCard(radius = attendance.gpsRadius)
            }
            
            // Only show face verification if enabled
            if (attendance.enableFaceVerification) {
                FaceVerificationCard(threshold = attendance.faceConfidenceThreshold)
            }
            
            // Show late attendance option if allowed
            if (attendance.allowLateAttendance) {
                Text("Late attendance allowed within ${attendance.lateThresholdMinutes} minutes")
            }
        }
    }
}
```

### Example 5: In TeacherViewModel/Screen
```kotlin
@Composable
fun TeacherDashboardScreen(viewModel: TeacherViewModel = hiltViewModel()) {
    // Assuming TeacherViewModel also injects SettingsRepository
    val appSettings by viewModel.appSettings.collectAsState()
    
    appSettings?.system?.let { system ->
        Text("Academic Year: ${system.academicYear}")
        Text("Current Semester: ${system.currentSemester}")
        
        if (system.maintenanceMode) {
            AlertDialog(
                onDismissRequest = {},
                title = { Text("Maintenance Mode") },
                text = { Text("System is under maintenance") },
                confirmButton = {}
            )
        }
    }
}
```

## Data Models

### AppSettingsConfig
```kotlin
data class AppSettingsConfig(
    val attendance: AttendanceSettings,
    val system: SystemSettings,
    val appInfo: AppInfo
)
```

### AttendanceSettings
```kotlin
data class AttendanceSettings(
    val gpsRadius: Int,                          // 50 meters
    val faceConfidenceThreshold: Int,            // 75%
    val enableFaceVerification: Boolean,         // true
    val enableGpsVerification: Boolean,          // true
    val minimumThreshold: Int,                   // 75%
    val allowLateAttendance: Boolean,            // true
    val lateThresholdMinutes: Int                // 15 minutes
)
```

### SystemSettings
```kotlin
data class SystemSettings(
    val sessionTimeout: Int,                     // 3600 seconds
    val academicYear: String,                    // "2025-26"
    val currentSemester: Int,                    // 2
    val maintenanceMode: Boolean                 // false
)
```

### AppInfo
```kotlin
data class AppInfo(
    val name: String,                            // "SAMS"
    val version: String,                         // "1.0.0"
    val institution: String,                     // "Your Institution"
    val logoUrl: String,                         // ""
    val supportEmail: String                     // "support@sams.edu"
)
```

## Caching Strategy

The SettingsRepository implements smart caching:
- **Cache Duration**: 24 hours
- **Auto Refresh**: Fetches from API when cache expires
- **Fallback**: Uses cached data if API fetch fails
- **Manual Refresh**: Call `viewModel.refreshSettings(forceRefresh=true)`

## Benefits of Individual Fetch Methods

1. **Targeted Refresh**: Load only what you need
2. **Flexibility**: Update specific settings without re-fetching everything
3. **Performance**: Reduce unnecessary data transfer
4. **Separation of Concerns**: Different settings for different screens
5. **Error Isolation**: Failure in one setting doesn't affect others

## When to Use Each Method

| Method | Use Case |
|--------|----------|
| `loadAppSettings()` | Initial app load (auto-called in init) |
| `loadAttendanceSettings()` | Mark attendance screen, need fresh thresholds |
| `loadSystemSettings()` | Dashboard, need academic year/semester |
| `loadAppInfo()` | App settings, need institution info |
| `refreshSettings()` | Force all settings to re-fetch from API |

## Best Practices

1. **Auto-load in init**: Settings are automatically loaded when ViewModel is created
2. **Use state flows**: Observe settings changes with `collectAsState()`
3. **Provide defaults**: All getter methods have sensible defaults
4. **Check settingsLoaded**: Wait for `settingsLoaded` StateFlow before relying on settings
5. **Handle offline**: Use cached settings as fallback when offline
6. **Refresh thoughtfully**: Don't refresh too frequently, let caching do its job

## Example: Complete MarkAttendanceScreen

```kotlin
@Composable
fun MarkAttendanceScreen(
    viewModel: StudentViewModel = hiltViewModel(),
    scheduleId: Int = 1
) {
    val appSettings by viewModel.appSettings.collectAsState()
    val settingsLoaded by viewModel.settingsLoaded.collectAsState()
    val attendanceState by viewModel.attendanceState.collectAsState()
    
    LaunchedEffect(Unit) {
        // Manual refresh if needed
        viewModel.loadAttendanceSettings()
    }
    
    if (!settingsLoaded) {
        CircularProgressIndicator()
        return
    }
    
    val gpsRadius = appSettings?.attendance?.gpsRadius ?: 50
    val faceThreshold = appSettings?.attendance?.faceConfidenceThreshold ?: 75
    val isGpsEnabled = appSettings?.attendance?.enableGpsVerification ?: true
    val isFaceEnabled = appSettings?.attendance?.enableFaceVerification ?: true
    
    Column(modifier = Modifier.fillMaxSize()) {
        Text("GPS Radius: ${gpsRadius}m")
        Text("Face Threshold: ${faceThreshold}%")
        
        if (isGpsEnabled) {
            // GPS Verification UI
        }
        
        if (isFaceEnabled) {
            // Face Verification UI
        }
        
        Button(onClick = {
            viewModel.markAttendance(scheduleId, latitude, longitude, confidence)
        }) {
            Text("Mark Attendance")
        }
    }
}
```

That's it! All three methods are now properly integrated and ready to use. 🎉
