package com.sams.app.ui.teacher

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sams.app.data.models.*
import com.sams.app.data.repository.TeacherRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class TeacherUiState {
    object Idle : TeacherUiState()
    object Loading : TeacherUiState()
    data class Success(val data: Any) : TeacherUiState()
    data class ScheduleSuccess(val schedule: List<TeacherScheduleItem>) : TeacherUiState()
    data class SessionSuccess(val data: Any) : TeacherUiState()
    data class AttendanceSuccess(val data: ClassAttendanceResponse) : TeacherUiState()
    data class Error(val message: String) : TeacherUiState()
}

@HiltViewModel
class TeacherViewModel @Inject constructor(
    private val teacherRepository: TeacherRepository
) : ViewModel() {
    
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
    
    private var activeSessionId: Int? = null
    
    fun loadDashboard() {
        viewModelScope.launch {
            _dashboardState.value = TeacherUiState.Loading
            teacherRepository.getDashboard()
                .onSuccess { 
                    activeSessionId = it.activeSession?.sessionId
                    _dashboardState.value = TeacherUiState.Success(it) 
                }
                .onFailure { _dashboardState.value = TeacherUiState.Error(it.message ?: "Failed to load dashboard") }
        }
    }
    
    fun refreshDashboard() {
        viewModelScope.launch {
            _isRefreshing.value = true
            teacherRepository.getDashboard()
                .onSuccess { 
                    activeSessionId = it.activeSession?.sessionId
                    _dashboardState.value = TeacherUiState.Success(it)
                    _isRefreshing.value = false
                }
                .onFailure { 
                    _dashboardState.value = TeacherUiState.Error(it.message ?: "Failed to load dashboard")
                    _isRefreshing.value = false
                }
        }
    }
    
    fun loadSchedule() {
        viewModelScope.launch {
            _scheduleState.value = TeacherUiState.Loading
            teacherRepository.getSchedule()
                .onSuccess { response ->
                    // Flatten the map into a list with day info
                    val allSchedules = response.schedules.flatMap { (day, items) ->
                        items.map { it.copy(dayOfWeek = day) }
                    }
                    _scheduleState.value = TeacherUiState.ScheduleSuccess(allSchedules)
                }
                .onFailure { _scheduleState.value = TeacherUiState.Error(it.message ?: "Failed to load schedule") }
        }
    }
    
    fun startSession(scheduleId: Int, latitude: Double, longitude: Double) {
        viewModelScope.launch {
            _sessionState.value = TeacherUiState.Loading
            teacherRepository.startSession(scheduleId, latitude, longitude)
                .onSuccess { 
                    activeSessionId = it.sessionId
                    _sessionState.value = TeacherUiState.SessionSuccess(it) 
                }
                .onFailure { _sessionState.value = TeacherUiState.Error(it.message ?: "Failed to start session") }
        }
    }
    
    fun endSession() {
        val sessionId = activeSessionId
        if (sessionId == null) {
            _sessionState.value = TeacherUiState.Error("No active session to end")
            return
        }
        viewModelScope.launch {
            _sessionState.value = TeacherUiState.Loading
            teacherRepository.endSession(sessionId)
                .onSuccess { 
                    activeSessionId = null
                    _sessionState.value = TeacherUiState.SessionSuccess(it ?: Unit)
                    // Refresh dashboard data
                    loadDashboard()
                }
                .onFailure { _sessionState.value = TeacherUiState.Error(it.message ?: "Failed to end session") }
        }
    }
    
    fun endSessionWithId(sessionId: Int) {
        viewModelScope.launch {
            _sessionState.value = TeacherUiState.Loading
            teacherRepository.endSession(sessionId)
                .onSuccess { 
                    activeSessionId = null
                    _sessionState.value = TeacherUiState.SessionSuccess(it ?: Unit)
                    // Refresh dashboard data
                    loadDashboard()
                }
                .onFailure { _sessionState.value = TeacherUiState.Error(it.message ?: "Failed to end session") }
        }
    }
    
    fun setActiveSessionId(sessionId: Int?) {
        activeSessionId = sessionId
    }
    
    fun loadClassAttendance(scheduleId: Int, date: String? = null) {
        viewModelScope.launch {
            _attendanceState.value = TeacherUiState.Loading
            teacherRepository.getClassAttendance(scheduleId, date)
                .onSuccess { 
                    // Update activeSessionId from attendance response
                    if (it.sessionActive && it.sessionId != null) {
                        activeSessionId = it.sessionId
                    }
                    _attendanceState.value = TeacherUiState.AttendanceSuccess(it) 
                }
                .onFailure { _attendanceState.value = TeacherUiState.Error(it.message ?: "Failed to load attendance") }
        }
    }
    
    fun markManualAttendance(scheduleId: Int, studentId: Int, status: String) {
        viewModelScope.launch {
            teacherRepository.markManualAttendance(scheduleId, studentId, status)
                .onSuccess { loadClassAttendance(scheduleId) }
                .onFailure { _attendanceState.value = TeacherUiState.Error(it.message ?: "Failed to mark attendance") }
        }
    }
    
    fun resetSessionState() {
        _sessionState.value = TeacherUiState.Idle
    }
    
    fun getActiveSessionId(): Int? = activeSessionId
}
