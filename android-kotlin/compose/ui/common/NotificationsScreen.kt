package com.sams.app.ui.common

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.Notification
import com.sams.app.ui.theme.Warning

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationsScreen(
    viewModel: NotificationViewModel = hiltViewModel(),
    onBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadNotifications()
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Notifications") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
                actions = {
                    if (uiState is NotificationUiState.Success) {
                        val state = uiState as NotificationUiState.Success
                        if (state.unreadCount > 0) {
                            TextButton(onClick = { viewModel.markAllAsRead() }) {
                                Text("Mark all read")
                            }
                        }
                    }
                }
            )
        }
    ) { padding ->
        when (val state = uiState) {
            is NotificationUiState.Loading -> {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
            }
            is NotificationUiState.Success -> {
                if (state.notifications.isEmpty()) {
                    EmptyNotifications(modifier = Modifier.padding(padding))
                } else {
                    NotificationsList(
                        notifications = state.notifications,
                        modifier = Modifier.padding(padding),
                        onNotificationClick = { notification ->
                            if (!notification.isRead) {
                                viewModel.markAsRead(notification.id)
                            }
                        }
                    )
                }
            }
            is NotificationUiState.Error -> {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(state.message, color = MaterialTheme.colorScheme.error)
                        Spacer(modifier = Modifier.height(16.dp))
                        Button(onClick = { viewModel.loadNotifications() }) {
                            Text("Retry")
                        }
                    }
                }
            }
            else -> {}
        }
    }
}

@Composable
private fun EmptyNotifications(modifier: Modifier = Modifier) {
    Box(
        modifier = modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Icon(
                Icons.Outlined.Notifications,
                contentDescription = null,
                modifier = Modifier.size(72.dp),
                tint = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Spacer(modifier = Modifier.height(16.dp))
            Text(
                "No notifications",
                style = MaterialTheme.typography.titleMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Text(
                "You're all caught up!",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun NotificationsList(
    notifications: List<Notification>,
    modifier: Modifier = Modifier,
    onNotificationClick: (Notification) -> Unit
) {
    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        items(notifications, key = { it.id }) { notification ->
            NotificationCard(
                notification = notification,
                onClick = { onNotificationClick(notification) }
            )
        }
    }
}

@Composable
private fun NotificationCard(
    notification: Notification,
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(
            containerColor = if (notification.isRead) 
                MaterialTheme.colorScheme.surface 
            else 
                MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.3f)
        )
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.Top
        ) {
            // Icon
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .clip(CircleShape)
                    .background(getNotificationIconColor(notification.type).copy(alpha = 0.2f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    getNotificationIcon(notification.type),
                    contentDescription = null,
                    tint = getNotificationIconColor(notification.type),
                    modifier = Modifier.size(24.dp)
                )
            }
            
            Spacer(modifier = Modifier.width(12.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.Top
                ) {
                    Text(
                        notification.title,
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = if (notification.isRead) FontWeight.Normal else FontWeight.Bold,
                        modifier = Modifier.weight(1f)
                    )
                    
                    if (!notification.isRead) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .clip(CircleShape)
                                .background(MaterialTheme.colorScheme.primary)
                        )
                    }
                }
                
                Spacer(modifier = Modifier.height(4.dp))
                
                Text(
                    notification.message,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
                
                Spacer(modifier = Modifier.height(8.dp))
                
                Text(
                    formatTimeAgo(notification.createdAt),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
    }
}

@Composable
private fun getNotificationIcon(type: String): ImageVector {
    return when (type) {
        "attendance" -> Icons.Default.CheckCircle
        "schedule" -> Icons.Default.CalendarMonth
        "session" -> Icons.Default.PlayCircle
        "alert" -> Icons.Default.Warning
        "announcement" -> Icons.Default.Campaign
        else -> Icons.Default.Notifications
    }
}

@Composable
private fun getNotificationIconColor(type: String): androidx.compose.ui.graphics.Color {
    return when (type) {
        "attendance" -> androidx.compose.ui.graphics.Color(0xFF4CAF50)
        "schedule" -> MaterialTheme.colorScheme.primary
        "session" -> androidx.compose.ui.graphics.Color(0xFF2196F3)
        "alert" -> Warning
        "announcement" -> androidx.compose.ui.graphics.Color(0xFF9C27B0)
        else -> MaterialTheme.colorScheme.primary
    }
}

private fun formatTimeAgo(dateString: String): String {
    // Simple time ago formatting - in production use a proper library
    return try {
        // Parse and calculate difference
        // For now, just return the date string
        dateString
    } catch (e: Exception) {
        dateString
    }
}
