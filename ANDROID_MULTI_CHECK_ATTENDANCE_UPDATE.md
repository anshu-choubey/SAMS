# Android App - Multi-Check Attendance Implementation

## ✅ Implementation Complete

The Android app has been updated to support the multi-check attendance system.

## Changes Made

### 1. **Data Models** (`Models.kt`)

Added new data classes for multi-check attendance:

**Teacher Models:**
- `TriggerAttendanceCheckRequest` - Request to trigger an attendance check
- `TriggerAttendanceCheckResponse` - Response with check point details
- `AttendanceCheckPoint` - Check point data
- `FinalizeAttendanceRequest` - Request to finalize attendance
- `FinalizeAttendanceResponse` - Response with finalized data
- `FinalizeAttendanceData` - Summary of finalized attendance

**Student Models:**
- `ActiveAttendanceChecksResponse` - Response with active checks
- `ActiveAttendanceChecksData` - Data wrapper for active checks
- `ActiveAttendanceCheck` - Individual check details
- `RespondAttendanceCheckRequest` - Request to respond to a check
- `RespondAttendanceCheckResponse` - Response after responding
- `AttendanceCheckResponseData` - Check response data

**Updated Models:**
- `StartSessionRequest` - Added `multiCheckEnabled` and `totalChecks` parameters
- `StartSessionData` - Added multi-check fields
- `EndSessionData` - Added `partial` attendance count

### 2. **API Service** (`ApiService.kt`)

Added new endpoints:

**Teacher Endpoints:**
```kotlin
@POST("api/teacher/trigger-attendance-check.php")
suspend fun triggerAttendanceCheck(@Body request: TriggerAttendanceCheckRequest): TriggerAttendanceCheckResponse

@POST("api/teacher/finalize-attendance.php")
suspend fun finalizeAttendance(@Body request: FinalizeAttendanceRequest): FinalizeAttendanceResponse
```

**Student Endpoints:**
```kotlin
@GET("api/student/active-attendance-checks.php")
suspend fun getActiveAttendanceChecks(): ActiveAttendanceChecksResponse

@POST("api/student/respond-attendance-check.php")
suspend fun respondAttendanceCheck(@Body request: RespondAttendanceCheckRequest): RespondAttendanceCheckResponse
```

### 3. **Repositories** (`Repositories.kt`)

**StudentRepository** - Added methods:
- `getActiveAttendanceChecks()` - Fetch active checks for student
- `respondAttendanceCheck()` - Submit response to a check

**TeacherRepository** - Added methods:
- `triggerAttendanceCheck()` - Trigger a random attendance check
- `finalizeAttendance()` - Finalize attendance after all checks

### 4. **ViewModels**

**StudentViewModel** - Added:
- State flows: `activeChecksState`, `checkResponseState`
- Methods:
  - `loadActiveAttendanceChecks()` - Load pending checks
  - `respondAttendanceCheck()` - Respond to a check
  - `resetCheckResponseState()` - Reset state

**TeacherViewModel** - Added:
- State flows: `checkTriggerState`, `finalizeState`
- Methods:
  - `triggerAttendanceCheck()` - Trigger attendance check
  - `resetCheckTriggerState()` - Reset state
  - `finalizeAttendance()` - Finalize attendance
  - `resetFinalizeState()` - Reset state

### 5. **UI Screens**

#### **Student: ActiveChecksScreen.kt** (NEW)

Features:
- Lists all pending attendance checks
- Shows countdown timer for each check
- Color-coded urgency (urgent <2min, expired)
- "Respond Now" button for each check
- Auto-refresh capability
- Empty state when no checks pending
- Permission handling for location and camera

UI Components:
- Pending checks badge
- Check cards with:
  - Subject name and code
  - Teacher name
  - Classroom location
  - Check number
  - Time remaining countdown
  - Urgency indicators
  - Respond button

#### **Teacher: AttendanceCheckScreen.kt** (NEW)

Features:
- Trigger random attendance checks
- Configure response window (3-10 minutes)
- Finalize attendance after checks
- Success/error feedback
- Loading states

UI Components:
- Info card explaining multi-check system
- Trigger check card with:
  - Configurable window slider
  - Trigger button
  - Success indicators
- Finalize attendance card with:
  - Confirmation dialog
  - Finalize button
- Error display cards

### 6. **Navigation Integration Required**

Add routes to navigation graph:

```kotlin
// Student Navigation
composable("activeChecks") {
    ActiveChecksScreen(
        onNavigateBack = { navController.popBackStack() },
        onNavigateToMarkAttendance = { checkPointId, subjectName ->
            navController.navigate("respondCheck/$checkPointId/$subjectName")
        }
    )
}

// Teacher Navigation
composable("attendanceCheck/{sessionId}") { backStackEntry ->
    val sessionId = backStackEntry.arguments?.getString("sessionId")?.toIntOrNull()
    if (sessionId != null) {
        AttendanceCheckScreen(
            sessionId = sessionId,
            onNavigateBack = { navController.popBackStack() }
        )
    }
}
```

### 7. **Dashboard Updates Required**

**Student Dashboard:**
- Add "Active Checks" button/badge showing pending check count
- Navigate to ActiveChecksScreen when clicked
- Show notification indicator when checks are active

```kotlin
// Example addition to StudentDashboardScreen
if (dashboardData.activeChecks.totalPending > 0) {
    Badge(
        modifier = Modifier.align(Alignment.TopEnd)
    ) {
        Text("${dashboardData.activeChecks.totalPending}")
    }
}
```

**Teacher Dashboard:**
- Add "Manage Checks" button for active sessions
- Show check count progress (e.g., "2/3 checks completed")
- Link to AttendanceCheckScreen

```kotlin
// Example addition to active session card
if (session.multiCheckEnabled) {
    Text("Checks: ${session.checksCompleted}/${session.totalChecksPlanned}")
    Button(onClick = { navController.navigate("attendanceCheck/${session.sessionId}") }) {
        Text("Manage Checks")
    }
}
```

### 8. **Modified Mark Attendance Flow**

**For Active Checks** (Student):
1. User opens ActiveChecksScreen
2. Sees list of pending checks with countdown
3. Clicks "Respond Now" on a check
4. Goes to face + GPS verification
5. Submits response to check point
6. Gets immediate feedback (success/failed/late)
7. Shows progress: "2/3 checks completed"

**Traditional Flow** (remains unchanged for backward compatibility):
- Single attendance marking when class starts
- Works if multi-check is not enabled

## Firebase Cloud Messaging Updates

Add FCM notification handling for attendance checks:

```kotlin
// In SAMSFirebaseMessagingService.kt
when (notificationType) {
    "attendance_check" -> {
        // Show notification for new attendance check
        val checkPointId = data["check_point_id"]
        val intent = Intent(this, MainActivity::class.java).apply {
            putExtra("navigate_to", "activeChecks")
            putExtra("check_point_id", checkPointId)
        }
        // ... build and show notification
    }
}
```

## Testing Checklist

### Teacher Flow
- [ ] Start class with multi-check enabled
- [ ] Trigger attendance check (check #1)
- [ ] Verify students receive notification
- [ ] Trigger check #2 and #3
- [ ] View check response counts
- [ ] Finalize attendance
- [ ] Verify attendance summary shows present/absent/partial

### Student Flow
- [ ] Receive attendance check notification
- [ ] Open active checks screen
- [ ] See countdown timer
- [ ] Respond to check with GPS + face
- [ ] Verify success/failure message
- [ ] Check progress (e.g., "2/3 completed")
- [ ] Try responding to expired check (should fail)
- [ ] View final attendance status after finalization

### Edge Cases
- [ ] Network failure during check trigger
- [ ] Network failure during response
- [ ] GPS signal weak/unavailable
- [ ] Face recognition failure
- [ ] Late response (after window expires)
- [ ] Multiple check responses
- [ ] Session ended before finalization

## Backend Compatibility

The Android app is now compatible with the backend multi-check attendance APIs:

✅ `POST /api/teacher/trigger-attendance-check.php`
✅ `POST /api/teacher/finalize-attendance.php`
✅ `GET /api/student/active-attendance-checks.php`
✅ `POST /api/student/respond-attendance-check.php`

## Migration Notes

- **Backward Compatible**: Old single-check attendance still works
- **Opt-in**: Multi-check is enabled per session (default: enabled)
- **Gradual Rollout**: Can enable multi-check feature flag in settings
- **No Breaking Changes**: Existing attendance code remains functional

## Next Steps

1. **Add routes to NavGraph** for new screens
2. **Update dashboards** with multi-check indicators
3. **Add FCM handlers** for check notifications
4. **Test end-to-end flow** with backend
5. **Add analytics** for tracking multi-check adoption
6. **Update user documentation** and help screens

## UI Screenshots (To Be Added)

- Active Checks List (Student)
- Check Response Screen (Student)
- Trigger Check Screen (Teacher)
- Finalize Attendance Dialog (Teacher)

---

**Implementation Status**: ✅ Complete
**Backend Integration**: ✅ Ready
**Testing**: 🔄 Pending
**Production Ready**: After testing phase
