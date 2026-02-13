package com.sams.app.ui.student

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sams.app.data.models.*
import com.sams.app.data.repository.StudentRepository
import dagger.hilt.android.lifecycle.HiltViewModel
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

@HiltViewModel
class StudentViewModel @Inject constructor(
    private val repository: StudentRepository
) : ViewModel() {
    
    private val _dashboardState = MutableStateFlow<StudentUiState>(StudentUiState.Idle)
    val dashboardState: StateFlow<StudentUiState> = _dashboardState.asStateFlow()
    
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
    
    fun loadDashboard() {
        viewModelScope.launch {
            _dashboardState.value = StudentUiState.Loading
            repository.getDashboard()
                .onSuccess { _dashboardState.value = StudentUiState.Success(it) }
                .onFailure { _dashboardState.value = StudentUiState.Error(it.message ?: "Failed to load dashboard") }
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
    
    fun registerFace(embedding: String) {
        viewModelScope.launch {
            _faceRegistrationState.value = StudentUiState.Loading
            repository.registerFace(embedding)
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
            repository.getStoredFaceEmbedding()
                .onSuccess { embedding -> _storedFaceEmbedding.value = embedding }
                .onFailure { _storedFaceEmbedding.value = null }
        }
    }
    
    fun hasFaceRegistered(): Boolean = _storedFaceEmbedding.value != null
}
