package com.sams.app.ui.notifications

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.Notification
import com.sams.app.ui.components.*
import com.sams.app.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationsScreen(
    viewModel: NotificationViewModel = hiltViewModel(),
    onNavigateBack: () -> Unit
) {
    val uiState by viewModel.uiState.collectAsState()
    val unreadCount by viewModel.unreadCount.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadNotifications()
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { 
                    Text(
                        "Notifications",
                        fontWeight = FontWeight.Bold
                    )
                },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
                actions = {
                    if (unreadCount > 0) {
                        TextButton(onClick = { viewModel.markAllAsRead() }) {
                            Text("Mark all read")
                        }
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background
                )
            )
        }
    ) { padding ->
        when (val state = uiState) {
            is NotificationUiState.Loading -> LoadingScreen()
            is NotificationUiState.Error -> ErrorScreen(
                message = state.message,
                onRetry = { viewModel.loadNotifications() }
            )
            is NotificationUiState.Success -> {
                if (state.notifications.isEmpty()) {
                    EmptyNotificationsView(modifier = Modifier.padding(padding))
                } else {
                    NotificationsList(
                        notifications = state.notifications,
                        modifier = Modifier.padding(padding),
                        onMarkAsRead = { viewModel.markAsRead(it) }
                    )
                }
            }
        }
    }
}

@Composable
private fun EmptyNotificationsView(modifier: Modifier = Modifier) {
    EmptyState(
        icon = Icons.Outlined.NotificationsNone,
        title = "No Notifications",
        description = "You're all caught up! Check back later for updates.",
    )
}

@Composable
private fun NotificationsList(
    notifications: List<Notification>,
    modifier: Modifier = Modifier,
    onMarkAsRead: (Int) -> Unit
) {
    LazyColumn(
        modifier = modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Today's notifications
        val today = notifications.filter { isToday(it.createdAt) }
        if (today.isNotEmpty()) {
            item {
                Text(
                    text = "Today",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(bottom = 4.dp)
                )
            }
            items(today) { notification ->
                NotificationCard(
                    notification = notification,
                    onMarkAsRead = { onMarkAsRead(notification.id) }
                )
            }
        }
        
        // Earlier notifications
        val earlier = notifications.filter { !isToday(it.createdAt) }
        if (earlier.isNotEmpty()) {
            item {
                Text(
                    text = "Earlier",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.padding(top = 8.dp, bottom = 4.dp)
                )
            }
            items(earlier) { notification ->
                NotificationCard(
                    notification = notification,
                    onMarkAsRead = { onMarkAsRead(notification.id) }
                )
            }
        }
        
        item { Spacer(modifier = Modifier.height(16.dp)) }
    }
}

@Composable
private fun NotificationCard(
    notification: Notification,
    onMarkAsRead: () -> Unit
) {
    val (icon, iconColor, containerColor) = getNotificationStyle(notification.type)
    
    SAMSCard(
        modifier = Modifier.fillMaxWidth(),
        onClick = { if (!notification.isRead) onMarkAsRead() }
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    if (!notification.isRead) 
                        PrimaryBlueContainer.copy(alpha = 0.3f)
                    else Color.Transparent
                )
                .padding(16.dp),
            verticalAlignment = Alignment.Top
        ) {
            // Icon
            Box(
                modifier = Modifier
                    .size(44.dp)
                    .clip(CircleShape)
                    .background(containerColor),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = iconColor,
                    modifier = Modifier.size(22.dp)
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
                        text = notification.title,
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = if (notification.isRead) FontWeight.Normal else FontWeight.Bold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f)
                    )
                    if (!notification.isRead) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .clip(CircleShape)
                                .background(PrimaryBlue)
                        )
                    }
                }
                
                Spacer(modifier = Modifier.height(4.dp))
                
                Text(
                    text = notification.message,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
                
                Spacer(modifier = Modifier.height(8.dp))
                
                Text(
                    text = formatTime(notification.createdAt),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.7f)
                )
            }
        }
    }
}

private fun getNotificationStyle(type: String): Triple<ImageVector, Color, Color> {
    return when (type.lowercase()) {
        "attendance" -> Triple(Icons.Outlined.CheckCircle, SuccessGreen, SuccessGreenContainer)
        "class_started" -> Triple(Icons.Outlined.PlayCircle, PrimaryBlue, PrimaryBlueContainer)
        "class_ended" -> Triple(Icons.Outlined.StopCircle, SecondaryTeal, SecondaryTealContainer)
        "alert" -> Triple(Icons.Outlined.Warning, WarningOrange, WarningOrangeContainer)
        "announcement" -> Triple(Icons.Outlined.Campaign, AccentPurple, AccentPurpleContainer)
        else -> Triple(Icons.Outlined.Notifications, PrimaryBlue, PrimaryBlueContainer)
    }
}

private fun isToday(dateString: String): Boolean {
    // Simple check - in production, use proper date parsing
    return dateString.contains(java.time.LocalDate.now().toString())
}

private fun formatTime(dateString: String): String {
    // Simple format - in production, use proper date parsing
    return try {
        if (dateString.length >= 16) {
            dateString.substring(11, 16) // Extract time HH:mm
        } else {
            dateString
        }
    } catch (e: Exception) {
        dateString
    }
}
