package com.sams.app.data.models

import com.google.gson.annotations.SerializedName

// ==================== Teacher Models ====================

data class TeacherDashboardData(
    @SerializedName("profile") val profile: TeacherDashboardProfile,
    @SerializedName("stats") val stats: TeacherStats,
    @SerializedName("today_classes") val todayClasses: List<TeacherScheduleItem>,
    @SerializedName("recent_sessions") val recentSessions: List<RecentSession>,
    @SerializedName("active_session") val activeSession: TeacherActiveSession?
)

data class TeacherDashboardProfile(
    @SerializedName("teacher_id") val teacherId: Int,
    @SerializedName("employee_id") val employeeId: String,
    @SerializedName("full_name") val fullName: String,
    @SerializedName("department") val department: String?,
    @SerializedName("designation") val designation: String?,
    @SerializedName("qualification") val qualification: String?
)

data class TeacherStats(
    @SerializedName("total_classes_today") val totalClassesToday: Int,
    @SerializedName("completed_classes") val completedClasses: Int,
    @SerializedName("total_students") val totalStudents: Int,
    @SerializedName("avg_attendance_rate") val avgAttendanceRate: Double
)

data class TeacherScheduleItem(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("assignment_id") val assignmentId: Int,
    @SerializedName("subject_id") val subjectId: Int,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("subject_code") val subjectCode: String,
    @SerializedName("department_id") val departmentId: Int,
    @SerializedName("department_name") val departmentName: String,
    @SerializedName("semester") val semester: Int,
    @SerializedName("section") val section: String?,
    @SerializedName("start_time") val startTime: String,
    @SerializedName("end_time") val endTime: String,
    @SerializedName("classroom") val classroom: String?,
    @SerializedName("building") val building: String?,
    @SerializedName("is_active") val isActive: Boolean,
    @SerializedName("session_started") val sessionStarted: Boolean,
    @SerializedName("students_present") val studentsPresent: Int
)

data class RecentSession(
    @SerializedName("date") val date: String,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("department_name") val departmentName: String,
    @SerializedName("total_students") val totalStudents: Int,
    @SerializedName("present") val present: Int,
    @SerializedName("attendance_rate") val attendanceRate: Double
)

data class TeacherActiveSession(
    @SerializedName("session_id") val sessionId: Int,
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("latitude") val latitude: Double,
    @SerializedName("longitude") val longitude: Double,
    @SerializedName("started_at") val startedAt: String,
    @SerializedName("students_present") val studentsPresent: Int
)

data class TeacherScheduleResponse(
    @SerializedName("schedules") val schedules: List<TeacherScheduleItem>,
    @SerializedName("current_class") val currentClass: TeacherScheduleItem?
)

// Teacher Profile Response
data class TeacherProfileResponse(
    @SerializedName("profile") val profile: TeacherProfileFull
)

data class TeacherProfileFull(
    @SerializedName("id") val id: Int,
    @SerializedName("employee_id") val employeeId: String,
    @SerializedName("full_name") val fullName: String,
    @SerializedName("email") val email: String,
    @SerializedName("phone") val phone: String?,
    @SerializedName("department_id") val departmentId: Int?,
    @SerializedName("department_name") val departmentName: String?,
    @SerializedName("designation") val designation: String?,
    @SerializedName("qualification") val qualification: String?,
    @SerializedName("joining_date") val joiningDate: String?,
    @SerializedName("profile_image") val profileImage: String?
)

data class UpdateTeacherProfileRequest(
    @SerializedName("full_name") val fullName: String? = null,
    @SerializedName("phone") val phone: String? = null,
    @SerializedName("department_id") val departmentId: Int? = null,
    @SerializedName("designation") val designation: String? = null,
    @SerializedName("qualification") val qualification: String? = null,
    @SerializedName("joining_date") val joiningDate: String? = null
)

// ==================== Class Session Models ====================

data class StartClassRequest(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("latitude") val latitude: Double,
    @SerializedName("longitude") val longitude: Double
)

data class ClassSessionResponse(
    @SerializedName("session_id") val sessionId: Int,
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("department_name") val departmentName: String,
    @SerializedName("latitude") val latitude: Double,
    @SerializedName("longitude") val longitude: Double,
    @SerializedName("started_at") val startedAt: String,
    @SerializedName("is_active") val isActive: Boolean
)

data class EndClassRequest(
    @SerializedName("session_id") val sessionId: Int
)

data class EndClassResponse(
    @SerializedName("session_id") val sessionId: Int,
    @SerializedName("ended_at") val endedAt: String,
    @SerializedName("total_students") val totalStudents: Int,
    @SerializedName("present") val present: Int,
    @SerializedName("absent") val absent: Int,
    @SerializedName("auto_marked_absent") val autoMarkedAbsent: Int = 0,
    @SerializedName("attendance_percentage") val attendancePercentage: Double
)

data class UpdateLocationRequest(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("latitude") val latitude: Double,
    @SerializedName("longitude") val longitude: Double
)

data class ClassAttendanceResponse(
    @SerializedName("schedule") val schedule: ClassScheduleInfo,
    @SerializedName("summary") val summary: ClassAttendanceSummary,
    @SerializedName("students") val students: List<StudentAttendanceRecord>
)

data class ClassScheduleInfo(
    @SerializedName("schedule_id") val scheduleId: Int,
    @SerializedName("subject_name") val subjectName: String,
    @SerializedName("department_name") val departmentName: String,
    @SerializedName("date") val date: String,
    @SerializedName("start_time") val startTime: String,
    @SerializedName("end_time") val endTime: String
)

data class ClassAttendanceSummary(
    @SerializedName("total_students") val totalStudents: Int,
    @SerializedName("present") val present: Int,
    @SerializedName("absent") val absent: Int,
    @SerializedName("attendance_rate") val attendanceRate: Double
)

data class StudentAttendanceRecord(
    @SerializedName("student_id") val studentId: Int,
    @SerializedName("roll_number") val rollNumber: String,
    @SerializedName("full_name") val fullName: String,
    @SerializedName("status") val status: String,
    @SerializedName("attendance_time") val attendanceTime: String?,
    @SerializedName("face_confidence") val faceConfidence: Double?,
    @SerializedName("distance_meters") val distanceMeters: Double?,
    @SerializedName("verification_status") val verificationStatus: String?
)
