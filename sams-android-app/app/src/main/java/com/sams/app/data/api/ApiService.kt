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
    suspend fun getStudentSchedule(): StudentScheduleResponse
    
    @GET("api/student/attendance-history.php")
    suspend fun getAttendanceHistory(
        @Query("subject_id") subjectId: Int? = null,
        @Query("start_date") startDate: String? = null,
        @Query("end_date") endDate: String? = null
    ): AttendanceHistoryResponse
    
    @POST("api/student/mark-attendance.php")
    suspend fun markAttendance(@Body request: MarkAttendanceRequest): MarkAttendanceResponse
    
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
    
    @GET("api/teacher/schedule.php")
    suspend fun getTeacherSchedule(@Query("week") week: Boolean = true): TeacherScheduleResponse
    
    @POST("api/teacher/start-class.php")
    suspend fun startSession(@Body request: StartSessionRequest): ApiResponse<StartSessionData>
    
    @POST("api/teacher/end-class.php")
    suspend fun endSession(@Body request: EndSessionRequest): ApiResponse<EndSessionData>
    
    @GET("api/teacher/class-attendance.php")
    suspend fun getClassAttendance(
        @Query("schedule_id") scheduleId: Int,
        @Query("date") date: String? = null
    ): ClassAttendanceResponse
    
    @POST("api/teacher/manual-attendance.php")
    suspend fun markManualAttendance(@Body request: ManualAttendanceRequest): ApiResponse<Unit>
    
    // ==================== Notifications ====================
    
    @GET("api/notifications/list.php")
    suspend fun getNotifications(): NotificationListResponse
    
    @POST("api/notifications/mark-read.php")
    suspend fun markNotificationRead(@Body request: MarkNotificationReadRequest): ApiResponse<Unit>
    
    // ==================== FCM ====================
    
    @POST("api/fcm/register.php")
    suspend fun registerFcmToken(@Body request: FcmRegisterRequest): ApiResponse<Unit>
    
    @POST("api/fcm/remove.php")
    suspend fun removeFcmToken(@Body request: FcmRegisterRequest): ApiResponse<Unit>
}
