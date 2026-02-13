package com.sams.app.data.models

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

// ==================== Common Models ====================

@Serializable
data class ApiResponse<T>(
    val success: Boolean,
    val message: String? = null,
    val data: T? = null
)

@Serializable
data class User(
    val id: Int,
    val email: String,
    @SerialName("full_name") val fullName: String,
    val role: String,
    val phone: String? = null,
    @SerialName("department_id") val departmentId: Int? = null,
    @SerialName("department_name") val departmentName: String? = null
)

@Serializable
data class Schedule(
    @SerialName("schedule_id") val scheduleId: Int,
    @SerialName("subject_id") val subjectId: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("teacher_id") val teacherId: Int,
    @SerialName("teacher_name") val teacherName: String,
    @SerialName("day_of_week") val dayOfWeek: String,
    @SerialName("start_time") val startTime: String,
    @SerialName("end_time") val endTime: String,
    val semester: Int,
    val section: String,
    val classroom: String? = null,
    @SerialName("is_active") val isActive: Boolean = false,
    @SerialName("attendance_marked") val attendanceMarked: Boolean = false,
    @SerialName("session_active") val sessionActive: Boolean = false
)

@Serializable
data class Notification(
    val id: Int,
    val title: String,
    val message: String,
    val type: String,
    @SerialName("is_read") val isRead: Boolean = false,
    @SerialName("created_at") val createdAt: String,
    @SerialName("read_at") val readAt: String? = null,
    val data: String? = null
)

// ==================== Auth Models ====================

@Serializable
data class LoginRequest(
    val email: String,
    val password: String,
    @SerialName("device_token") val deviceToken: String? = null
)

@Serializable
data class LoginResponse(
    val success: Boolean,
    val message: String? = null,
    val data: LoginData? = null
)

@Serializable
data class LoginData(
    val user: LoginUser,
    @SerialName("session_id") val sessionId: String,
    val profile: LoginProfile? = null
)

@Serializable
data class LoginUser(
    val id: Int,
    @SerialName("full_name") val fullName: String,
    val email: String,
    val role: String,
    @SerialName("is_active") val isActive: Int = 1
)

@Serializable
data class LoginProfile(
    val id: Int? = null,
    @SerialName("roll_number") val rollNumber: String? = null,
    @SerialName("department_id") val departmentId: Int? = null,
    @SerialName("department_name") val departmentName: String? = null,
    val semester: Int? = null,
    val section: String? = null,
    @SerialName("batch_year") val batchYear: Int? = null,
    @SerialName("employee_id") val employeeId: String? = null
)

// ==================== Student Models ====================

@Serializable
data class StudentProfile(
    val id: Int,
    @SerialName("user_id") val userId: Int? = null,
    @SerialName("full_name") val fullName: String? = null,
    val email: String? = null,
    val phone: String? = null,
    @SerialName("roll_number") val rollNumber: String? = null,
    @SerialName("department_id") val departmentId: Int? = null,
    val department: String? = null,
    @SerialName("department_name") val departmentName: String? = null,
    val semester: Int? = null,
    val section: String? = null,
    @SerialName("batch_year") val batchYear: Int? = null,
    @SerialName("face_registered") val faceRegistered: Boolean = false
)

@Serializable
data class StudentProfileFull(
    val id: Int,
    @SerialName("user_id") val userId: Int? = null,
    @SerialName("full_name") val fullName: String,
    val email: String,
    val phone: String? = null,
    @SerialName("roll_number") val rollNumber: String,
    @SerialName("department_id") val departmentId: Int? = null,
    @SerialName("department_name") val departmentName: String? = null,
    val semester: Int,
    val section: String? = null,
    @SerialName("batch_year") val batchYear: Int? = null,
    @SerialName("face_registered") val faceRegistered: Boolean = false,
    @SerialName("face_registered_at") val faceRegisteredAt: String? = null,
    @SerialName("face_registration_date") val faceRegistrationDate: String? = null,
    @SerialName("admission_date") val admissionDate: String? = null,
    @SerialName("profile_image") val profileImage: String? = null
)

@Serializable
data class AttendanceStats(
    @SerialName("total_classes") val totalClasses: Int,
    val attended: Int,
    val percentage: Double
)

@Serializable
data class ActiveSession(
    @SerialName("schedule_id") val scheduleId: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("teacher_name") val teacherName: String,
    @SerialName("teacher_id") val teacherId: Int,
    val classroom: String? = null,
    @SerialName("session_start_time") val sessionStartTime: String? = null,
    @SerialName("teacher_latitude") val teacherLatitude: Double = 0.0,
    @SerialName("teacher_longitude") val teacherLongitude: Double = 0.0
)

@Serializable
data class ScheduleItem(
    @SerialName("schedule_id") val scheduleId: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("teacher_name") val teacherName: String,
    @SerialName("start_time") val startTime: String,
    @SerialName("end_time") val endTime: String,
    val classroom: String? = null,
    @SerialName("attendance_marked") val attendanceMarked: Boolean = false,
    @SerialName("is_active") val isActive: Boolean = false
)

@Serializable
data class StudentDashboardData(
    val profile: StudentProfile,
    @SerialName("overall_attendance") val overallAttendance: AttendanceStats? = null,
    @SerialName("attendance_stats") val attendanceStats: AttendanceStats? = null,
    @SerialName("subject_wise") val subjectWise: List<SubjectAttendance> = emptyList(),
    @SerialName("recent_attendance") val recentAttendance: List<RecentAttendance> = emptyList(),
    @SerialName("low_attendance_subjects") val lowAttendanceSubjects: List<LowAttendanceSubject> = emptyList(),
    @SerialName("today_schedule") val todaySchedule: List<ScheduleItem> = emptyList(),
    @SerialName("active_session") val activeSession: ActiveSession? = null
) {
    // Helper to get attendance stats from either field
    fun getAttendance(): AttendanceStats = overallAttendance ?: attendanceStats ?: AttendanceStats(0, 0, 0.0)
}

@Serializable
data class SubjectAttendance(
    @SerialName("subject_id") val subjectId: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("total_classes") val totalClasses: Int,
    val attended: Int,
    val percentage: Double
)

@Serializable
data class RecentAttendance(
    val id: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("teacher_name") val teacherName: String,
    val date: String,
    val time: String,
    val status: String,
    @SerialName("verification_status") val verificationStatus: String? = null
)

@Serializable
data class LowAttendanceSubject(
    @SerialName("subject_name") val subjectName: String,
    val percentage: Double,
    @SerialName("classes_needed") val classesNeeded: Int
)

@Serializable
data class StudentDashboardResponse(
    val success: Boolean,
    val data: StudentDashboardData? = null,
    val message: String? = null
)

@Serializable
data class StudentScheduleResponse(
    val success: Boolean,
    val schedules: Map<String, List<ScheduleItem>> = emptyMap(),
    val message: String? = null
)

@Serializable
data class StudentProfileResponse(
    val success: Boolean,
    val data: StudentProfileWrapper? = null,
    val message: String? = null
)

@Serializable
data class StudentProfileWrapper(
    val profile: StudentProfileFull
)

@Serializable
data class AttendanceRecord(
    @SerialName("attendance_id") val attendanceId: Int,
    @SerialName("schedule_id") val scheduleId: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("teacher_name") val teacherName: String,
    val date: String,
    val time: String,
    val status: String,
    @SerialName("face_confidence") val faceConfidence: Double? = null,
    @SerialName("distance_meters") val distanceMeters: Double? = null
)

@Serializable
data class AttendanceSummary(
    @SerialName("total_classes") val totalClasses: Int,
    val attended: Int,
    val percentage: Double
)

@Serializable
data class AttendanceHistoryResponse(
    val success: Boolean,
    val summary: AttendanceSummary,
    val records: List<AttendanceRecord> = emptyList(),
    val message: String? = null
)

@Serializable
data class MarkAttendanceRequest(
    @SerialName("schedule_id") val scheduleId: Int,
    val latitude: Double,
    val longitude: Double,
    @SerialName("face_confidence") val faceConfidence: Double,
    @SerialName("face_embedding") val faceEmbedding: String? = null
)

@Serializable
data class MarkAttendanceResponse(
    val success: Boolean,
    val message: String? = null,
    @SerialName("attendance_id") val attendanceId: Int? = null,
    @SerialName("face_confidence") val faceConfidence: Double? = null,
    @SerialName("distance_meters") val distanceMeters: Double? = null,
    @SerialName("verification_status") val verificationStatus: String? = null
)

@Serializable
data class FaceRegistrationRequest(
    @SerialName("face_embedding") val faceEmbedding: String
)

@Serializable
data class FaceRegistrationResponse(
    val success: Boolean,
    val message: String? = null
)

@Serializable
data class UpdateStudentProfileRequest(
    val phone: String? = null,
    val section: String? = null
)

// ==================== Teacher Models ====================

@Serializable
data class TeacherProfile(
    val id: Int,
    @SerialName("user_id") val userId: Int,
    @SerialName("full_name") val fullName: String,
    val email: String,
    val phone: String? = null,
    @SerialName("employee_id") val employeeId: String? = null,
    @SerialName("department_id") val departmentId: Int? = null,
    @SerialName("department_name") val departmentName: String? = null,
    val designation: String? = null,
    val qualification: String? = null
)

@Serializable
data class TeacherSubject(
    @SerialName("subject_id") val subjectId: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    val semester: Int,
    val section: String
)

@Serializable
data class TeacherActiveSession(
    @SerialName("session_id") val sessionId: Int? = null,
    @SerialName("schedule_id") val scheduleId: Int,
    @SerialName("subject_id") val subjectId: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    val classroom: String? = null,
    @SerialName("session_start_time") val sessionStartTime: String? = null,
    @SerialName("present_count") val presentCount: Int = 0,
    @SerialName("total_students") val totalStudents: Int = 0
)

@Serializable
data class TeacherScheduleItem(
    @SerialName("schedule_id") val scheduleId: Int,
    @SerialName("subject_id") val subjectId: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("start_time") val startTime: String,
    @SerialName("end_time") val endTime: String,
    val semester: Int,
    val section: String,
    val classroom: String? = null,
    @SerialName("session_active") val sessionActive: Boolean = false,
    @SerialName("is_startable") val isStartable: Boolean = false,
    @SerialName("is_completed") val isCompleted: Boolean = false
)

@Serializable
data class TeacherDashboardData(
    val profile: TeacherProfile,
    val subjects: List<TeacherSubject> = emptyList(),
    @SerialName("today_schedule") val todaySchedule: List<TeacherScheduleItem> = emptyList(),
    @SerialName("active_session") val activeSession: TeacherActiveSession? = null,
    @SerialName("total_students") val totalStudents: Int = 0
)

@Serializable
data class TeacherDashboardResponse(
    val success: Boolean,
    val data: TeacherDashboardData? = null,
    val message: String? = null
)

@Serializable
data class TeacherScheduleResponse(
    val success: Boolean,
    val schedules: Map<String, List<TeacherScheduleItem>> = emptyMap(),
    val message: String? = null
)

@Serializable
data class StartSessionRequest(
    @SerialName("schedule_id") val scheduleId: Int,
    val latitude: Double,
    val longitude: Double
)

@Serializable
data class StartSessionData(
    @SerialName("session_id") val sessionId: Int,
    @SerialName("schedule_id") val scheduleId: Int,
    @SerialName("started_at") val startedAt: String? = null,
    @SerialName("expected_end") val expectedEnd: String? = null,
    @SerialName("qr_code") val qrCode: String? = null,
    @SerialName("attendance_window_minutes") val attendanceWindowMinutes: Int? = null
)

@Serializable
data class EndSessionRequest(
    @SerialName("session_id") val sessionId: Int
)

@Serializable
data class EndSessionData(
    @SerialName("session_id") val sessionId: Int? = null,
    @SerialName("ended_at") val endedAt: String? = null,
    @SerialName("total_students") val totalStudents: Int? = null,
    val present: Int? = null,
    val absent: Int? = null,
    @SerialName("attendance_percentage") val attendancePercentage: Double? = null,
    @SerialName("present_count") val presentCount: Int? = null,
    @SerialName("absent_count") val absentCount: Int? = null
)

@Serializable
data class StudentAttendanceStatus(
    @SerialName("student_id") val studentId: Int,
    @SerialName("student_name") val studentName: String,
    @SerialName("roll_number") val rollNumber: String,
    val status: String? = null,
    @SerialName("marked_at") val markedAt: String? = null,
    @SerialName("face_confidence") val faceConfidence: Double? = null
)

@Serializable
data class ClassAttendanceResponse(
    val success: Boolean,
    @SerialName("schedule_id") val scheduleId: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("session_active") val sessionActive: Boolean = false,
    @SerialName("total_students") val totalStudents: Int = 0,
    @SerialName("present_count") val presentCount: Int = 0,
    @SerialName("absent_count") val absentCount: Int = 0,
    val students: List<StudentAttendanceStatus> = emptyList(),
    val message: String? = null
)

@Serializable
data class ManualAttendanceRequest(
    @SerialName("schedule_id") val scheduleId: Int,
    @SerialName("student_id") val studentId: Int,
    val status: String
)

// ==================== Notification Models ====================

@Serializable
data class NotificationListResponse(
    val success: Boolean,
    val notifications: List<Notification> = emptyList(),
    @SerialName("unread_count") val unreadCount: Int = 0,
    val message: String? = null
)

@Serializable
data class MarkNotificationReadRequest(
    @SerialName("notification_id") val notificationId: Int? = null,
    @SerialName("mark_all") val markAll: Boolean = false
)

// ==================== FCM Models ====================

@Serializable
data class FcmRegisterRequest(
    @SerialName("device_token") val deviceToken: String,
    @SerialName("device_type") val deviceType: String = "android"
)
