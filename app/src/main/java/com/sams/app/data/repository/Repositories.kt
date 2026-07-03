package com.sams.app.data.repository

import android.graphics.Bitmap
import android.util.Base64
import android.util.Log
import com.sams.app.data.api.ApiService
import com.sams.app.data.models.*
import java.io.ByteArrayOutputStream
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AuthRepository @Inject constructor(
    private val apiService: ApiService,
    private val sessionManager: SessionManager
) {
    suspend fun registerCurrentDeviceToken(deviceToken: String): Result<Unit> {
        return try {
            apiService.registerFcmToken(
                FcmRegisterRequest(
                    token = deviceToken,
                    deviceType = "android"
                )
            )
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

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
                        apiService.registerFcmToken(
                            FcmRegisterRequest(
                                token = deviceToken,
                                deviceType = "android"
                            )
                        )
                        Log.d("AuthRepository", "FCM token registered after login")
                    } catch (e: Exception) {
                        Log.e("AuthRepository", "Failed to register FCM token: ${e.message}")
                    }
                }
            }
            Result.success(response)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun logout(): Result<Unit> {
        return try {
            apiService.logout()
            sessionManager.clearSession()
            Result.success(Unit)
        } catch (e: Exception) {
            sessionManager.clearSession()
            Result.failure(e)
        }
    }
    
    suspend fun isLoggedInAsync(): Boolean {
        return sessionManager.getTokenAsync() != null
    }
    
    suspend fun getUserRoleAsync(): String? {
        return sessionManager.getUserAsync()?.role
    }
    
    @Deprecated("Use isLoggedInAsync instead")
    fun isLoggedIn(): Boolean = false
    
    @Deprecated("Use getUserRoleAsync instead")
    fun getUserRole(): String? = null
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
            Log.d("StudentRepo", "Fetching student schedule...")
            val response = apiService.getStudentSchedule()
            
            // Extract schedules from nested data structure
            // API returns: { data: { data: { schedules: [...] } } }
            val schedulesList = when {
                response.data?.data?.schedules?.isNotEmpty() == true -> {
                    Log.d("StudentRepo", "Using nested data.data.schedules: ${response.data.data.schedules.size} items")
                    response.data.data.schedules
                }
                response.data?.schedules?.isNotEmpty() == true -> {
                    Log.d("StudentRepo", "Using data.schedules: ${response.data.schedules.size} items")
                    response.data.schedules
                }
                else -> emptyList()
            }
            
            Log.d("StudentRepo", "Schedule response received: success=${response.success}, scheduleCount=${schedulesList.size}")
            
            if (response.success) {
                // Group schedules by day_of_week when any rows exist, otherwise return an empty success.
                val groupedSchedules = schedulesList.groupBy {
                    it.dayOfWeek.ifEmpty { "Other" }
                }

                if (groupedSchedules.isNotEmpty()) {
                    Log.d("StudentRepo", "Schedules grouped by day: ${groupedSchedules.keys}")
                } else {
                    Log.d("StudentRepo", "No schedules returned for this user; keeping success state with an empty schedule map")
                }

                Result.success(StudentScheduleResponse(
                    success = true,
                    schedules = groupedSchedules,
                    message = response.message ?: if (schedulesList.isEmpty()) "No schedules available" else null
                ))
            } else {
                val msg = response.message ?: "No schedules available or failed to load schedule"
                Log.e("StudentRepo", msg)
                Result.failure(Exception(msg))
            }
        } catch (e: Exception) {
            Log.e("StudentRepo", "Error fetching schedule", e)
            e.printStackTrace()
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
        faceEmbedding: String? = null,
        gpsAccuracy: Float = 0f
    ): Result<MarkAttendanceResponse> {
        return try {
            val response = apiService.markAttendance(
                MarkAttendanceRequest(
                    scheduleId = scheduleId,
                    latitude = latitude,
                    longitude = longitude,
                    faceConfidence = faceConfidence,
                    faceEmbedding = faceEmbedding,
                    gpsAccuracy = gpsAccuracy
                )
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
    
    suspend fun registerFace(embeddingString: String, faceBitmap: Bitmap? = null): Result<FaceRegistrationResponse> {
        return try {
            val response = apiService.registerFace(
                FaceRegistrationRequest(
                    faceEmbedding = embeddingString
                )
            )
            if (response.success) {
                Result.success(response)
            } else {
                Result.failure(Exception(response.message ?: "Failed to register face"))
            }
        } catch (e: Exception) {
            Log.e("StudentRepo", "Error registering face: ${e.message}")
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
    
    suspend fun getNotificationPreferences(): Result<Map<String, Boolean>> {
        return try {
            val response = apiService.getNotificationPreferences()
            val preferences = response.data?.preferences
            if (response.success && preferences != null) {
                Result.success(preferences)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load notification preferences"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun updateNotificationPreferences(preferences: Map<String, Boolean>): Result<Map<String, Boolean>> {
        return try {
            val response = apiService.updateNotificationPreferences(preferences)
            val updatedPreferences = response.data?.preferences
            if (response.success && updatedPreferences != null) {
                Result.success(updatedPreferences)
            } else {
                Result.failure(Exception(response.message ?: "Failed to update notification preferences"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getNotifications(unreadOnly: Boolean = false): Result<List<Notification>> {
        return try {
            val response = apiService.getNotifications(unreadOnly)
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load notifications"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun markNotificationAsRead(notificationId: Int): Result<Unit> {
        return try {
            val response = apiService.markNotificationAsRead(MarkNotificationReadRequest(notificationId = notificationId))
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception(response.message ?: "Failed to mark notification as read"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    // ✅ Multi-Check Attendance Methods
    suspend fun getActiveAttendanceChecks(): Result<ActiveAttendanceChecksData> {
        return try {
            val response = apiService.getActiveAttendanceChecks()
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load active checks"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun respondAttendanceCheck(
        checkPointId: Int,
        latitude: Double,
        longitude: Double,
        faceConfidence: Double,
        gpsAccuracy: Float = 0f,
        deviceInfo: String? = null
    ): Result<AttendanceCheckResponseData> {
        return try {
            val response = apiService.respondAttendanceCheck(
                RespondAttendanceCheckRequest(
                    checkPointId = checkPointId,
                    latitude = latitude,
                    longitude = longitude,
                    faceConfidence = faceConfidence,
                    gpsAccuracy = gpsAccuracy,
                    deviceInfo = deviceInfo
                )
            )
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to respond to check"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getContinuousMonitoringConfig(sessionId: Int): Result<ContinuousMonitoringConfig> {
        return try {
            val response = apiService.getContinuousMonitoringConfig(sessionId)
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load configuration"))
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

    suspend fun getSchedules(): Result<List<TeacherWeeklyScheduleItem>> {
        return try {
            Log.d("TeacherRepo", "Fetching teacher schedules...")
            val response = apiService.getTeacherSchedule() // returns TeacherScheduleResponse
            Log.d("TeacherRepo", "Schedule response received: success=${response.success}, scheduleCount=${response.data?.schedules?.size ?: 0}")
            
            if (response.success && response.data != null) {
                Log.d("TeacherRepo", "Schedules retrieved: ${response.data.schedules.map { "${it.subjectName} - ${it.dayOfWeek} ${it.startTime}-${it.endTime}" }}")
                Result.success(response.data.schedules) // List<TeacherWeeklyScheduleItem>
            } else {
                val msg = response.message ?: "Failed to load schedules"
                Log.e("TeacherRepo", msg)
                Result.failure(Exception(msg))
            }
        } catch (e: Exception) {
            Log.e("TeacherRepo", "Error fetching schedules", e)
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
        } catch (e: retrofit2.HttpException) {
            when (e.code()) {
                400 -> Result.failure(Exception("Session already ended"))
                404 -> Result.failure(Exception("Session not found"))
                500 -> Result.failure(Exception("Server error. Please try again."))
                else -> Result.failure(Exception("Error ${e.code()}"))
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
    
    // ✅ Multi-Check Attendance Methods
    suspend fun triggerAttendanceCheck(sessionId: Int, windowMinutes: Int = 5): Result<AttendanceCheckPoint> {
        return try {
            val response = apiService.triggerAttendanceCheck(
                TriggerAttendanceCheckRequest(sessionId, windowMinutes)
            )
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to trigger attendance check"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun finalizeAttendance(sessionId: Int): Result<FinalizeAttendanceData> {
        return try {
            val response = apiService.finalizeAttendance(
                FinalizeAttendanceRequest(sessionId)
            )
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to finalize attendance"))
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
    suspend fun getNotifications(unreadOnly: Boolean = false): Result<List<Notification>> {
        return try {
            val response = apiService.getNotifications(unreadOnly)
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to load notifications"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun markAsRead(notificationId: Int): Result<Unit> {
        return try {
            val response = apiService.markNotificationAsRead(
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
            val response = apiService.markNotificationAsRead(
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

// ==================== Settings Repository ====================

@Singleton
class SettingsRepository @Inject constructor(
    private val apiService: ApiService
) {
    private var cachedSettings: AppSettingsConfig? = null
    private var lastFetchTime: Long = 0
    private val CACHE_DURATION = 5 * 60 * 1000L // 5 minutes

    suspend fun getAppSettings(forceRefresh: Boolean = false): Result<AppSettingsConfig> {
        return try {
            // Check if cache is valid and not forced refresh
            if (!forceRefresh && cachedSettings != null && System.currentTimeMillis() - lastFetchTime < CACHE_DURATION) {
                return Result.success(cachedSettings!!)
            }

            val response = apiService.getAppSettings(type = "all")
            if (response.success && response.data != null) {
                cachedSettings = response.data
                lastFetchTime = System.currentTimeMillis()
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to fetch settings"))
            }
        } catch (e: Exception) {
            // Return cached settings if available, otherwise fail
            if (cachedSettings != null) {
                Result.success(cachedSettings!!)
            } else {
                Result.failure(e)
            }
        }
    }

    suspend fun getAttendanceSettings(): Result<AttendanceSettings> {
        return try {
            val response = apiService.getAttendanceSettings(type = "attendance")
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to fetch attendance settings"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getSystemSettings(): Result<SystemSettings> {
        return try {
            val response = apiService.getSystemSettings(type = "academic")
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to fetch system settings"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getAppInfo(): Result<AppInfo> {
        return try {
            val response = apiService.getAppInfo()
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to fetch app info"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    fun getCachedSettings(): AppSettingsConfig? = cachedSettings

}
