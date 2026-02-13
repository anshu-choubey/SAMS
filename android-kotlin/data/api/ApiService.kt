package com.sams.app.data.api

import com.sams.app.data.models.*
import retrofit2.Response
import retrofit2.http.*

/**
 * SAMS API Service Interface
 * Complete API endpoints for Smart Attendance Management System
 */
interface ApiService {

    // ==================== Authentication ====================
    
    @POST("api/public/login.php")
    suspend fun login(@Body request: LoginRequest): Response<ApiResponse<LoginResponse>>
    
    @POST("api/public/logout.php")
    suspend fun logout(): Response<ApiResponse<Unit>>

    // ==================== FCM Token ====================
    
    @POST("api/fcm/register.php")
    suspend fun registerFcmToken(@Body request: FcmTokenRequest): Response<ApiResponse<Unit>>
    
    @POST("api/fcm/remove.php")
    suspend fun removeFcmToken(@Body request: FcmTokenRemoveRequest): Response<ApiResponse<Unit>>

    // ==================== Student APIs ====================
    
    @GET("api/student/dashboard.php")
    suspend fun getStudentDashboard(): Response<ApiResponse<StudentDashboardData>>
    
    @GET("api/student/schedule.php")
    suspend fun getStudentSchedule(): Response<ApiResponse<StudentScheduleResponse>>
    
    @GET("api/student/attendance-history.php")
    suspend fun getStudentAttendanceHistory(
        @Query("subject_id") subjectId: Int? = null,
        @Query("start_date") startDate: String? = null,
        @Query("end_date") endDate: String? = null
    ): Response<ApiResponse<AttendanceHistoryResponse>>
    
    @GET("api/student/profile.php")
    suspend fun getStudentProfile(): Response<ApiResponse<StudentProfileResponse>>
    
    @PUT("api/student/profile.php")
    suspend fun updateStudentProfile(@Body request: UpdateStudentProfileRequest): Response<ApiResponse<StudentProfileResponse>>
    
    @POST("api/student/register-face.php")
    suspend fun registerFace(@Body request: FaceRegistrationRequest): Response<ApiResponse<FaceRegistrationResponse>>
    
    @POST("api/student/verify-face.php")
    suspend fun verifyFace(@Body request: FaceVerificationRequest): Response<ApiResponse<FaceVerificationResponse>>
    
    @POST("api/student/mark-attendance.php")
    suspend fun markAttendance(@Body request: MarkAttendanceRequest): Response<ApiResponse<MarkAttendanceResponse>>

    // ==================== Teacher APIs ====================
    
    @GET("api/teacher/dashboard.php")
    suspend fun getTeacherDashboard(): Response<ApiResponse<TeacherDashboardData>>
    
    @GET("api/teacher/schedule.php")
    suspend fun getTeacherSchedule(): Response<ApiResponse<TeacherScheduleResponse>>
    
    @GET("api/teacher/profile.php")
    suspend fun getTeacherProfile(): Response<ApiResponse<TeacherProfileResponse>>
    
    @PUT("api/teacher/profile.php")
    suspend fun updateTeacherProfile(@Body request: UpdateTeacherProfileRequest): Response<ApiResponse<TeacherProfileResponse>>
    
    @POST("api/teacher/start-class.php")
    suspend fun startClass(@Body request: StartClassRequest): Response<ApiResponse<ClassSessionResponse>>
    
    @POST("api/teacher/end-class.php")
    suspend fun endClass(@Body request: EndClassRequest): Response<ApiResponse<EndClassResponse>>
    
    @POST("api/teacher/location.php")
    suspend fun updateLocation(@Body request: UpdateLocationRequest): Response<ApiResponse<Unit>>
    
    @GET("api/teacher/class-attendance.php")
    suspend fun getClassAttendance(
        @Query("schedule_id") scheduleId: Int,
        @Query("date") date: String? = null
    ): Response<ApiResponse<ClassAttendanceResponse>>

    // ==================== Notifications ====================
    
    @GET("api/notifications/list.php")
    suspend fun getNotifications(
        @Query("limit") limit: Int = 50,
        @Query("offset") offset: Int = 0,
        @Query("unread_only") unreadOnly: Boolean = false
    ): Response<ApiResponse<NotificationsResponse>>
    
    @POST("api/notifications/mark-read.php")
    suspend fun markNotificationRead(@Body request: MarkNotificationReadRequest): Response<ApiResponse<Unit>>
}
