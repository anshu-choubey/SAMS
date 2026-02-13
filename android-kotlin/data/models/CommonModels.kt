package com.sams.app.data.models

import com.google.gson.annotations.SerializedName

// ==================== Face Registration & Verification Models ====================

data class FaceRegistrationRequest(
    @SerializedName("face_embedding") val faceEmbedding: String
)

data class FaceRegistrationResponse(
    @SerializedName("student_id") val studentId: Int,
    @SerializedName("face_registered") val faceRegistered: Boolean
)

data class FaceVerificationRequest(
    @SerializedName("face_embedding") val faceEmbedding: String
)

data class FaceVerificationResponse(
    @SerializedName("student_id") val studentId: Int,
    @SerializedName("face_registered") val faceRegistered: Boolean,
    @SerializedName("stored_embedding") val storedEmbedding: String,
    @SerializedName("threshold") val threshold: Int
)

// ==================== Attendance Marking Models ====================

data class MarkAttendanceRequest(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("latitude") val latitude: Double,
    @SerializedName("longitude") val longitude: Double,
    @SerializedName("face_confidence") val faceConfidence: Double
)

data class MarkAttendanceResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String,
    @SerializedName("attendance_id") val attendanceId: Int?,
    @SerializedName("verification_status") val verificationStatus: String?,
    @SerializedName("distance_meters") val distanceMeters: Double?,
    @SerializedName("face_confidence") val faceConfidence: Double?
)

// ==================== Notification Models ====================

data class NotificationsResponse(
    @SerializedName("notifications") val notifications: List<NotificationItem>,
    @SerializedName("unread_count") val unreadCount: Int
)

data class NotificationItem(
    @SerializedName("id") val id: Int,
    @SerializedName("title") val title: String,
    @SerializedName("message") val message: String,
    @SerializedName("type") val type: String,
    @SerializedName("data") val data: Map<String, Any>?,
    @SerializedName("is_read") val isRead: Boolean,
    @SerializedName("created_at") val createdAt: String,
    @SerializedName("read_at") val readAt: String?
)

data class MarkNotificationReadRequest(
    @SerializedName("notification_id") val notificationId: Int? = null,
    @SerializedName("mark_all") val markAll: Boolean? = null
)

// ==================== Schedule Models ====================

data class Schedule(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("subject_id") val subjectId: Int,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("subject_code") val subjectCode: String,
    @SerializedName("teacher_id") val teacherId: Int?,
    @SerializedName("teacher_name") val teacherName: String?,
    @SerializedName("department_id") val departmentId: Int?,
    @SerializedName("department_name") val departmentName: String?,
    @SerializedName("day_of_week") val dayOfWeek: String,
    @SerializedName("start_time") val startTime: String,
    @SerializedName("end_time") val endTime: String,
    @SerializedName("classroom") val classroom: String?,
    @SerializedName("building") val building: String?,
    @SerializedName("semester") val semester: Int?,
    @SerializedName("section") val section: String?,
    @SerializedName("is_active") val isActive: Boolean = false,
    @SerializedName("attendance_marked") val attendanceMarked: Boolean = false,
    @SerializedName("session_started") val sessionStarted: Boolean = false
)

data class ScheduleResponse(
    @SerializedName("schedules") val schedules: List<Schedule>
)

data class WeeklyScheduleResponse(
    @SerializedName("schedules") val schedules: Map<String, List<Schedule>>
)

// ==================== Utility Enums ====================

enum class UserRole(val value: String) {
    ADMIN("admin"),
    TEACHER("teacher"),
    STUDENT("student")
}

enum class AttendanceStatus(val value: String) {
    PRESENT("present"),
    ABSENT("absent"),
    LATE("late")
}

enum class VerificationStatus(val value: String) {
    SUCCESS("success"),
    GPS_FAILED("gps_failed"),
    FACE_FAILED("face_failed"),
    BOTH_FAILED("both_failed")
}

enum class NotificationType(val value: String) {
    ATTENDANCE_ALERT("attendance_alert"),
    LOW_ATTENDANCE("low_attendance"),
    SYSTEM("system"),
    SCHEDULE_CHANGE("schedule_change"),
    FACE_REREGISTER("face_reregister")
}
