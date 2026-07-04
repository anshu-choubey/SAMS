@file:OptIn(
    ExperimentalSerializationApi::class,
    kotlinx.serialization.InternalSerializationApi::class
)
package com.sams.app.data.models

import android.annotation.SuppressLint
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.ExperimentalSerializationApi

// ==================== Common Models ====================

@SuppressLint("UnsafeOptInUsageError")
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
    @SerialName("schedule_id") val scheduleId: Int = 0,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("teacher_name") val teacherName: String,
    @SerialName("teacher_id") val teacherId: Int = 0,
    val classroom: String? = null,
    @SerialName("session_start_time") val sessionStartTime: String? = null,
    @SerialName("teacher_latitude") val teacherLatitude: Double = 0.0,
    @SerialName("teacher_longitude") val teacherLongitude: Double = 0.0
)

@Serializable
data class ScheduleItem(
    @SerialName("id") val scheduleId: Int = 0,
    @SerialName("subject_id") val subjectId: Int = 0,
    @SerialName("subject_name") val subjectName: String = "",
    @SerialName("subject_code") val subjectCode: String = "",
    @SerialName("teacher_name") val teacherName: String = "",
    @SerialName("teacher_id") val teacherId: Int = 0,
    @SerialName("day_of_week") val dayOfWeek: String = "",
    @SerialName("start_time") val startTime: String = "",
    @SerialName("end_time") val endTime: String = "",
    val classroom: String? = null,
    val building: String? = null,
    @SerialName("attendance_marked") val attendanceMarked: Boolean = false,
    @SerialName("is_active") val isActive: Boolean = false,
    @SerialName("session_active") val sessionActive: Boolean = false,
    @SerialName("teacher_latitude") val teacherLatitude: Double = 0.0,
    @SerialName("teacher_longitude") val teacherLongitude: Double = 0.0
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
    fun getAttendance(): AttendanceStats =
        overallAttendance ?: attendanceStats ?: AttendanceStats(0, 0, 0.0)
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
data class StudentScheduleData(
    val data: ScheduleDataWrapper? = null,
    val schedules: List<ScheduleItem> = emptyList()
)

@Serializable
data class ScheduleDataWrapper(
    val schedules: List<ScheduleItem> = emptyList()
)

@Serializable
data class StudentScheduleApiResponse(
    val success: Boolean,
    val data: StudentScheduleData? = null,
    val message: String? = null,
    val timestamp: String? = null
)

@Serializable
data class StudentScheduleResponse(
    val success: Boolean,
    val schedules: Map<String, List<ScheduleItem>> = emptyMap(),
    val message: String? = null,
    val timestamp: String? = null
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
    @SerialName("distance_meters") val distanceMeters: Double? = null,
    @SerialName("verification_status") val verificationStatus: String? = null
)

@Serializable
data class AttendanceSummary(
    @SerialName("total_classes") val totalClasses: Int = 0,
    val attended: Int = 0,
    val percentage: Double = 0.0
)

@Serializable
data class PaginationInfo(
    @SerialName("current_page") val currentPage: Int = 1,
    @SerialName("total_pages") val totalPages: Int = 0,
    @SerialName("total_records") val totalRecords: Int = 0,
    @SerialName("per_page") val perPage: Int = 20
)

@Serializable
data class AttendanceHistoryData(
    val records: List<AttendanceRecord> = emptyList(),
    val pagination: PaginationInfo? = null,
    val summary: AttendanceSummary? = null
)

@Serializable
data class AttendanceHistoryResponse(
    val success: Boolean,
    val data: AttendanceHistoryData? = null,
    val message: String? = null,
    val timestamp: String? = null
)

@Serializable
data class MarkAttendanceRequest(
    @SerialName("schedule_id") val scheduleId: Int,
    val latitude: Double,
    val longitude: Double,
    @SerialName("face_confidence") val faceConfidence: Double,
    @SerialName("face_embedding") val faceEmbedding: String? = null,
    @SerialName("gps_accuracy") val gpsAccuracy: Float = 0f  // GPS accuracy in meters
)

// ✅ Continuous Monitoring Config
@Serializable
data class ContinuousMonitoringConfig(
    val session: ContinuousSession,
    @SerialName("scheduled_checks") val scheduledChecks: List<ScheduledCheck> = emptyList(),
    val settings: ContinuousSettings
)

@Serializable
data class ContinuousSession(
    @SerialName("session_id") val sessionId: Int,
    @SerialName("schedule_id") val scheduleId: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("teacher_name") val teacherName: String,
    @SerialName("started_at") val startedAt: String,
    @SerialName("expected_end") val expectedEnd: String,
    @SerialName("multi_check_enabled") val multiCheckEnabled: Boolean,
    @SerialName("total_checks_planned") val totalChecksPlanned: Int,
    @SerialName("auto_schedule") val autoSchedule: Boolean
)

@Serializable
data class ScheduledCheck(
    @SerialName("check_number") val checkNumber: Int,
    @SerialName("check_time") val checkTime: String,
    @SerialName("window_end_time") val windowEndTime: String,
    @SerialName("is_active") val isActive: Boolean
)

@Serializable
data class ContinuousSettings(
    @SerialName("continuous_monitoring_enabled") val continuousMonitoringEnabled: Boolean,
    @SerialName("continuous_monitoring_required") val continuousMonitoringRequired: Boolean,
    @SerialName("auto_response_enabled") val autoResponseEnabled: Boolean,
    @SerialName("face_detection_interval_seconds") val faceDetectionIntervalSeconds: Int,
    @SerialName("liveness_detection_enabled") val livenessDetectionEnabled: Boolean,
    @SerialName("liveness_min_score") val livenessMinScore: Int,
    @SerialName("face_confidence_threshold") val faceConfidenceThreshold: Int,
    @SerialName("check_window_minutes") val checkWindowMinutes: Int
)

// Student Multi-Check Attendance Models
@Serializable
data class ActiveAttendanceChecksResponse(
    val success: Boolean,
    val message: String? = null,
    val data: ActiveAttendanceChecksData? = null
)

@Serializable
data class ActiveAttendanceChecksData(
    @SerialName("active_checks") val activeChecks: List<ActiveAttendanceCheck> = emptyList(),
    @SerialName("total_pending") val totalPending: Int = 0,
    @SerialName("active_sessions") val activeSessions: List<ActiveClassSession> = emptyList(),
    @SerialName("stay_on_screen") val stayOnScreen: Boolean = false,
    @SerialName("has_random_intervals") val hasRandomIntervals: Boolean = false
)

@Serializable
data class ActiveAttendanceCheck(
    @SerialName("check_point_id") val checkPointId: Int,
    @SerialName("check_number") val checkNumber: Int,
    @SerialName("session_id") val sessionId: Int,
    @SerialName("schedule_id") val scheduleId: Int = 0,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("teacher_name") val teacherName: String,
    val classroom: String? = null,
    val building: String? = null,
    @SerialName("class_start_time") val classStartTime: String? = null,
    @SerialName("class_end_time") val classEndTime: String? = null,
    @SerialName("teacher_latitude") val teacherLatitude: Double = 0.0,
    @SerialName("teacher_longitude") val teacherLongitude: Double = 0.0,
    @SerialName("check_time") val checkTime: String? = null,
    @SerialName("window_end_time") val windowEndTime: String? = null,
    @SerialName("is_expired") val isExpired: Boolean = false,
    @SerialName("seconds_remaining") val secondsRemaining: Int? = null,
    @SerialName("total_checks_planned") val totalChecksPlanned: Int = 0,
    @SerialName("checks_completed") val checksCompleted: Int = 0,
    @SerialName("hide_timing") val hideTiming: Boolean = false,
    @SerialName("random_intervals") val randomIntervals: Boolean = false,
    val message: String? = null
)

@Serializable
data class ActiveClassSession(
    @SerialName("session_id") val sessionId: Int,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("teacher_name") val teacherName: String,
    val classroom: String? = null,
    @SerialName("total_checks_planned") val totalChecksPlanned: Int = 0,
    @SerialName("checks_completed") val checksCompleted: Int = 0,
    @SerialName("student_successful_checks") val studentSuccessfulChecks: Int = 0,
    @SerialName("checks_remaining") val checksRemaining: Int = 0,
    @SerialName("hide_timing") val hideTiming: Boolean = false,
    @SerialName("random_intervals") val randomIntervals: Boolean = false,
    val message: String? = null
)

@Serializable
data class RespondAttendanceCheckRequest(
    @SerialName("check_point_id") val checkPointId: Int,
    val latitude: Double,
    val longitude: Double,
    @SerialName("face_confidence") val faceConfidence: Double,
    @SerialName("gps_accuracy") val gpsAccuracy: Float = 0f,  // GPS accuracy in meters
    @SerialName("device_info") val deviceInfo: String? = null
)

@Serializable
data class RespondAttendanceCheckResponse(
    val success: Boolean,
    val message: String? = null,
    val data: AttendanceCheckResponseData? = null
)

@Serializable
data class AttendanceCheckResponseData(
    @SerialName("response_id") val responseId: Int,
    @SerialName("check_point_id") val checkPointId: Int,
    @SerialName("check_number") val checkNumber: Int,
    @SerialName("verification_status") val verificationStatus: String,
    @SerialName("distance_meters") val distanceMeters: Double,
    @SerialName("face_confidence") val faceConfidence: Double,
    @SerialName("is_late") val isLate: Boolean,
    @SerialName("total_responses") val totalResponses: Int,
    @SerialName("successful_checks") val successfulChecks: Int,
    @SerialName("total_checks_planned") val totalChecksPlanned: Int = 0,
    @SerialName("all_checks_complete") val allChecksComplete: Boolean = false,
    @SerialName("attendance_marked") val attendanceMarked: Boolean = false
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

// ✅ Multi-Check Attendance Models
@Serializable
data class TriggerAttendanceCheckRequest(
    @SerialName("session_id") val sessionId: Int,
    @SerialName("window_minutes") val windowMinutes: Int = 5
)

@Serializable
data class TriggerAttendanceCheckResponse(
    val success: Boolean,
    val message: String? = null,
    val data: AttendanceCheckPoint? = null
)

@Serializable
data class AttendanceCheckPoint(
    @SerialName("check_point_id") val checkPointId: Int,
    @SerialName("check_number") val checkNumber: Int,
    @SerialName("session_id") val sessionId: Int,
    @SerialName("triggered_at") val triggeredAt: String,
    @SerialName("window_end_time") val windowEndTime: String,
    @SerialName("window_minutes") val windowMinutes: Int,
    @SerialName("expected_responses") val expectedResponses: Int
)

@Serializable
data class FinalizeAttendanceRequest(
    @SerialName("session_id") val sessionId: Int
)

@Serializable
data class FinalizeAttendanceResponse(
    val success: Boolean,
    val message: String? = null,
    val data: FinalizeAttendanceData? = null
)

@Serializable
data class FinalizeAttendanceData(
    @SerialName("session_id") val sessionId: Int,
    @SerialName("total_students") val totalStudents: Int,
    val present: Int,
    val absent: Int,
    val partial: Int,
    @SerialName("attendance_percentage") val attendancePercentage: Double,
    @SerialName("total_checks_conducted") val totalChecksConducted: Int,
    @SerialName("required_successful_checks") val requiredSuccessfulChecks: Int
)

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

// ✅ Used in dashboard active_session — schedule_id comes as String from API
@Serializable
data class TeacherActiveSession(
    @SerialName("session_id") val sessionId: Int? = null,
    @SerialName("schedule_id") val scheduleId: String = "0", // ✅ String — API sends "108" not 108
    @SerialName("subject_id") val subjectId: Int = 0,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    val classroom: String? = null,
    @SerialName("session_start_time") val sessionStartTime: String? = null,
    @SerialName("present_count") val presentCount: Int = 0,
    @SerialName("total_students") val totalStudents: Int = 0
) {
    // ✅ Safe Int conversion helper
    val scheduleIdInt: Int get() = scheduleId.toIntOrNull() ?: 0
}

// ✅ Used in dashboard today_schedule — uses "schedule_id" key
@Serializable
data class TeacherScheduleItem(
    @SerialName("schedule_id") val scheduleId: Int = 0,
    @SerialName("subject_id") val subjectId: Int = 0,
    @SerialName("subject_name") val subjectName: String = "",
    @SerialName("subject_code") val subjectCode: String = "",
    @SerialName("day_of_week") val dayOfWeek: String = "",
    @SerialName("start_time") val startTime: String = "",
    @SerialName("end_time") val endTime: String = "",
    @SerialName("semester") val semester: Int = 0,
    @SerialName("section") val section: String? = null,  // ✅ Can be null from API
    @SerialName("classroom") val classroom: String? = null,
    @SerialName("session_active") val sessionActive: Boolean = false,
    @SerialName("is_completed") val isCompleted: Boolean = false,
    @SerialName("is_startable") val isStartable: Boolean = false
)


@Serializable
data class TeacherWeeklyScheduleItem(
    @SerialName("id") val scheduleId: Int = 0,
    @SerialName("assignment_id") val assignmentId: Int = 0,
    @SerialName("subject_name") val subjectName: String = "",
    @SerialName("subject_code") val subjectCode: String = "",
    @SerialName("day_of_week") val dayOfWeek: String = "",
    @SerialName("start_time") val startTime: String = "",
    @SerialName("end_time") val endTime: String = "",
    @SerialName("semester") val semester: Int = 0,
    @SerialName("section") val section: String? = null,  // ✅ Can be null from API
    @SerialName("classroom") val classroom: String? = null,
    @SerialName("building") val building: String? = null,
    @SerialName("department_name") val departmentName: String? = null,
    @SerialName("teacher_name") val teacherName: String? = null,
    @SerialName("is_active") val isActive: Int = 1,
    @SerialName("academic_year") val academicYear: Int? = null,  // ✅ May also be null
    // ✅ Not in API response — always default false
    val sessionActive: Boolean = false,
    val isCompleted: Boolean = false,
    val isStartable: Boolean = false
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

// ✅ Uses TeacherWeeklyScheduleItem for schedules.php
@Serializable
data class TeacherScheduleData(
    val schedules: List<TeacherWeeklyScheduleItem> = emptyList(),
    val timestamp: String? = null
)

@Serializable
data class TeacherScheduleResponse(
    val success: Boolean,
    val data: TeacherScheduleData? = null,
    val message: String? = null,
    val timestamp: String? = null
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
    @SerialName("attendance_window_minutes") val attendanceWindowMinutes: Int? = null,
    @SerialName("multi_check_enabled") val multiCheckEnabled: Boolean = false,
    @SerialName("total_checks_planned") val totalChecksPlanned: Int = 1,
    @SerialName("auto_schedule") val autoSchedule: Boolean = false,
    @SerialName("scheduled_check_times") val scheduledCheckTimes: List<Int> = emptyList()
)
@Serializable
data class EndSessionResponse(
    val success: Boolean,
    val message: String? = null,
    val data: EndSessionData? = null
)

// ✅ Also add auto_marked_absent field that PHP sends
@Serializable
data class EndSessionData(
    @SerialName("session_id") val sessionId: Int? = null,
    @SerialName("ended_at") val endedAt: String? = null,
    @SerialName("total_students") val totalStudents: Int? = null,
    val present: Int? = null,
    val absent: Int? = null,
    val partial: Int? = null,
    @SerialName("attendance_percentage") val attendancePercentage: Double? = null,
    @SerialName("present_count") val presentCount: Int? = null,
    @SerialName("absent_count") val absentCount: Int? = null,
    @SerialName("auto_marked_absent") val autoMarkedAbsent: Int? = null
)

@Serializable
data class EndSessionRequest(
    @SerialName("session_id") val sessionId: Int
)

@Serializable
data class StudentAttendanceStatus(
    @SerialName("student_id") val studentId: Int,
    @SerialName("student_name") val studentName: String,
    @SerialName("roll_number") val rollNumber: String,
    val status: String? = null,
    @SerialName("marked_at") val markedAt: String? = null,
    @SerialName("face_confidence") val faceConfidence: Double? = null,
    @SerialName("face_registered") val faceRegistered: Boolean = false
)

@Serializable
data class ClassAttendanceResponse(
    val success: Boolean,
    @SerialName("schedule_id") val scheduleId: Int,
    @SerialName("session_id") val sessionId: Int? = null,
    @SerialName("subject_name") val subjectName: String,
    @SerialName("subject_code") val subjectCode: String,
    @SerialName("session_active") val sessionActive: Boolean = false,
    @SerialName("total_students") val totalStudents: Int = 0,
    @SerialName("present_count") val presentCount: Int = 0,
    @SerialName("absent_count") val absentCount: Int = 0,
    @SerialName("start_time") val startTime: String? = null,
    @SerialName("end_time") val endTime: String? = null,
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
data class NotificationPreferencesResponse(
    val preferences: Map<String, Boolean> = emptyMap(),
    @SerialName("user_id") val userId: Int? = null,
    val message: String? = null
)

@Serializable
data class MarkNotificationReadRequest(
    @SerialName("notification_id") val notificationId: Int? = null,
    @SerialName("mark_all") val markAll: Boolean = false
)

// ==================== App Settings Models ====================

@Serializable
data class AppSettingsConfig(
    val attendance: AttendanceSettings,
    val system: SystemSettings,
    @SerialName("app_info") val appInfo: AppInfo
)

@Serializable
data class AttendanceSettings(
    @SerialName("gps_proximity_radius") val gpsProximityRadius: Int = 50,
    @SerialName("face_confidence_threshold") val faceConfidenceThreshold: Int = 60,
    @SerialName("enableLivenessDetection") val enableLivenessDetection: Boolean = true,
    @SerialName("multi_check_enabled") val multiCheckEnabled: Boolean = true,
    @SerialName("default_total_checks") val defaultTotalChecks: Int = 2,
    @SerialName("check_window_minutes") val checkWindowMinutes: Int = 3,
    @SerialName("hide_timing_from_students") val hideTimingFromStudents: Boolean = true
)

@Serializable
data class SystemSettings(
    @SerialName("session_timeout") val sessionTimeout: Int = 3600,
    @SerialName("academic_year") val academicYear: Int = 2026,
    @SerialName("semester_duration_weeks") val semesterDurationWeeks: Int = 16,
    @SerialName("current_semester") val currentSemester: Int = 1,
    @SerialName("maintenance_mode") val maintenanceMode: Boolean = false
)

@Serializable
data class AppInfo(
    val name: String,
    val version: String,
    val institution: String,
    @SerialName("logo_url") val logoUrl: String,
    @SerialName("support_email") val supportEmail: String
)

// ==================== FCM Models ====================

@Serializable
data class FcmRegisterRequest(
    val token: String,
    @SerialName("device_type") val deviceType: String = "android",
    @SerialName("device_name") val deviceName: String? = null
)

// ==================== UI State ====================

sealed class UiState<out T> {
    object Loading : UiState<Nothing>()
    data class Success<T>(val data: T) : UiState<T>()
    data class Error(val message: String) : UiState<Nothing>()
    object Idle : UiState<Nothing>()
}
