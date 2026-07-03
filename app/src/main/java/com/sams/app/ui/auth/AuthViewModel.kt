package com.sams.app.ui.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.google.firebase.messaging.FirebaseMessaging
import com.sams.app.data.repository.AuthRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await
import javax.inject.Inject

sealed class AuthUiState {
    object Idle : AuthUiState()
    object Loading : AuthUiState()
    data class Success(val role: String) : AuthUiState()
    data class Error(val message: String) : AuthUiState()
}
@HiltViewModel
class AuthViewModel @Inject constructor(
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow<AuthUiState>(AuthUiState.Idle)
    val uiState: StateFlow<AuthUiState> = _uiState.asStateFlow()

    private val _isLoggedIn = MutableStateFlow(false)
    val isLoggedIn: StateFlow<Boolean> = _isLoggedIn.asStateFlow()

    private val _userRole = MutableStateFlow<String?>(null)
    val userRole: StateFlow<String?> = _userRole.asStateFlow()

    // ✅ Add this — blocks NavHost until DataStore is read
    private val _isReady = MutableStateFlow(false)
    val isReady: StateFlow<Boolean> = _isReady.asStateFlow()

    init {
        checkExistingSession()
    }

    private fun checkExistingSession() {
        viewModelScope.launch {
            try {
                val isLoggedIn = authRepository.isLoggedInAsync()
                val userRole = authRepository.getUserRoleAsync()
                if (isLoggedIn && userRole != null) {
                    val deviceToken = try {
                        FirebaseMessaging.getInstance().token.await()
                    } catch (_: Exception) {
                        null
                    }
                    if (!deviceToken.isNullOrBlank()) {
                        authRepository.registerCurrentDeviceToken(deviceToken)
                    }
                    _isLoggedIn.value = true
                    _userRole.value = userRole
                    _uiState.value = AuthUiState.Success(userRole)
                }
            } catch (_: Exception) {
                // Session check failed, stay on login
            } finally {
                _isReady.value = true
            }
        }
    }

    fun login(email: String, password: String) {
        if (email.isBlank() || password.isBlank()) {
            _uiState.value = AuthUiState.Error("Email and password are required")
            return
        }
        viewModelScope.launch {
            _uiState.value = AuthUiState.Loading
            val deviceToken = try {
                FirebaseMessaging.getInstance().token.await()
            } catch (_: Exception) { null }

            authRepository.login(email, password, deviceToken)
                .onSuccess { loginResponse ->
                    val loginData = loginResponse.data
                    if (loginData != null) {
                        _isLoggedIn.value = true
                        _userRole.value = loginData.user.role
                        _uiState.value = AuthUiState.Success(loginData.user.role)
                    } else {
                        _uiState.value = AuthUiState.Error("Login failed: No user data")
                    }
                }
                .onFailure { error ->
                    _uiState.value = AuthUiState.Error(error.message ?: "Login failed")
                }
        }
    }

    fun logout(onComplete: () -> Unit = {}) {
        viewModelScope.launch {
            authRepository.logout()
            _isLoggedIn.value = false
            _userRole.value = null
            _uiState.value = AuthUiState.Idle
            onComplete()
        }
    }
}
