package com.sams.app.ui.notifications

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sams.app.data.models.Notification
import com.sams.app.data.repository.NotificationRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class NotificationUiState {
    object Loading : NotificationUiState()
    data class Success(val notifications: List<Notification>) : NotificationUiState()
    data class Error(val message: String) : NotificationUiState()
}

@HiltViewModel
class NotificationViewModel @Inject constructor(
    private val repository: NotificationRepository
) : ViewModel() {
    
    private val _uiState = MutableStateFlow<NotificationUiState>(NotificationUiState.Loading)
    val uiState: StateFlow<NotificationUiState> = _uiState
    
    private val _unreadCount = MutableStateFlow(0)
    val unreadCount: StateFlow<Int> = _unreadCount
    
    fun loadNotifications() {
        viewModelScope.launch {
            _uiState.value = NotificationUiState.Loading
            repository.getNotifications()
                .onSuccess { response ->
                    val notifications = response.notifications
                    _uiState.value = NotificationUiState.Success(notifications)
                    _unreadCount.value = notifications.count { !it.isRead }
                }
                .onFailure { e ->
                    _uiState.value = NotificationUiState.Error(e.message ?: "Failed to load notifications")
                }
        }
    }
    
    fun markAsRead(notificationId: Int) {
        viewModelScope.launch {
            repository.markAsRead(notificationId)
                .onSuccess {
                    // Reload to get updated list
                    loadNotifications()
                }
        }
    }
    
    fun markAllAsRead() {
        viewModelScope.launch {
            val currentState = _uiState.value
            if (currentState is NotificationUiState.Success) {
                currentState.notifications
                    .filter { !it.isRead }
                    .forEach { notification ->
                        repository.markAsRead(notification.id)
                    }
                loadNotifications()
            }
        }
    }
}
