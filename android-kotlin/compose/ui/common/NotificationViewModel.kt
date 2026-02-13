package com.sams.app.ui.common

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sams.app.data.models.Notification
import com.sams.app.data.repository.NotificationRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class NotificationUiState {
    object Idle : NotificationUiState()
    object Loading : NotificationUiState()
    data class Success(val notifications: List<Notification>, val unreadCount: Int) : NotificationUiState()
    data class Error(val message: String) : NotificationUiState()
}

@HiltViewModel
class NotificationViewModel @Inject constructor(
    private val repository: NotificationRepository
) : ViewModel() {
    
    private val _uiState = MutableStateFlow<NotificationUiState>(NotificationUiState.Idle)
    val uiState: StateFlow<NotificationUiState> = _uiState.asStateFlow()
    
    fun loadNotifications() {
        viewModelScope.launch {
            _uiState.value = NotificationUiState.Loading
            repository.getNotifications()
                .onSuccess { response ->
                    _uiState.value = NotificationUiState.Success(
                        notifications = response.notifications,
                        unreadCount = response.unreadCount
                    )
                }
                .onFailure { 
                    _uiState.value = NotificationUiState.Error(it.message ?: "Failed to load notifications")
                }
        }
    }
    
    fun markAsRead(notificationId: Int) {
        viewModelScope.launch {
            repository.markAsRead(notificationId)
                .onSuccess { loadNotifications() }
        }
    }
    
    fun markAllAsRead() {
        viewModelScope.launch {
            repository.markAllAsRead()
                .onSuccess { loadNotifications() }
        }
    }
}
