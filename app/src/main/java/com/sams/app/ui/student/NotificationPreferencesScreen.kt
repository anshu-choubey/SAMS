package com.sams.app.ui.student

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.VolumeUp
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.material3.ripple
import androidx.compose.runtime.*
import androidx.compose.runtime.livedata.observeAsState
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.UiState
import com.sams.app.ui.theme.PresentColor
import kotlinx.coroutines.delay

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationPreferencesScreen(
    viewModel: StudentViewModel = hiltViewModel(),
    onBack: () -> Unit
) {
    // Load preferences when screen appears
    LaunchedEffect(Unit) {
        viewModel.loadNotificationPreferences()
    }
    
    // Use observeAsState for LiveData instead of collectAsStateWithLifecycle
    val preferencesState by viewModel.notificationPreferencesState.observeAsState()
    
    // Initialize with default preferences if loading
    val defaultPreferences = mapOf(
        "low_attendance" to true,
        "perfect_attendance" to true,
        "absent_today" to true,
        "schedule_reminder" to true,
        "performance_praise" to true,
        "custom" to true,
        "sound_enabled" to true,
        "vibration_enabled" to true,
        "show_preview" to true
    )
    
    val loadedPreferences = when (preferencesState) {
        is UiState.Success<*> -> (preferencesState as UiState.Success<Map<String, Boolean>>).data
        else -> defaultPreferences
    }
    
    var updatedPreferences by remember(loadedPreferences) { 
        mutableStateOf(loadedPreferences) 
    }
    var isSaving by remember { mutableStateOf(false) }
    var saveMessage by remember { mutableStateOf<String?>(null) }
    var isError by remember { mutableStateOf(false) }
    
    // Auto-hide success message after 3 seconds
    LaunchedEffect(saveMessage) {
        if (saveMessage != null) {
            delay(3000)
            saveMessage = null
        }
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Text(
                        text = "Notification Settings",
                        style = MaterialTheme.typography.titleLarge.copy(
                            fontWeight = FontWeight.Bold
                        )
                    )
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back"
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surface
                )
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
        ) {
            // Loading state
            if (preferencesState is UiState.Loading) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(300.dp),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator()
                }
                return@Column
            }
            
            // Error state
            if (preferencesState is UiState.Error) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.errorContainer
                    )
                ) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        Icon(
                            Icons.Default.Error,
                            contentDescription = null,
                            tint = MaterialTheme.colorScheme.error,
                            modifier = Modifier.size(24.dp)
                        )
                        Text(
                            text = (preferencesState as UiState.Error).message,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onErrorContainer
                        )
                    }
                }
            }
            
            // Info card
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer
                )
            ) {
                Row(
                    modifier = Modifier.padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Icon(
                        Icons.Default.Info,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(24.dp)
                    )
                    Text(
                        text = "Customize how you receive notifications",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onPrimaryContainer
                    )
                }
            }
            
            // Notification Types Section
            PreferenceSectionHeader("NOTIFICATION TYPES")
            
            PreferenceSwitchItem(
                icon = Icons.Default.Speed,
                title = "Low Attendance Alerts",
                description = "Get notified when attendance drops below 75%",
                enabled = updatedPreferences["low_attendance"] ?: true,
                onToggle = { 
                    updatedPreferences = updatedPreferences.toMutableMap().apply {
                        put("low_attendance", it)
                    }
                }
            )
            
            PreferenceSwitchItem(
                icon = Icons.Default.EmojiEvents,
                title = "Perfect Attendance",
                description = "Celebrate when you maintain 100% attendance",
                enabled = updatedPreferences["perfect_attendance"] ?: true,
                onToggle = { 
                    updatedPreferences = updatedPreferences.toMutableMap().apply {
                        put("perfect_attendance", it)
                    }
                }
            )
            
            PreferenceSwitchItem(
                icon = Icons.Default.EventBusy,
                title = "Absence Notifications",
                description = "Know when you're marked absent",
                enabled = updatedPreferences["absent_today"] ?: true,
                onToggle = { 
                    updatedPreferences = updatedPreferences.toMutableMap().apply {
                        put("absent_today", it)
                    }
                }
            )
            
            PreferenceSwitchItem(
                icon = Icons.Default.Schedule,
                title = "Schedule Reminders",
                description = "Get reminded about upcoming classes",
                enabled = updatedPreferences["schedule_reminder"] ?: true,
                onToggle = { 
                    updatedPreferences = updatedPreferences.toMutableMap().apply {
                        put("schedule_reminder", it)
                    }
                }
            )
            
            PreferenceSwitchItem(
                icon = Icons.Default.Stars,
                title = "Performance Praise",
                description = "Receive encouragement messages",
                enabled = updatedPreferences["performance_praise"] ?: true,
                onToggle = { 
                    updatedPreferences = updatedPreferences.toMutableMap().apply {
                        put("performance_praise", it)
                    }
                }
            )
            
            // Sound & Vibration Section
            PreferenceSectionHeader("NOTIFICATION SETTINGS")
            
            PreferenceSwitchItem(
                icon = Icons.AutoMirrored.Filled.VolumeUp,
                title = "Sound",
                description = "Play sound for notifications",
                enabled = updatedPreferences["sound_enabled"] ?: true,
                onToggle = { 
                    updatedPreferences = updatedPreferences.toMutableMap().apply {
                        put("sound_enabled", it)
                    }
                }
            )
            
            PreferenceSwitchItem(
                icon = Icons.Default.Vibration,
                title = "Vibration",
                description = "Vibrate on notification",
                enabled = updatedPreferences["vibration_enabled"] ?: true,
                onToggle = { 
                    updatedPreferences = updatedPreferences.toMutableMap().apply {
                        put("vibration_enabled", it)
                    }
                }
            )
            
            PreferenceSwitchItem(
                icon = Icons.Default.Preview,
                title = "Show Preview",
                description = "Display message preview on lock screen",
                enabled = updatedPreferences["show_preview"] ?: true,
                onToggle = { 
                    updatedPreferences = updatedPreferences.toMutableMap().apply {
                        put("show_preview", it)
                    }
                }
            )
            
            // Status message
            AnimatedVisibility(
                visible = saveMessage != null,
                enter = fadeIn(),
                exit = fadeOut()
            ) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = if (!isError)
                            PresentColor.copy(alpha = 0.1f)
                        else
                            MaterialTheme.colorScheme.errorContainer
                    )
                ) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        Icon(
                            if (!isError) Icons.Default.CheckCircle
                            else Icons.Default.Error,
                            contentDescription = null,
                            tint = if (!isError) PresentColor
                            else MaterialTheme.colorScheme.error,
                            modifier = Modifier.size(20.dp)
                        )
                        Text(
                            text = saveMessage ?: "",
                            style = MaterialTheme.typography.bodySmall,
                            color = if (!isError) PresentColor
                            else MaterialTheme.colorScheme.error
                        )
                    }
                }
            }
            
            // Save button
            Button(
                onClick = {
                    isSaving = true
                    // Call API to save preferences
                    viewModel.updateNotificationPreferences(updatedPreferences)
                    isError = false
                    saveMessage = "✓ Preferences saved successfully"
                    isSaving = false
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp)
                    .height(56.dp),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = PresentColor
                ),
                enabled = !isSaving
            ) {
                if (isSaving) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(20.dp),
                        strokeWidth = 2.dp,
                        color = Color.White
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Saving...")
                } else {
                    Icon(Icons.Default.Save, contentDescription = null, modifier = Modifier.size(20.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Save Preferences")
                }
            }
            
            Spacer(modifier = Modifier.height(16.dp))
        }
    }
}

@Composable
private fun PreferenceSectionHeader(title: String) {
    Text(
        text = title,
        style = MaterialTheme.typography.labelLarge.copy(
            fontWeight = FontWeight.ExtraBold
        ),
        color = MaterialTheme.colorScheme.primary,
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 12.dp)
    )
}

@Composable
private fun PreferenceSwitchItem(
    icon: ImageVector,
    title: String,
    description: String,
    enabled: Boolean,
    onToggle: (Boolean) -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 6.dp)
            .clickable(
                interactionSource = remember { MutableInteractionSource() },
                indication = ripple(),
                onClick = { onToggle(!enabled) }
            ),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant
        ),
        elevation = CardDefaults.cardElevation(0.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Row(
                modifier = Modifier.weight(1f),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clip(RoundedCornerShape(8.dp))
                        .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.1f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = icon,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(20.dp)
                    )
                }
                
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(
                        text = title,
                        style = MaterialTheme.typography.titleSmall.copy(
                            fontWeight = FontWeight.Bold
                        ),
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = description,
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            
            Switch(
                checked = enabled,
                onCheckedChange = onToggle,
                modifier = Modifier.padding(start = 12.dp)
            )
        }
    }
}
