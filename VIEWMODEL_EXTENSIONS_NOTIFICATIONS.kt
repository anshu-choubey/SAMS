/**
 * StudentViewModel Extensions for Personalized Notifications
 * Add these methods to StudentViewModel.kt
 */

// Add to imports
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.serialization.json.jsonObject
import com.sams.app.data.models.PersonalizedNotification

// Add these properties to StudentViewModel
private val _personalizedNotifications = MutableStateFlow<List<PersonalizedNotification>>(emptyList())
val personalizedNotifications = _personalizedNotifications.asStateFlow()

private val _notificationPreferences = MutableStateFlow<Map<String, Boolean>>(emptyMap())
val notificationPreferences = _notificationPreferences.asStateFlow()

// Add these methods to StudentViewModel

/**
 * Load personalized notifications for current user
 */
fun loadPersonalizedNotifications() {
    viewModelScope.launch {
        try {
            // Example API call - adjust to your actual API
            val response = repository.getPersonalizedNotifications()
            if (response.success) {
                _personalizedNotifications.value = response.notifications
            }
        } catch (e: Exception) {
            _notificationError.value = e.message
        }
    }
}

/**
 * Mark a notification as read
 */
fun markNotificationAsRead(notificationId: Int) {
    viewModelScope.launch {
        try {
            // Call API to mark as read
            repository.markNotificationAsRead(notificationId)
            
            // Update local state
            val updated = _personalizedNotifications.value.map { notif ->
                if (notif.id == notificationId) {
                    notif.copy(isRead = true)
                } else notif
            }
            _personalizedNotifications.value = updated
        } catch (e: Exception) {
            _notificationError.value = e.message
        }
    }
}

/**
 * Load user's notification preferences
 */
fun loadNotificationPreferences() {
    viewModelScope.launch {
        try {
            val response = repository.getNotificationPreferences()
            if (response.success) {
                _notificationPreferences.value = response.preferences
            }
        } catch (e: Exception) {
            _notificationError.value = e.message
        }
    }
}

/**
 * Update notification preferences
 */
fun updateNotificationPreferences(preferences: Map<String, Any>) {
    viewModelScope.launch {
        try {
            val response = repository.updateNotificationPreferences(preferences)
            if (response.success) {
                _notificationPreferences.value = response.preferences as Map<String, Boolean>
                _loadingState.value = false
            }
        } catch (e: Exception) {
            _notificationError.value = "Failed to update preferences: ${e.message}"
        }
    }
}

/**
 * Filter notifications by type
 */
fun getNotificationsByType(type: String): List<PersonalizedNotification> {
    return if (type == "all") {
        _personalizedNotifications.value
    } else {
        _personalizedNotifications.value.filter { it.notificationClass == type }
    }
}

/**
 * Get unread notification count
 */
fun getUnreadNotificationCount(): Int {
    return _personalizedNotifications.value.count { !it.isRead }
}

/**
 * Send personalized notification (Admin/Teacher only)
 */
fun sendPersonalizedNotification(
    notificationClass: String,
    targetUserId: Int,
    customTitle: String? = null,
    customMessage: String? = null,
    customData: Map<String, Any>? = null
) {
    viewModelScope.launch {
        try {
            val response = repository.sendPersonalizedNotification(
                notificationClass = notificationClass,
                targetUserId = targetUserId,
                title = customTitle,
                message = customMessage,
                data = customData
            )
            if (response.success) {
                _notificationSuccess.value = "Notification sent successfully"
            }
        } catch (e: Exception) {
            _notificationError.value = e.message
        }
    }
}

// Add error flow if not exists
private val _notificationError = MutableStateFlow<String?>(null)
val notificationError = _notificationError.asStateFlow()

private val _notificationSuccess = MutableStateFlow<String?>(null)
val notificationSuccess = _notificationSuccess.asStateFlow()
