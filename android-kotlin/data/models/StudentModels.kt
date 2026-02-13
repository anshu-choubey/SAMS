package com.sams.app.data.models

import com.google.gson.annotations.SerializedName

/**
 * Base API Response wrapper
 */
data class ApiResponse<T>(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String?,
    @SerializedName("data") val data: T?,
    @SerializedName("timestamp") val timestamp: String?
)

// ==================== Authentication Models ====================

data class LoginRequest(
    @SerializedName("email") val email: String,
    @SerializedName("password") val password: String
)

data class LoginResponse(
    @SerializedName("user") val user: User,
    @SerializedName("session_id") val sessionId: String,
    @SerializedName("student_profile") val studentProfile: StudentProfile?,
    @SerializedName("teacher_profile") val teacherProfile: TeacherProfile?
)

data class User(
    @SerializedName("id") val id: Int,
    @SerializedName("full_name") val fullName: String,
    @SerializedName("email") val email: String,
    @SerializedName("role") val role: String,
    @SerializedName("phone") val phone: String?,
    @SerializedName("profile_image") val profileImage: String?
)

data class StudentProfile(
    @SerializedName("id") val id: Int,
    @SerializedName("roll_number") val rollNumber: String,
    @SerializedName("department_id") val departmentId: Int?,
    @SerializedName("department_name") val departmentName: String?,
    @SerializedName("semester") val semester: Int,
    @SerializedName("section") val section: String?,
    @SerializedName("batch_year") val batchYear: Int?,
    @SerializedName("face_registered") val faceRegistered: Boolean
)

data class TeacherProfile(
    @SerializedName("id") val id: Int,
    @SerializedName("employee_id") val employeeId: String,
    @SerializedName("department_id") val departmentId: Int?,
    @SerializedName("department_name") val departmentName: String?,
    @SerializedName("designation") val designation: String?,
    @SerializedName("qualification") val qualification: String?
)

// ==================== FCM Token Models ====================

data class FcmTokenRequest(
    @SerializedName("token") val token: String,
    @SerializedName("device_type") val deviceType: String = "android",
    @SerializedName("device_name") val deviceName: String? = null
)

data class FcmTokenRemoveRequest(
    @SerializedName("token") val token: String? = null
)

// ==================== Student Models ====================

data class StudentDashboardData(
    @SerializedName("profile") val profile: StudentDashboardProfile,
    @SerializedName("attendance_stats") val attendanceStats: AttendanceStats,
    @SerializedName("today_schedule") val todaySchedule: List<ScheduleItem>,
    @SerializedName("recent_attendance") val recentAttendance: List<RecentAttendance>,
    @SerializedName("active_session") val activeSession: ActiveSession?
)

data class StudentDashboardProfile(
    @SerializedName("student_id") val studentId: Int,
    @SerializedName("roll_number") val rollNumber: String,
    @SerializedName("full_name") val fullName: String,
    @SerializedName("department") val department: String?,
    @SerializedName("semester") val semester: Int,
    @SerializedName("section") val section: String?,
    @SerializedName("face_registered") val faceRegistered: Boolean
)

data class AttendanceStats(
    @SerializedName("total_classes") val totalClasses: Int,
    @SerializedName("attended") val attended: Int,
    @SerializedName("percentage") val percentage: Double,
    @SerializedName("this_week") val thisWeek: WeekStats?
)

data class WeekStats(
    @SerializedName("total") val total: Int,
    @SerializedName("attended") val attended: Int
)

data class ScheduleItem(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("subject_id") val subjectId: Int,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("subject_code") val subjectCode: String,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("start_time") val startTime: String,
    @SerializedName("end_time") val endTime: String,
    @SerializedName("classroom") val classroom: String?,
    @SerializedName("building") val building: String?,
    @SerializedName("is_active") val isActive: Boolean,
    @SerializedName("attendance_marked") val attendanceMarked: Boolean
)

data class RecentAttendance(
    @SerializedName("date") val date: String,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("subject_code") val subjectCode: String,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("status") val status: String,
    @SerializedName("verification_status") val verificationStatus: String
)

data class ActiveSession(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("teacher_latitude") val teacherLatitude: Double,
    @SerializedName("teacher_longitude") val teacherLongitude: Double,
    @SerializedName("classroom") val classroom: String?
)

data class StudentScheduleResponse(
    @SerializedName("schedules") val schedules: Map<String, List<ScheduleItem>>
)

data class AttendanceHistoryResponse(
    @SerializedName("records") val records: List<AttendanceRecord>,
    @SerializedName("summary") val summary: AttendanceSummary
)

data class AttendanceRecord(
    @SerializedName("id") val id: Int,
    @SerializedName("date") val date: String,
    @SerializedName("time") val time: String,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("subject_code") val subjectCode: String,
    @SerializedName("teacher_name") val teacherName: String,
    @SerializedName("status") val status: String,
    @SerializedName("verification_status") val verificationStatus: String,
    @SerializedName("face_confidence") val faceConfidence: Double?,
    @SerializedName("distance_meters") val distanceMeters: Double?
)

data class AttendanceSummary(
    @SerializedName("total_classes") val totalClasses: Int,
    @SerializedName("attended") val attended: Int,
    @SerializedName("percentage") val percentage: Double
)

// Student Profile Response
data class StudentProfileResponse(
    @SerializedName("profile") val profile: StudentProfileFull
)

data class StudentProfileFull(
    @SerializedName("id") val id: Int,
    @SerializedName("roll_number") val rollNumber: String,
    @SerializedName("full_name") val fullName: String,
    @SerializedName("email") val email: String,
    @SerializedName("phone") val phone: String?,
    @SerializedName("department_id") val departmentId: Int?,
    @SerializedName("department_name") val departmentName: String?,
    @SerializedName("semester") val semester: Int,
    @SerializedName("section") val section: String?,
    @SerializedName("batch_year") val batchYear: Int?,
    @SerializedName("admission_date") val admissionDate: String?,
    @SerializedName("face_registered") val faceRegistered: Boolean,
    @SerializedName("face_registration_date") val faceRegistrationDate: String?,
    @SerializedName("profile_image") val profileImage: String?
)

data class UpdateStudentProfileRequest(
    @SerializedName("full_name") val fullName: String? = null,
    @SerializedName("phone") val phone: String? = null,
    @SerializedName("semester") val semester: Int? = null,
    @SerializedName("section") val section: String? = null
)
