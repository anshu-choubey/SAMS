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
    
    private val _isLoggedIn = MutableStateFlow(authRepository.isLoggedIn())
    val isLoggedIn: StateFlow<Boolean> = _isLoggedIn.asStateFlow()
    
    private val _userRole = MutableStateFlow(authRepository.getUserRole())
    val userRole: StateFlow<String?> = _userRole.asStateFlow()
    
    fun login(email: String, password: String) {
        if (email.isBlank() || password.isBlank()) {
            _uiState.value = AuthUiState.Error("Email and password are required")
            return
        }
        
        viewModelScope.launch {
            _uiState.value = AuthUiState.Loading
            
            // Get FCM token to register
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
    
    fun logout() {
        viewModelScope.launch {
            // Get FCM token to remove from server
            val token = try {
                FirebaseMessaging.getInstance().token.await()
            } catch (_: Exception) { null }
            
            authRepository.logout(token)
            _isLoggedIn.value = false
            _userRole.value = null
            _uiState.value = AuthUiState.Idle
        }
    }
}
