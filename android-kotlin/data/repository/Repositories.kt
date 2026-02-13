package com.sams.app.data.repository

import com.sams.app.data.api.ApiClient
import com.sams.app.data.models.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

/**
 * Authentication Repository
 * Handles login, logout, and session management
 */
class AuthRepository {
    
    private val apiService = ApiClient.getApiService()
    
    suspend fun login(email: String, password: String): Result<LoginResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.login(LoginRequest(email, password))
            
            if (response.isSuccessful && response.body()?.success == true) {
                val loginData = response.body()?.data!!
                
                // Store session token
                ApiClient.setSessionToken(loginData.sessionId)
                ApiClient.setUserRole(loginData.user.role)
                
                Result.success(loginData)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Login failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun logout(): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            apiService.logout()
            ApiClient.clearSession()
            Result.success(Unit)
        } catch (e: Exception) {
            ApiClient.clearSession()
            Result.failure(e)
        }
    }
    
    suspend fun registerFcmToken(token: String, deviceName: String? = null): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.registerFcmToken(
                FcmTokenRequest(token, "android", deviceName)
            )
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to register FCM token"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    fun isLoggedIn(): Boolean = ApiClient.isLoggedIn()
    
    fun getUserRole(): String? = ApiClient.getUserRole()
}

/**
 * Student Repository
 * Handles all student-related API calls
 */
class StudentRepository {
    
    private val apiService get() = ApiClient.getApiService()
    
    suspend fun getDashboard(): Result<StudentDashboardData> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getStudentDashboard()
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to fetch dashboard"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getSchedule(): Result<StudentScheduleResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getStudentSchedule()
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to fetch schedule"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getAttendanceHistory(
        subjectId: Int? = null,
        startDate: String? = null,
        endDate: String? = null
    ): Result<AttendanceHistoryResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getStudentAttendanceHistory(subjectId, startDate, endDate)
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to fetch attendance history"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getProfile(): Result<StudentProfileFull> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getStudentProfile()
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data?.profile!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to fetch profile"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun updateProfile(request: UpdateStudentProfileRequest): Result<StudentProfileFull> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.updateStudentProfile(request)
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data?.profile!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to update profile"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun registerFace(embedding: String): Result<FaceRegistrationResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.registerFace(FaceRegistrationRequest(embedding))
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to register face"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getStoredFaceEmbedding(): Result<FaceVerificationResponse> = withContext(Dispatchers.IO) {
        try {
            // Dummy embedding just to trigger the API
            val response = apiService.verifyFace(FaceVerificationRequest(""))
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to get stored face data"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun markAttendance(
        scheduleId: Int,
        latitude: Double,
        longitude: Double,
        faceConfidence: Double
    ): Result<MarkAttendanceResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.markAttendance(
                MarkAttendanceRequest(scheduleId, latitude, longitude, faceConfidence)
            )
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to mark attendance"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}

/**
 * Teacher Repository
 * Handles all teacher-related API calls
 */
class TeacherRepository {
    
    private val apiService get() = ApiClient.getApiService()
    
    suspend fun getDashboard(): Result<TeacherDashboardData> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getTeacherDashboard()
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to fetch dashboard"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getSchedule(): Result<TeacherScheduleResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getTeacherSchedule()
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to fetch schedule"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getProfile(): Result<TeacherProfileFull> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getTeacherProfile()
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data?.profile!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to fetch profile"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun updateProfile(request: UpdateTeacherProfileRequest): Result<TeacherProfileFull> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.updateTeacherProfile(request)
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data?.profile!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to update profile"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun startClass(
        scheduleId: Int,
        latitude: Double,
        longitude: Double
    ): Result<ClassSessionResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.startClass(StartClassRequest(scheduleId, latitude, longitude))
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to start class"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun endClass(sessionId: Int): Result<EndClassResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.endClass(EndClassRequest(sessionId))
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to end class"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun updateLocation(
        scheduleId: Int,
        latitude: Double,
        longitude: Double
    ): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.updateLocation(UpdateLocationRequest(scheduleId, latitude, longitude))
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to update location"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getClassAttendance(
        scheduleId: Int,
        date: String? = null
    ): Result<ClassAttendanceResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getClassAttendance(scheduleId, date)
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to fetch attendance"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}

/**
 * Notification Repository
 * Handles notification-related API calls
 */
class NotificationRepository {
    
    private val apiService get() = ApiClient.getApiService()
    
    suspend fun getNotifications(
        limit: Int = 50,
        offset: Int = 0,
        unreadOnly: Boolean = false
    ): Result<NotificationsResponse> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getNotifications(limit, offset, unreadOnly)
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.data!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to fetch notifications"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun markAsRead(notificationId: Int): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.markNotificationRead(
                MarkNotificationReadRequest(notificationId = notificationId)
            )
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to mark as read"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun markAllAsRead(): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.markNotificationRead(
                MarkNotificationReadRequest(markAll = true)
            )
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to mark all as read"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
