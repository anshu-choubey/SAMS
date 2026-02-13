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
    data class Error(val message: String) : TeacherUiState()
}

@HiltViewModel
class TeacherViewModel @Inject constructor(
    private val repository: TeacherRepository
) : ViewModel() {
    
    private val _dashboardState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val dashboardState: StateFlow<TeacherUiState> = _dashboardState.asStateFlow()
    
    private val _scheduleState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val scheduleState: StateFlow<TeacherUiState> = _scheduleState.asStateFlow()
    
    private val _sessionState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val sessionState: StateFlow<TeacherUiState> = _sessionState.asStateFlow()
    
    private val _attendanceState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val attendanceState: StateFlow<TeacherUiState> = _attendanceState.asStateFlow()
    
    private val _classAttendanceState = MutableStateFlow<TeacherUiState>(TeacherUiState.Idle)
    val classAttendanceState: StateFlow<TeacherUiState> = _classAttendanceState.asStateFlow()
    
    fun loadDashboard() {
        viewModelScope.launch {
            _dashboardState.value = TeacherUiState.Loading
            repository.getDashboard()
                .onSuccess { _dashboardState.value = TeacherUiState.Success(it) }
                .onFailure { _dashboardState.value = TeacherUiState.Error(it.message ?: "Failed to load dashboard") }
        }
    }
    
    fun loadSchedule() {
        viewModelScope.launch {
            _scheduleState.value = TeacherUiState.Loading
            repository.getSchedule()
                .onSuccess { _scheduleState.value = TeacherUiState.Success(it) }
                .onFailure { _scheduleState.value = TeacherUiState.Error(it.message ?: "Failed to load schedule") }
        }
    }
    
    // Store session ID from startSession
    private var currentSessionId: Int? = null
    
    fun startSession(scheduleId: Int, latitude: Double, longitude: Double) {
        viewModelScope.launch {
            _sessionState.value = TeacherUiState.Loading
            repository.startSession(scheduleId, latitude, longitude)
                .onSuccess { data ->
                    currentSessionId = data.sessionId
                    _sessionState.value = TeacherUiState.Success(data)
                }
                .onFailure { _sessionState.value = TeacherUiState.Error(it.message ?: "Failed to start session") }
        }
    }
    
    fun endSession(sessionId: Int? = null) {
        val id = sessionId ?: currentSessionId
        if (id == null) {
            _sessionState.value = TeacherUiState.Error("No active session to end")
            return
        }
        viewModelScope.launch {
            _sessionState.value = TeacherUiState.Loading
            repository.endSession(id)
                .onSuccess { 
                    currentSessionId = null
                    _sessionState.value = TeacherUiState.Idle
                    loadDashboard()
                }
                .onFailure { _sessionState.value = TeacherUiState.Error(it.message ?: "Failed to end session") }
        }
    }
    
    fun loadClassAttendance(scheduleId: Int, date: String? = null) {
        viewModelScope.launch {
            _classAttendanceState.value = TeacherUiState.Loading
            repository.getClassAttendance(scheduleId, date)
                .onSuccess { _classAttendanceState.value = TeacherUiState.Success(it) }
                .onFailure { _classAttendanceState.value = TeacherUiState.Error(it.message ?: "Failed to load attendance") }
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
                .onFailure { _attendanceState.value = TeacherUiState.Error(it.message ?: "Failed to mark attendance") }
        }
    }
    
    fun resetSessionState() {
        _sessionState.value = TeacherUiState.Idle
    }
}
