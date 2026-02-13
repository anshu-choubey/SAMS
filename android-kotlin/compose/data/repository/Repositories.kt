package com.sams.app.data.repository

import com.sams.app.data.api.ApiService
import com.sams.app.data.models.*
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AuthRepository @Inject constructor(
    private val apiService: ApiService,
    private val sessionManager: SessionManager
) {
    suspend fun login(email: String, password: String, deviceToken: String? = null): Result<LoginResponse> {
        return try {
            val response = apiService.login(LoginRequest(email, password, deviceToken))
            val loginData = response.data
            if (response.success && loginData != null) {
                // Convert LoginUser to User for session storage
                val user = User(
                    id = loginData.user.id,
                    email = loginData.user.email,
                    fullName = loginData.user.fullName,
                    role = loginData.user.role,
                    departmentId = loginData.profile?.departmentId,
                    departmentName = loginData.profile?.departmentName
                )
                sessionManager.saveSession(loginData.sessionId, user)
                // Register FCM token after successful login
                if (deviceToken != null) {
                    try {
                        apiService.registerFcmToken(FcmRegisterRequest(deviceToken))
                    } catch (_: Exception) { }
                }
            }
            Result.success(response)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun logout(deviceToken: String? = null): Result<Unit> {
        return try {
            // Remove FCM token before logout
            if (deviceToken != null) {
                try {
                    apiService.removeFcmToken(FcmRegisterRequest(deviceToken))
                } catch (_: Exception) { }
            }
            apiService.logout()
            sessionManager.clearSession()
            Result.success(Unit)
        } catch (e: Exception) {
            sessionManager.clearSession()
            Result.failure(e)
        }
    }
    
    fun isLoggedIn(): Boolean = sessionManager.getToken() != null
    
    fun getCurrentUser(): User? = sessionManager.getUser()
    
    fun getUserRole(): String? = sessionManager.getUser()?.role
}

@Singleton
class StudentRepository @Inject constructor(
    private val apiService: ApiService
) {
    suspend fun getDashboard(): Result<StudentDashboardData> {
        return try {
            val response = apiService.getStudentDashboard()
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load dashboard"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getSchedule(): Result<StudentScheduleResponse> {
        return try {
            val response = apiService.getStudentSchedule()
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load schedule"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getAttendanceHistory(
        subjectId: Int? = null,
        startDate: String? = null,
        endDate: String? = null
    ): Result<AttendanceHistoryResponse> {
        return try {
            val response = apiService.getAttendanceHistory(subjectId, startDate, endDate)
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load history"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun markAttendance(
        scheduleId: Int,
        latitude: Double,
        longitude: Double,
        faceConfidence: Double,
        faceEmbedding: String? = null
    ): Result<MarkAttendanceResponse> {
        return try {
            val response = apiService.markAttendance(
                MarkAttendanceRequest(scheduleId, latitude, longitude, faceConfidence, faceEmbedding)
            )
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception(response.message ?: "Failed to mark attendance"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun registerFace(embedding: String): Result<FaceRegistrationResponse> {
        return try {
            val response = apiService.registerFace(FaceRegistrationRequest(embedding))
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception(response.message ?: "Failed to register face"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getProfile(): Result<StudentProfileFull> {
        return try {
            val response = apiService.getStudentProfile()
            if (response.success && response.data?.profile != null) {
                Result.success(response.data.profile)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load profile"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun updateProfile(request: UpdateStudentProfileRequest): Result<StudentProfileFull> {
        return try {
            val response = apiService.updateStudentProfile(request)
            if (response.success && response.data?.profile != null) {
                Result.success(response.data.profile)
            } else {
                Result.failure(Exception(response.message ?: "Failed to update profile"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getStoredFaceEmbedding(): Result<String?> {
        return try {
            val response = apiService.getStoredFaceEmbedding()
            if (response.success && response.data != null) {
                Result.success(response.data["face_embedding"])
            } else {
                Result.success(null) // No face registered yet
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}

@Singleton
class TeacherRepository @Inject constructor(
    private val apiService: ApiService
) {
    suspend fun getDashboard(): Result<TeacherDashboardData> {
        return try {
            val response = apiService.getTeacherDashboard()
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load dashboard"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getSchedule(): Result<TeacherScheduleResponse> {
        return try {
            val response = apiService.getTeacherSchedule()
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load schedule"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun startSession(scheduleId: Int, latitude: Double, longitude: Double): Result<StartSessionData> {
        return try {
            val response = apiService.startSession(StartSessionRequest(scheduleId, latitude, longitude))
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to start session"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun endSession(sessionId: Int): Result<EndSessionData?> {
        return try {
            val response = apiService.endSession(EndSessionRequest(sessionId))
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to end session"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getClassAttendance(scheduleId: Int, date: String? = null): Result<ClassAttendanceResponse> {
        return try {
            val response = apiService.getClassAttendance(scheduleId, date)
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load attendance"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun markManualAttendance(scheduleId: Int, studentId: Int, status: String): Result<Unit> {
        return try {
            val response = apiService.markManualAttendance(
                ManualAttendanceRequest(scheduleId, studentId, status)
            )
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Failed to mark attendance"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}

@Singleton
class NotificationRepository @Inject constructor(
    private val apiService: ApiService
) {
    suspend fun getNotifications(): Result<NotificationListResponse> {
        return try {
            val response = apiService.getNotifications()
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load notifications"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun markAsRead(notificationId: Int): Result<Unit> {
        return try {
            val response = apiService.markNotificationRead(
                MarkNotificationReadRequest(notificationId = notificationId)
            )
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Failed to mark as read"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun markAllAsRead(): Result<Unit> {
        return try {
            val response = apiService.markNotificationRead(
                MarkNotificationReadRequest(markAll = true)
            )
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Failed to mark all as read"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
