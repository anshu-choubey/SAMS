package com.sams.app.ui.student

import android.graphics.Bitmap
import androidx.lifecycle.LiveData
import androidx.lifecycle.MutableLiveData
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sams.app.data.models.*
import com.sams.app.data.repository.StudentRepository
import com.sams.app.data.repository.SettingsRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class StudentUiState {
    object Idle : StudentUiState()
    object Loading : StudentUiState()
    data class Success(val data: Any) : StudentUiState()
    data class Error(val message: String) : StudentUiState()
}

sealed class UiState<out T> {
    object Loading : UiState<Nothing>()
    data class Success<T>(val data: T) : UiState<T>()
    data class Error(val message: String) : UiState<Nothing>()
}

@HiltViewModel
class StudentViewModel @Inject constructor(
    private val repository: StudentRepository,
    private val settingsRepository: SettingsRepository
) : ViewModel() {
    
    private val _dashboardState = MutableStateFlow<StudentUiState>(StudentUiState.Idle)
    val dashboardState: StateFlow<StudentUiState> = _dashboardState.asStateFlow()
    
    private val _isRefreshing = MutableStateFlow(false)
    val isRefreshing: StateFlow<Boolean> = _isRefreshing.asStateFlow()
    
    private val _lastRefreshTime = MutableStateFlow(0L)
    val lastRefreshTime: StateFlow<Long> = _lastRefreshTime.asStateFlow()
    
    private val _scheduleState = MutableStateFlow<StudentUiState>(StudentUiState.Idle)
    val scheduleState: StateFlow<StudentUiState> = _scheduleState.asStateFlow()
    
    private val _historyState = MutableStateFlow<StudentUiState>(StudentUiState.Idle)
    val historyState: StateFlow<StudentUiState> = _historyState.asStateFlow()
    
    private val _profileState = MutableStateFlow<StudentUiState>(StudentUiState.Idle)
    val profileState: StateFlow<StudentUiState> = _profileState.asStateFlow()
    
    private val _attendanceState = MutableStateFlow<StudentUiState>(StudentUiState.Idle)
    val attendanceState: StateFlow<StudentUiState> = _attendanceState.asStateFlow()
    
    private val _faceRegistrationState = MutableStateFlow<StudentUiState>(StudentUiState.Idle)
    val faceRegistrationState: StateFlow<StudentUiState> = _faceRegistrationState.asStateFlow()
    
    private val _storedFaceEmbedding = MutableStateFlow<String?>(null)
    val storedFaceEmbedding: StateFlow<String?> = _storedFaceEmbedding.asStateFlow()

    private val _storedFaceEmbeddingLoaded = MutableStateFlow(false)
    val storedFaceEmbeddingLoaded: StateFlow<Boolean> = _storedFaceEmbeddingLoaded.asStateFlow()

    // Notification LiveData
    private val _notificationPreferencesState: MutableLiveData<UiState<Map<String, Boolean>>> = MutableLiveData()
    val notificationPreferencesState: LiveData<UiState<Map<String, Boolean>>> = _notificationPreferencesState

    private val _notificationsState: MutableLiveData<UiState<List<Notification>>> = MutableLiveData()
    val notificationsState: LiveData<UiState<List<Notification>>> = _notificationsState

    // Auto-refresh jobs
    private var dashboardRefreshJob: Job? = null
    private var scheduleRefreshJob: Job? = null
    private var historyRefreshJob: Job? = null

    // Settings State
    private val _appSettings = MutableStateFlow<AppSettingsConfig?>(null)

    private val _settingsLoaded = MutableStateFlow(false)

    init {
        loadAppSettings()
    }

    private fun loadAppSettings() {
        viewModelScope.launch {
            settingsRepository.getAppSettings()
                .onSuccess {
                    _appSettings.value = it
                    _settingsLoaded.value = true
                }
                .onFailure {
                    // Try to use cached settings
                    val cached = settingsRepository.getCachedSettings()
                    if (cached != null) {
                        _appSettings.value = cached
                    }
                    _settingsLoaded.value = true
                }
        }
    }

    fun refreshSettings() {
        viewModelScope.launch {
            settingsRepository.getAppSettings(forceRefresh = true)
                .onSuccess { _appSettings.value = it }
        }
    }

    fun getGpsRadius(): Int = _appSettings.value?.attendance?.gpsProximityRadius ?: 50

    fun getFaceConfidenceThreshold(): Int = _appSettings.value?.attendance?.faceConfidenceThreshold ?: 85

    fun getAttendanceThreshold(): Int = _appSettings.value?.attendance?.minAttendanceThreshold ?: 75

    fun getEnableLivenessDetection(): Boolean = _appSettings.value?.attendance?.enableLivenessDetection ?: true

    fun getAcademicYear(): Int = _appSettings.value?.system?.academicYear ?: 2026




    fun loadDashboard() {
        viewModelScope.launch {
            _dashboardState.value = StudentUiState.Loading
            repository.getDashboard()
                .onSuccess { _dashboardState.value = StudentUiState.Success(it) }
                .onFailure { _dashboardState.value = StudentUiState.Error(it.message ?: "Failed to load dashboard") }
        }
    }
    
    fun refreshDashboard() {
        viewModelScope.launch {
            _isRefreshing.value = true
            repository.getDashboard()
                .onSuccess { 
                    _dashboardState.value = StudentUiState.Success(it)
                    _lastRefreshTime.value = System.currentTimeMillis()
                }
                .onFailure { _dashboardState.value = StudentUiState.Error(it.message ?: "Failed to refresh dashboard") }
                .also { _isRefreshing.value = false }
        }
    }
    
    fun loadSchedule() {
        viewModelScope.launch {
            _scheduleState.value = StudentUiState.Loading
            repository.getSchedule()
                .onSuccess { _scheduleState.value = StudentUiState.Success(it) }
                .onFailure { _scheduleState.value = StudentUiState.Error(it.message ?: "Failed to load schedule") }
        }
    }
    
    fun loadAttendanceHistory(subjectId: Int? = null, startDate: String? = null, endDate: String? = null) {
        viewModelScope.launch {
            _historyState.value = StudentUiState.Loading
            repository.getAttendanceHistory(subjectId, startDate, endDate)
                .onSuccess { _historyState.value = StudentUiState.Success(it) }
                .onFailure { _historyState.value = StudentUiState.Error(it.message ?: "Failed to load history") }
        }
    }
    
    fun loadProfile() {
        viewModelScope.launch {
            _profileState.value = StudentUiState.Loading
            repository.getProfile()
                .onSuccess { _profileState.value = StudentUiState.Success(it) }
                .onFailure { _profileState.value = StudentUiState.Error(it.message ?: "Failed to load profile") }
        }
    }
    
    fun updateProfile(request: UpdateStudentProfileRequest) {
        viewModelScope.launch {
            _profileState.value = StudentUiState.Loading
            repository.updateProfile(request)
                .onSuccess { _profileState.value = StudentUiState.Success(it) }
                .onFailure { _profileState.value = StudentUiState.Error(it.message ?: "Failed to update profile") }
        }
    }
    
    fun registerFace(embedding: String, faceBitmap: Bitmap? = null) {
        viewModelScope.launch {
            _faceRegistrationState.value = StudentUiState.Loading
            repository.registerFace(embedding, faceBitmap)
                .onSuccess { _faceRegistrationState.value = StudentUiState.Success(it) }
                .onFailure { _faceRegistrationState.value = StudentUiState.Error(it.message ?: "Failed to register face") }
        }
    }
    
    fun markAttendance(scheduleId: Int, latitude: Double, longitude: Double, faceConfidence: Double) {
        viewModelScope.launch {
            _attendanceState.value = StudentUiState.Loading
            repository.markAttendance(scheduleId, latitude, longitude, faceConfidence)
                .onSuccess { _attendanceState.value = StudentUiState.Success(it) }
                .onFailure { _attendanceState.value = StudentUiState.Error(it.message ?: "Failed to mark attendance") }
        }
    }
    
    fun resetAttendanceState() {
        _attendanceState.value = StudentUiState.Idle
    }
    
    fun resetFaceRegistrationState() {
        _faceRegistrationState.value = StudentUiState.Idle
    }
    
    fun loadStoredFaceEmbedding() {
        viewModelScope.launch {
            _storedFaceEmbeddingLoaded.value = false
            repository.getStoredFaceEmbedding()
                .onSuccess { embedding ->
                    _storedFaceEmbedding.value = embedding
                    _storedFaceEmbeddingLoaded.value = true
                }
                .onFailure {
                    _storedFaceEmbedding.value = null
                    _storedFaceEmbeddingLoaded.value = true
                }
        }
    }
    
    // ── Auto-Refresh Methods ──────────────────────────────────────────────────
    
    fun startDashboardAutoRefresh() {
        stopAllAutoRefresh()
        dashboardRefreshJob = viewModelScope.launch {
            while (true) {
                kotlinx.coroutines.delay(30_000) // 30 seconds
                refreshDashboard()
            }
        }
    }
    
    fun startScheduleAutoRefresh() {
        stopAllAutoRefresh()
        scheduleRefreshJob = viewModelScope.launch {
            while (true) {
                kotlinx.coroutines.delay(60_000) // 60 seconds
                refreshSchedule()
            }
        }
    }
    
    fun startAttendanceHistoryAutoRefresh() {
        stopAllAutoRefresh()
        historyRefreshJob = viewModelScope.launch {
            while (true) {
                kotlinx.coroutines.delay(120_000) // 2 minutes
                refreshAttendanceHistory()
            }
        }
    }
    
    fun refreshSchedule() {
        viewModelScope.launch {
            _isRefreshing.value = true
            repository.getSchedule()
                .onSuccess { 
                    _scheduleState.value = StudentUiState.Success(it)
                    _lastRefreshTime.value = System.currentTimeMillis()
                }
                .onFailure { _scheduleState.value = StudentUiState.Error(it.message ?: "Failed to refresh schedule") }
                .also { _isRefreshing.value = false }
        }
    }
    
    fun refreshAttendanceHistory(subjectId: Int? = null, startDate: String? = null, endDate: String? = null) {
        viewModelScope.launch {
            _isRefreshing.value = true
            repository.getAttendanceHistory(subjectId, startDate, endDate)
                .onSuccess { 
                    _historyState.value = StudentUiState.Success(it)
                    _lastRefreshTime.value = System.currentTimeMillis()
                }
                .onFailure { _historyState.value = StudentUiState.Error(it.message ?: "Failed to refresh history") }
                .also { _isRefreshing.value = false }
        }
    }
    
    fun stopAllAutoRefresh() {
        dashboardRefreshJob?.cancel()
        scheduleRefreshJob?.cancel()
        historyRefreshJob?.cancel()
    }
    
    // ── Notification Methods ──────────────────────────────────────────────────
    
    fun loadNotificationPreferences() {
        viewModelScope.launch {
            _notificationPreferencesState.postValue(UiState.Loading)
            repository.getNotificationPreferences()
                .onSuccess { preferences ->
                    _notificationPreferencesState.postValue(UiState.Success(preferences))
                }
                .onFailure { error ->
                    _notificationPreferencesState.postValue(UiState.Error(error.message ?: "Failed to load preferences"))
                }
        }
    }
    
    fun updateNotificationPreferences(preferences: Map<String, Boolean>) {
        viewModelScope.launch {
            _notificationPreferencesState.postValue(UiState.Loading)
            repository.updateNotificationPreferences(preferences)
                .onSuccess { result ->
                    _notificationPreferencesState.postValue(UiState.Success(preferences))
                }
                .onFailure { error ->
                    _notificationPreferencesState.postValue(UiState.Error(error.message ?: "Failed to update preferences"))
                }
        }
    }
    
    fun loadNotifications(unreadOnly: Boolean = false) {
        viewModelScope.launch {
            _notificationsState.postValue(UiState.Loading)
            repository.getNotifications(unreadOnly)
                .onSuccess { response ->
                    _notificationsState.postValue(UiState.Success(response))
                }
                .onFailure { error ->
                    _notificationsState.postValue(UiState.Error(error.message ?: "Failed to load notifications"))
                }
        }
    }
    
    fun markNotificationAsRead(notificationId: Int) {
        viewModelScope.launch {
            repository.markNotificationAsRead(notificationId)
                .onSuccess {
                    // Reload notifications after marking as read
                    loadNotifications()
                }
                .onFailure { error ->
                    println("Failed to mark notification as read: ${error.message}")
                }
        }
    }
    
}
