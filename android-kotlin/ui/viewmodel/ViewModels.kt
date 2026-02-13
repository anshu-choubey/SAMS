package com.sams.app.ui.viewmodel

import android.graphics.Bitmap
import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sams.app.data.models.*
import com.sams.app.data.repository.StudentRepository
import com.sams.app.utils.FaceDetectionHelper
import com.sams.app.utils.FaceEmbeddingResult
import com.sams.app.utils.LocationHelper
import com.sams.app.utils.LocationResult
import kotlinx.coroutines.launch

/**
 * Student ViewModel
 * Manages UI state for student features
 */
class StudentViewModel : ViewModel() {
    
    private val repository = StudentRepository()
    private var faceHelper: FaceDetectionHelper? = null
    private var locationHelper: LocationHelper? = null
    
    // Dashboard
    private val _dashboardState = MutableLiveData<UiState<StudentDashboardData>>()
    val dashboardState: LiveData<UiState<StudentDashboardData>> = _dashboardState
    
    // Schedule
    private val _scheduleState = MutableLiveData<UiState<StudentScheduleResponse>>()
    val scheduleState: LiveData<UiState<StudentScheduleResponse>> = _scheduleState
    
    // Attendance History
    private val _historyState = MutableLiveData<UiState<AttendanceHistoryResponse>>()
    val historyState: LiveData<UiState<AttendanceHistoryResponse>> = _historyState
    
    // Profile
    private val _profileState = MutableLiveData<UiState<StudentProfileFull>>()
    val profileState: LiveData<UiState<StudentProfileFull>> = _profileState
    
    // Face Registration
    private val _faceRegistrationState = MutableLiveData<UiState<FaceRegistrationResponse>>()
    val faceRegistrationState: LiveData<UiState<FaceRegistrationResponse>> = _faceRegistrationState
    
    // Attendance Marking
    private val _attendanceState = MutableLiveData<UiState<MarkAttendanceResponse>>()
    val attendanceState: LiveData<UiState<MarkAttendanceResponse>> = _attendanceState
    
    fun initHelpers(faceHelper: FaceDetectionHelper, locationHelper: LocationHelper) {
        this.faceHelper = faceHelper
        this.locationHelper = locationHelper
    }
    
    fun loadDashboard() {
        _dashboardState.value = UiState.Loading
        viewModelScope.launch {
            repository.getDashboard()
                .onSuccess { _dashboardState.postValue(UiState.Success(it)) }
                .onFailure { _dashboardState.postValue(UiState.Error(it.message ?: "Unknown error")) }
        }
    }
    
    fun loadSchedule() {
        _scheduleState.value = UiState.Loading
        viewModelScope.launch {
            repository.getSchedule()
                .onSuccess { _scheduleState.postValue(UiState.Success(it)) }
                .onFailure { _scheduleState.postValue(UiState.Error(it.message ?: "Unknown error")) }
        }
    }
    
    fun loadAttendanceHistory(subjectId: Int? = null, startDate: String? = null, endDate: String? = null) {
        _historyState.value = UiState.Loading
        viewModelScope.launch {
            repository.getAttendanceHistory(subjectId, startDate, endDate)
                .onSuccess { _historyState.postValue(UiState.Success(it)) }
                .onFailure { _historyState.postValue(UiState.Error(it.message ?: "Unknown error")) }
        }
    }
    
    fun loadProfile() {
        _profileState.value = UiState.Loading
        viewModelScope.launch {
            repository.getProfile()
                .onSuccess { _profileState.postValue(UiState.Success(it)) }
                .onFailure { _profileState.postValue(UiState.Error(it.message ?: "Unknown error")) }
        }
    }
    
    fun updateProfile(request: UpdateStudentProfileRequest) {
        _profileState.value = UiState.Loading
        viewModelScope.launch {
            repository.updateProfile(request)
                .onSuccess { _profileState.postValue(UiState.Success(it)) }
                .onFailure { _profileState.postValue(UiState.Error(it.message ?: "Unknown error")) }
        }
    }
    
    /**
     * Register face from captured bitmap
     */
    fun registerFace(bitmap: Bitmap) {
        val helper = faceHelper ?: run {
            _faceRegistrationState.value = UiState.Error("Face detection not initialized")
            return
        }
        
        _faceRegistrationState.value = UiState.Loading
        viewModelScope.launch {
            try {
                val result = helper.extractFaceEmbedding(bitmap)
                
                when (result) {
                    is FaceEmbeddingResult.Success -> {
                        val encodedEmbedding = helper.encodeEmbedding(result.embedding)
                        repository.registerFace(encodedEmbedding)
                            .onSuccess { _faceRegistrationState.postValue(UiState.Success(it)) }
                            .onFailure { _faceRegistrationState.postValue(UiState.Error(it.message ?: "Registration failed")) }
                    }
                    is FaceEmbeddingResult.ValidationFailed -> {
                        val message = when (result.reason) {
                            is com.sams.app.utils.FaceValidationResult.NoFaceDetected -> "No face detected"
                            is com.sams.app.utils.FaceValidationResult.MultipleFacesDetected -> "Multiple faces detected"
                            is com.sams.app.utils.FaceValidationResult.FaceNotFrontal -> "Please face the camera directly"
                            else -> "Face validation failed"
                        }
                        _faceRegistrationState.postValue(UiState.Error(message))
                    }
                }
            } catch (e: Exception) {
                _faceRegistrationState.postValue(UiState.Error(e.message ?: "Face registration failed"))
            }
        }
    }
    
    /**
     * Mark attendance with GPS + Face verification
     */
    fun markAttendance(scheduleId: Int, bitmap: Bitmap, teacherLat: Double, teacherLon: Double) {
        val fHelper = faceHelper ?: run {
            _attendanceState.value = UiState.Error("Face detection not initialized")
            return
        }
        
        val lHelper = locationHelper ?: run {
            _attendanceState.value = UiState.Error("Location service not initialized")
            return
        }
        
        _attendanceState.value = UiState.Loading
        viewModelScope.launch {
            try {
                // Step 1: Get current location
                when (val locationResult = lHelper.getCurrentLocation()) {
                    is LocationResult.Success -> {
                        val studentLat = locationResult.location.latitude
                        val studentLon = locationResult.location.longitude
                        
                        // Step 2: Check GPS proximity
                        val proximity = lHelper.isWithinProximity(
                            studentLat, studentLon, teacherLat, teacherLon
                        )
                        
                        if (!proximity.isWithin) {
                            _attendanceState.postValue(
                                UiState.Error("You are not within classroom proximity (${proximity.distanceMeters.toInt()}m away)")
                            )
                            return@launch
                        }
                        
                        // Step 3: Extract face embedding
                        val faceResult = fHelper.extractFaceEmbedding(bitmap)
                        
                        when (faceResult) {
                            is FaceEmbeddingResult.Success -> {
                                // Step 4: Get stored embedding and verify
                                repository.getStoredFaceEmbedding()
                                    .onSuccess { storedData ->
                                        val storedEmbedding = fHelper.decodeEmbedding(storedData.storedEmbedding)
                                        val confidence = fHelper.verifyFace(faceResult.embedding, storedEmbedding)
                                        
                                        if (confidence < FaceDetectionHelper.FACE_CONFIDENCE_THRESHOLD) {
                                            _attendanceState.postValue(
                                                UiState.Error("Face verification failed (${confidence.toInt()}% confidence)")
                                            )
                                            return@onSuccess
                                        }
                                        
                                        // Step 5: Mark attendance
                                        repository.markAttendance(
                                            scheduleId, studentLat, studentLon, confidence.toDouble()
                                        ).onSuccess {
                                            _attendanceState.postValue(UiState.Success(it))
                                        }.onFailure {
                                            _attendanceState.postValue(UiState.Error(it.message ?: "Failed to mark attendance"))
                                        }
                                    }
                                    .onFailure {
                                        _attendanceState.postValue(UiState.Error(it.message ?: "Failed to verify face"))
                                    }
                            }
                            is FaceEmbeddingResult.ValidationFailed -> {
                                _attendanceState.postValue(UiState.Error("Face validation failed"))
                            }
                        }
                    }
                    is LocationResult.PermissionDenied -> {
                        _attendanceState.postValue(UiState.Error("Location permission required"))
                    }
                    is LocationResult.LocationUnavailable -> {
                        _attendanceState.postValue(UiState.Error("Unable to get your location"))
                    }
                    is LocationResult.Error -> {
                        _attendanceState.postValue(UiState.Error(locationResult.message))
                    }
                }
            } catch (e: Exception) {
                _attendanceState.postValue(UiState.Error(e.message ?: "Attendance marking failed"))
            }
        }
    }
    
    override fun onCleared() {
        super.onCleared()
        faceHelper?.close()
    }
}

/**
 * Teacher ViewModel
 */
class TeacherViewModel : ViewModel() {
    
    private val repository = com.sams.app.data.repository.TeacherRepository()
    private var locationHelper: LocationHelper? = null
    
    // Dashboard
    private val _dashboardState = MutableLiveData<UiState<TeacherDashboardData>>()
    val dashboardState: LiveData<UiState<TeacherDashboardData>> = _dashboardState
    
    // Schedule
    private val _scheduleState = MutableLiveData<UiState<TeacherScheduleResponse>>()
    val scheduleState: LiveData<UiState<TeacherScheduleResponse>> = _scheduleState
    
    // Profile
    private val _profileState = MutableLiveData<UiState<TeacherProfileFull>>()
    val profileState: LiveData<UiState<TeacherProfileFull>> = _profileState
    
    // Class Session
    private val _sessionState = MutableLiveData<UiState<ClassSessionResponse>>()
    val sessionState: LiveData<UiState<ClassSessionResponse>> = _sessionState
    
    // End Class
    private val _endClassState = MutableLiveData<UiState<EndClassResponse>>()
    val endClassState: LiveData<UiState<EndClassResponse>> = _endClassState
    
    // Class Attendance
    private val _classAttendanceState = MutableLiveData<UiState<ClassAttendanceResponse>>()
    val classAttendanceState: LiveData<UiState<ClassAttendanceResponse>> = _classAttendanceState
    
    fun initLocationHelper(helper: LocationHelper) {
        this.locationHelper = helper
    }
    
    fun loadDashboard() {
        _dashboardState.value = UiState.Loading
        viewModelScope.launch {
            repository.getDashboard()
                .onSuccess { _dashboardState.postValue(UiState.Success(it)) }
                .onFailure { _dashboardState.postValue(UiState.Error(it.message ?: "Unknown error")) }
        }
    }
    
    fun loadSchedule() {
        _scheduleState.value = UiState.Loading
        viewModelScope.launch {
            repository.getSchedule()
                .onSuccess { _scheduleState.postValue(UiState.Success(it)) }
                .onFailure { _scheduleState.postValue(UiState.Error(it.message ?: "Unknown error")) }
        }
    }
    
    fun loadProfile() {
        _profileState.value = UiState.Loading
        viewModelScope.launch {
            repository.getProfile()
                .onSuccess { _profileState.postValue(UiState.Success(it)) }
                .onFailure { _profileState.postValue(UiState.Error(it.message ?: "Unknown error")) }
        }
    }
    
    fun updateProfile(request: UpdateTeacherProfileRequest) {
        _profileState.value = UiState.Loading
        viewModelScope.launch {
            repository.updateProfile(request)
                .onSuccess { _profileState.postValue(UiState.Success(it)) }
                .onFailure { _profileState.postValue(UiState.Error(it.message ?: "Unknown error")) }
        }
    }
    
    /**
     * Start class session with current GPS location
     */
    fun startClass(scheduleId: Int) {
        val helper = locationHelper ?: run {
            _sessionState.value = UiState.Error("Location service not initialized")
            return
        }
        
        _sessionState.value = UiState.Loading
        viewModelScope.launch {
            when (val locationResult = helper.getCurrentLocation()) {
                is LocationResult.Success -> {
                    repository.startClass(
                        scheduleId,
                        locationResult.location.latitude,
                        locationResult.location.longitude
                    ).onSuccess {
                        _sessionState.postValue(UiState.Success(it))
                    }.onFailure {
                        _sessionState.postValue(UiState.Error(it.message ?: "Failed to start class"))
                    }
                }
                is LocationResult.PermissionDenied -> {
                    _sessionState.postValue(UiState.Error("Location permission required"))
                }
                is LocationResult.LocationUnavailable -> {
                    _sessionState.postValue(UiState.Error("Unable to get your location"))
                }
                is LocationResult.Error -> {
                    _sessionState.postValue(UiState.Error(locationResult.message))
                }
            }
        }
    }
    
    fun endClass(sessionId: Int) {
        _endClassState.value = UiState.Loading
        viewModelScope.launch {
            repository.endClass(sessionId)
                .onSuccess { _endClassState.postValue(UiState.Success(it)) }
                .onFailure { _endClassState.postValue(UiState.Error(it.message ?: "Failed to end class")) }
        }
    }
    
    fun loadClassAttendance(scheduleId: Int, date: String? = null) {
        _classAttendanceState.value = UiState.Loading
        viewModelScope.launch {
            repository.getClassAttendance(scheduleId, date)
                .onSuccess { _classAttendanceState.postValue(UiState.Success(it)) }
                .onFailure { _classAttendanceState.postValue(UiState.Error(it.message ?: "Unknown error")) }
        }
    }
    
    /**
     * Update teacher location during active session
     */
    fun updateLocation(scheduleId: Int) {
        val helper = locationHelper ?: return
        
        viewModelScope.launch {
            when (val locationResult = helper.getCurrentLocation()) {
                is LocationResult.Success -> {
                    repository.updateLocation(
                        scheduleId,
                        locationResult.location.latitude,
                        locationResult.location.longitude
                    )
                }
                else -> { /* Silently fail for location updates */ }
            }
        }
    }
}

/**
 * UI State wrapper
 */
sealed class UiState<out T> {
    object Loading : UiState<Nothing>()
    data class Success<T>(val data: T) : UiState<T>()
    data class Error(val message: String) : UiState<Nothing>()
}
