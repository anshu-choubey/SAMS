package com.sams.app.ui.teacher

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sams.app.data.models.*
import com.sams.app.data.repository.TeacherRepository
import com.sams.app.data.repository.SettingsRepository
import com.sams.app.utils.AppDefaults
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class TeacherUiState {
    object Idle : TeacherUiState()
    object Loading : TeacherUiState()
    data class Success(val data: Any) : TeacherUiState()
    data class Error(val message: String) : TeacherUiState()
}

@HiltViewModel
class TeacherViewModel @Inject constructor(
    private val repository: TeacherRepository,
    private val settingsRepository: SettingsRepository   // ✅ injected
) : ViewModel() {

    // ── UI State Flows ────────────────────────────────────────────────────────

    private val _dashboardState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val dashboardState: StateFlow<TeacherUiState> = _dashboardState.asStateFlow()

    private val _isRefreshing = MutableStateFlow(false)
    val isRefreshing: StateFlow<Boolean> = _isRefreshing.asStateFlow()

    private val _scheduleState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val scheduleState: StateFlow<TeacherUiState> = _scheduleState.asStateFlow()

    private val _sessionState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val sessionState: StateFlow<TeacherUiState> = _sessionState.asStateFlow()

    private val _attendanceState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val attendanceState: StateFlow<TeacherUiState> = _attendanceState.asStateFlow()

    private val _classAttendanceState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val classAttendanceState: StateFlow<TeacherUiState> = _classAttendanceState.asStateFlow()
    
    // ✅ Multi-Check Attendance States
    private val _checkTriggerState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val checkTriggerState: StateFlow<TeacherUiState> = _checkTriggerState.asStateFlow()
    
    private val _finalizeState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val finalizeState: StateFlow<TeacherUiState> = _finalizeState.asStateFlow()

    private var currentSessionId: Int? = null

    // ── Settings State ────────────────────────────────────────────────────────

    private val _appSettings = MutableStateFlow<AppSettingsConfig?>(null)

    private val _settingsLoaded = MutableStateFlow(false)

    // ── Init ──────────────────────────────────────────────────────────────────

    init {
        loadAppSettings()
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    private fun loadAppSettings() {
        viewModelScope.launch {
            settingsRepository.getAppSettings()
                .onSuccess {
                    _appSettings.value = it
                    _settingsLoaded.value = true
                }
                .onFailure {
                    val cached = settingsRepository.getCachedSettings()
                    if (cached != null) _appSettings.value = cached
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



    // ── Settings Getters ──────────────────────────────────────────────────────

    fun getGpsRadius(): Int =
        _appSettings.value?.attendance?.gpsProximityRadius ?: AppDefaults.GPS_RADIUS

    fun getFaceConfidenceThreshold(): Int =
        _appSettings.value?.attendance?.faceConfidenceThreshold ?: 85

    fun getAttendanceThreshold(): Int = 75

    fun getEnableLivenessDetection(): Boolean =
        _appSettings.value?.attendance?.enableLivenessDetection ?: true

    fun getAcademicYear(): Int =
        _appSettings.value?.system?.academicYear ?: 2026


    // ── Dashboard ─────────────────────────────────────────────────────────────

    fun loadDashboard() {
        viewModelScope.launch {
            _dashboardState.value = TeacherUiState.Loading
            repository.getDashboard()
                .onSuccess { data ->
                    currentSessionId = data.activeSession?.sessionId ?: currentSessionId
                    _dashboardState.value = TeacherUiState.Success(data)
                }
                .onFailure {
                    _dashboardState.value =
                        TeacherUiState.Error(it.message ?: "Failed to load dashboard")
                }
        }
    }

    fun refreshDashboard() {
        viewModelScope.launch {
            _isRefreshing.value = true
            try {
                repository.getDashboard()
                    .onSuccess { data ->
                        currentSessionId = data.activeSession?.sessionId ?: currentSessionId
                        _dashboardState.value = TeacherUiState.Success(data)
                    }
                    .onFailure {
                        _dashboardState.value =
                            TeacherUiState.Error(it.message ?: "Failed to refresh")
                    }
            } finally {
                _isRefreshing.value = false
            }
        }
    }

    // ── Schedule ──────────────────────────────────────────────────────────────

    fun loadSchedule() {
        viewModelScope.launch {
            _scheduleState.value = TeacherUiState.Loading
            repository.getSchedules()
                .onSuccess { _scheduleState.value = TeacherUiState.Success(it) }
                .onFailure {
                    _scheduleState.value =
                        TeacherUiState.Error(it.message ?: "Failed to load schedule")
                }
        }
    }

    // ── Session ───────────────────────────────────────────────────────────────

    fun startSession(scheduleId: Int, latitude: Double, longitude: Double) {
        viewModelScope.launch {
            _sessionState.value = TeacherUiState.Loading
            repository.startSession(scheduleId, latitude, longitude)
                .onSuccess { data ->
                    currentSessionId = data.sessionId
                    _sessionState.value = TeacherUiState.Success(data)
                }
                .onFailure {
                    _sessionState.value =
                        TeacherUiState.Error(it.message ?: "Failed to start session")
                }
        }
    }

    fun endSession(sessionId: Int? = null) {
        // Prefer provided sessionId, then try alternative sources
        val id = sessionId
            ?: currentSessionId
            ?: (_dashboardState.value as? TeacherUiState.Success)
                ?.let { (it.data as? TeacherDashboardData)?.activeSession?.sessionId }
            ?: (_classAttendanceState.value as? TeacherUiState.Success)
                ?.let { (it.data as? ClassAttendanceResponse)?.sessionId }

        // Debug logging
        android.util.Log.d("TeacherViewModel", """
            endSession() called
            - sessionId arg: $sessionId
            - currentSessionId: $currentSessionId
            - dashboard activeSession: ${(_dashboardState.value as? TeacherUiState.Success)?.let { (it.data as? TeacherDashboardData)?.activeSession?.sessionId }}
            - classAttendance sessionId: ${(_classAttendanceState.value as? TeacherUiState.Success)?.let { (it.data as? ClassAttendanceResponse)?.sessionId }}
            - RESOLVED ID: $id
        """.trimIndent())

        if (id == null) {
            _sessionState.value = TeacherUiState.Error("No active session to end")
            return
        }

        viewModelScope.launch {
            _sessionState.value = TeacherUiState.Loading
            repository.endSession(id)
                .onSuccess {
                    currentSessionId = null
                    _sessionState.value = TeacherUiState.Success(Unit)
                    loadDashboard()
                }
                .onFailure {
                    _sessionState.value =
                        TeacherUiState.Error(it.message ?: "Failed to end session")
                }
        }
    }

    fun resetSessionState() {
        _sessionState.value = TeacherUiState.Idle
    }

    // ── Attendance ────────────────────────────────────────────────────────────

    fun loadClassAttendance(scheduleId: Int, date: String? = null) {
        viewModelScope.launch {
            _classAttendanceState.value = TeacherUiState.Loading
            repository.getClassAttendance(scheduleId, date)
                .onSuccess { _classAttendanceState.value = TeacherUiState.Success(it) }
                .onFailure {
                    _classAttendanceState.value =
                        TeacherUiState.Error(it.message ?: "Failed to load attendance")
                }
        }
    }

    fun markManualAttendance(scheduleId: Int, studentId: Int, status: String) {
        viewModelScope.launch {
            _attendanceState.value = TeacherUiState.Loading
            repository.markManualAttendance(scheduleId, studentId, status)
                .onSuccess {
                    _attendanceState.value = TeacherUiState.Idle
                    loadClassAttendance(scheduleId)
                }
                .onFailure {
                    _attendanceState.value =
                        TeacherUiState.Error(it.message ?: "Failed to mark attendance")
                }
        }
    }
    
    // ✅ Multi-Check Attendance Methods
    fun triggerAttendanceCheck(sessionId: Int? = null, windowMinutes: Int = 5) {
        val id = sessionId ?: currentSessionId
        
        if (id == null) {
            _checkTriggerState.value = TeacherUiState.Error("No active session")
            return
        }
        
        viewModelScope.launch {
            _checkTriggerState.value = TeacherUiState.Loading
            repository.triggerAttendanceCheck(id, windowMinutes)
                .onSuccess { data ->
                    _checkTriggerState.value = TeacherUiState.Success(data)
                    // Refresh dashboard to update check count
                    loadDashboard()
                }
                .onFailure {
                    _checkTriggerState.value =
                        TeacherUiState.Error(it.message ?: "Failed to trigger check")
                }
        }
    }
    
    fun resetCheckTriggerState() {
        _checkTriggerState.value = TeacherUiState.Idle
    }
    
    fun finalizeAttendance(sessionId: Int? = null) {
        val id = sessionId ?: currentSessionId
        
        if (id == null) {
            _finalizeState.value = TeacherUiState.Error("No active session")
            return
        }
        
        viewModelScope.launch {
            _finalizeState.value = TeacherUiState.Loading
            repository.finalizeAttendance(id)
                .onSuccess { data ->
                    _finalizeState.value = TeacherUiState.Success(data)
                    loadDashboard()
                }
                .onFailure {
                    _finalizeState.value =
                        TeacherUiState.Error(it.message ?: "Failed to finalize attendance")
                }
        }
    }
    
    fun resetFinalizeState() {
        _finalizeState.value = TeacherUiState.Idle
    }
}
