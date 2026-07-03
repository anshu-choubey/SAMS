package com.sams.app.data.api

import com.sams.app.data.models.*
import retrofit2.http.*

interface ApiService {
    
    // ==================== Auth ====================
    
    @POST("api/public/login.php")
    suspend fun login(@Body request: LoginRequest): LoginResponse
    
    @POST("api/public/logout.php")
    suspend fun logout(): ApiResponse<Unit>
    
    // ==================== Student ====================
    
    @GET("api/student/dashboard.php")
    suspend fun getStudentDashboard(): StudentDashboardResponse
    
    @GET("api/student/schedule.php")
    suspend fun getStudentSchedule(): StudentScheduleApiResponse
    
    @GET("api/student/attendance-history.php")
    suspend fun getAttendanceHistory(
        @Query("subject_id") subjectId: Int? = null,
        @Query("start_date") startDate: String? = null,
        @Query("end_date") endDate: String? = null
    ): AttendanceHistoryResponse
    
    @POST("api/student/mark-attendance.php")
    suspend fun markAttendance(@Body request: MarkAttendanceRequest): MarkAttendanceResponse
    
    // ✅ Multi-Check Attendance for Students
    @GET("api/student/active-attendance-checks.php")
    suspend fun getActiveAttendanceChecks(): ActiveAttendanceChecksResponse
    
    @POST("api/student/respond-attendance-check.php")
    suspend fun respondAttendanceCheck(@Body request: RespondAttendanceCheckRequest): RespondAttendanceCheckResponse
    
    @GET("api/student/continuous-monitoring-config.php")
    suspend fun getContinuousMonitoringConfig(
        @Query("session_id") sessionId: Int
    ): ApiResponse<ContinuousMonitoringConfig>
    
    @POST("api/student/register-face.php")
    suspend fun registerFace(@Body request: FaceRegistrationRequest): FaceRegistrationResponse

    @GET("api/student/profile.php")
    suspend fun getStudentProfile(): StudentProfileResponse
    
    @PUT("api/student/profile.php")
    suspend fun updateStudentProfile(@Body request: UpdateStudentProfileRequest): StudentProfileResponse
    
    @GET("api/student/verify-face.php")
    suspend fun getStoredFaceEmbedding(): ApiResponse<Map<String, String>>
    
    // ==================== Teacher ====================
    
    @GET("api/teacher/dashboard.php")
    suspend fun getTeacherDashboard(): TeacherDashboardResponse
    
    @GET("api/teacher/schedules.php")
    suspend fun getTeacherSchedule(): TeacherScheduleResponse
    

    @POST("api/teacher/start-class.php")
    suspend fun startSession(@Body request: StartSessionRequest): ApiResponse<StartSessionData>

    @POST("api/teacher/end-class.php")
    suspend fun endSession(@Body request: EndSessionRequest): EndSessionResponse // ✅ dedicated type
    
    // ✅ Multi-Check Attendance for Teachers
    @POST("api/teacher/trigger-attendance-check.php")
    suspend fun triggerAttendanceCheck(@Body request: TriggerAttendanceCheckRequest): TriggerAttendanceCheckResponse
    
    @POST("api/teacher/finalize-attendance.php")
    suspend fun finalizeAttendance(@Body request: FinalizeAttendanceRequest): FinalizeAttendanceResponse

    @GET("api/teacher/class-attendance.php")
    suspend fun getClassAttendance(
        @Query("schedule_id") scheduleId: Int,
        @Query("date") date: String? = null
    ): ClassAttendanceResponse
    
    @POST("api/teacher/manual-attendance.php")
    suspend fun markManualAttendance(@Body request: ManualAttendanceRequest): ApiResponse<Unit>
    
    // ==================== Notifications ====================
    
    @GET("api/notifications/list.php")
    suspend fun getNotifications(
        @Query("unread_only") unreadOnly: Boolean = false
    ): ApiResponse<List<Notification>>
    
    @POST("api/notifications/mark-read.php")
    suspend fun markNotificationAsRead(@Body request: MarkNotificationReadRequest): ApiResponse<Unit>
    
    @GET("api/notifications/preferences.php")
    suspend fun getNotificationPreferences(): ApiResponse<NotificationPreferencesResponse>
    
    @POST("api/notifications/preferences.php")
    suspend fun updateNotificationPreferences(@Body preferences: Map<String, Boolean>): ApiResponse<NotificationPreferencesResponse>
    
    // ==================== FCM ====================
    
    @POST("api/fcm/register.php")
    suspend fun registerFcmToken(@Body request: FcmRegisterRequest): ApiResponse<Unit>
    
    @POST("api/fcm/remove.php")
    suspend fun removeFcmToken(@Body request: FcmRegisterRequest): ApiResponse<Unit>

    // ==================== Settings ====================

    @GET("api/public/settings.php")
    suspend fun getAppSettings(
        @Query("type") type: String = "all"
    ): ApiResponse<AppSettingsConfig>
    
    @GET("api/admin/attendance-settings.php")
    suspend fun getAttendanceAdminSettings(): ApiResponse<Map<String, String>>
    
    @POST("api/admin/attendance-settings.php")
    suspend fun updateAttendanceAdminSettings(@Body settings: Map<String, String>): ApiResponse<Map<String, Any>>

    @GET("api/public/settings.php")
    suspend fun getAttendanceSettings(
        @Query("type") type: String = "attendance"
    ): ApiResponse<AttendanceSettings>

    @GET("api/public/settings.php")
    suspend fun getSystemSettings(
        @Query("type") type: String = "academic"
    ): ApiResponse<SystemSettings>

    @GET("api/public/settings.php")
    suspend fun getAppInfo(): ApiResponse<AppInfo>
}
