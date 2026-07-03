package com.sams.app.ui.student

import android.Manifest
import android.os.Build
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.runtime.*
import androidx.compose.runtime.mutableStateOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.accompanist.permissions.ExperimentalPermissionsApi
import com.google.accompanist.permissions.rememberMultiplePermissionsState
import com.sams.app.data.models.ActiveAttendanceCheck
import com.sams.app.data.models.ActiveAttendanceChecksData
import com.sams.app.data.models.ActiveClassSession
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class, ExperimentalPermissionsApi::class)
@Composable
fun ActiveChecksScreen(
    viewModel: StudentViewModel = hiltViewModel(),
    onNavigateBack: () -> Unit,
    onNavigateToRespondToCheck: (Int, Double, Double, String) -> Unit
) {
    val activeChecksState by viewModel.activeChecksState.collectAsState()
    
    // Track if there are active sessions (to prevent leaving)
    val hasActiveSessions = remember(activeChecksState) {
        val data = (activeChecksState as? StudentUiState.Success)?.data as? ActiveAttendanceChecksData
        data?.activeSessions?.isNotEmpty() == true || data?.hasRandomIntervals == true
    }
    
    // Show confirmation dialog when trying to leave with active sessions
    var showLeaveDialog by remember { mutableStateOf(false) }
    
    // Request location permissions
    val permissionsState = rememberMultiplePermissionsState(
        permissions = listOf(
            Manifest.permission.ACCESS_FINE_LOCATION,
            Manifest.permission.ACCESS_COARSE_LOCATION,
            Manifest.permission.CAMERA
        )
    )
    
    // Auto-refresh every 10 seconds to detect new random interval checks
    LaunchedEffect(Unit) {
        viewModel.loadActiveAttendanceChecks()
        if (!permissionsState.allPermissionsGranted) {
            permissionsState.launchMultiplePermissionRequest()
        }
    }
    
    // Auto-refresh polling — always poll while on this screen
    LaunchedEffect(Unit) {
        while (true) {
            kotlinx.coroutines.delay(10_000L)
            viewModel.loadActiveAttendanceChecks()
        }
    }
    
    // Confirmation dialog when trying to leave
    if (showLeaveDialog) {
        AlertDialog(
            onDismissRequest = { showLeaveDialog = false },
            icon = { Icon(Icons.Default.Warning, contentDescription = null) },
            title = { Text("Leave Attendance Screen?") },
            text = { 
                Text("You have remaining attendance checks. If you leave, you may miss them and be marked absent for those checks.")
            },
            confirmButton = {
                TextButton(onClick = { 
                    showLeaveDialog = false
                    onNavigateBack()
                }) {
                    Text("Leave Anyway")
                }
            },
            dismissButton = {
                Button(onClick = { showLeaveDialog = false }) {
                    Text("Stay Here")
                }
            }
        )
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Active Attendance Checks") },
                navigationIcon = {
                    IconButton(onClick = { 
                        if (hasActiveSessions) {
                            showLeaveDialog = true
                        } else {
                            onNavigateBack()
                        }
                    }) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back")
                    }
                },
                actions = {
                    IconButton(onClick = { viewModel.loadActiveAttendanceChecks() }) {
                        Icon(Icons.Default.Refresh, "Refresh")
                    }
                }
            )
        }
    ) { padding ->
        when (val state = activeChecksState) {
            is StudentUiState.Loading -> {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(padding),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator()
                }
            }
            
            is StudentUiState.Success -> {
                val data = state.data as ActiveAttendanceChecksData
                
                // Check if all sessions have completed all checks
                val allComplete = data.activeSessions.isNotEmpty() && data.activeChecks.isEmpty() &&
                    data.activeSessions.all { it.totalChecksPlanned > 0 && it.studentSuccessfulChecks >= it.totalChecksPlanned }

                if (allComplete) {
                    Box(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(padding),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally,
                            modifier = Modifier.padding(32.dp)
                        ) {
                            Icon(
                                Icons.Default.CheckCircle,
                                contentDescription = null,
                                modifier = Modifier.size(80.dp),
                                tint = MaterialTheme.colorScheme.primary
                            )
                            Spacer(modifier = Modifier.height(16.dp))
                            Text(
                                "All Checks Complete!",
                                style = MaterialTheme.typography.headlineSmall,
                                fontWeight = FontWeight.Bold
                            )
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                "Your attendance has been marked successfully.",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                                textAlign = TextAlign.Center
                            )
                            Spacer(modifier = Modifier.height(24.dp))
                            Button(onClick = onNavigateBack) {
                                Text("Back to Dashboard")
                            }
                        }
                    }
                } else if (data.activeChecks.isEmpty() && data.activeSessions.isEmpty()) {
                    Box(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(padding),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(
                                Icons.Default.CheckCircle,
                                contentDescription = null,
                                modifier = Modifier.size(64.dp),
                                tint = MaterialTheme.colorScheme.primary
                            )
                            Spacer(modifier = Modifier.height(16.dp))
                            Text(
                                "No Active Checks",
                                style = MaterialTheme.typography.titleLarge
                            )
                            Text(
                                "You're all caught up!",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                } else if (data.activeChecks.isEmpty() && data.activeSessions.isNotEmpty()) {
                    // No active check NOW but there are active sessions (random intervals)
                    LazyColumn(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(padding),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        // Show "Stay on screen" banner if timing is hidden
                        if (data.stayOnScreen || data.hasRandomIntervals) {
                            item {
                                Card(
                                    colors = CardDefaults.cardColors(
                                        containerColor = MaterialTheme.colorScheme.tertiaryContainer
                                    )
                                ) {
                                    Column(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .padding(16.dp),
                                        horizontalAlignment = Alignment.CenterHorizontally
                                    ) {
                                        Icon(
                                            Icons.Default.Timer,
                                            contentDescription = null,
                                            modifier = Modifier.size(48.dp),
                                            tint = MaterialTheme.colorScheme.tertiary
                                        )
                                        Spacer(modifier = Modifier.height(8.dp))
                                        Text(
                                            "Stay on This Screen",
                                            style = MaterialTheme.typography.titleMedium,
                                            fontWeight = FontWeight.Bold
                                        )
                                        Text(
                                            "Random attendance checks are enabled. The next check will appear automatically at a random time.",
                                            style = MaterialTheme.typography.bodySmall,
                                            color = MaterialTheme.colorScheme.onTertiaryContainer,
                                            textAlign = androidx.compose.ui.text.style.TextAlign.Center
                                        )
                                    }
                                }
                            }
                        }
                        
                        // Show active sessions info
                        items(data.activeSessions) { session ->
                            ActiveSessionCard(session = session)
                        }
                    }
                } else {
                    // There are active checks to respond to
                    LazyColumn(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(padding),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        item {
                            Card(
                                colors = CardDefaults.cardColors(
                                    containerColor = MaterialTheme.colorScheme.primaryContainer
                                )
                            ) {
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(16.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(
                                        "Pending Checks",
                                        style = MaterialTheme.typography.titleMedium,
                                        fontWeight = FontWeight.Bold
                                    )
                                    Badge {
                                        Text("${data.totalPending}")
                                    }
                                }
                            }
                        }
                        
                        items(data.activeChecks) { check ->
                            ActiveCheckCard(
                                check = check,
                                onRespond = { 
                                    onNavigateToRespondToCheck(
                                        check.checkPointId,
                                        check.teacherLatitude,
                                        check.teacherLongitude,
                                        check.subjectName
                                    )
                                }
                            )
                        }
                        
                        // Show remaining sessions info if there are more checks coming
                        if (data.stayOnScreen && data.activeSessions.isNotEmpty()) {
                            item {
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    "More checks coming at random times...",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    modifier = Modifier.padding(horizontal = 4.dp)
                                )
                            }
                        }
                    }
                }
            }
            
            is StudentUiState.Error -> {
                // Auto-retry on error after 5 seconds
                LaunchedEffect(state) {
                    kotlinx.coroutines.delay(5_000L)
                    viewModel.loadActiveAttendanceChecks()
                }
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(padding),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(48.dp)
                        )
                        Spacer(modifier = Modifier.height(16.dp))
                        Text(
                            "Connecting...",
                            style = MaterialTheme.typography.bodyLarge,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            "Retrying automatically",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Spacer(modifier = Modifier.height(16.dp))
                        OutlinedButton(onClick = { viewModel.loadActiveAttendanceChecks() }) {
                            Text("Retry Now")
                        }
                    }
                }
            }
            
            else -> {}
        }
    }
}

@Composable
private fun ActiveCheckCard(
    check: ActiveAttendanceCheck,
    onRespond: () -> Unit
) {
    // Only show time remaining if timing is not hidden
    val timeRemaining = if (!check.hideTiming && check.secondsRemaining != null) {
        remember(check.secondsRemaining) {
            val minutes = check.secondsRemaining / 60
            val seconds = check.secondsRemaining % 60
            String.format("%d:%02d", minutes, seconds)
        }
    } else null
    
    val isUrgent = !check.hideTiming && (check.secondsRemaining ?: 0) < 120
    val isExpired = check.isExpired
    
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = when {
                isExpired -> MaterialTheme.colorScheme.errorContainer
                isUrgent -> MaterialTheme.colorScheme.tertiaryContainer
                else -> MaterialTheme.colorScheme.surface
            }
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        check.subjectName,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        check.subjectCode,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                
                AssistChip(
                    onClick = {},
                    label = { Text("Check #${check.checkNumber}") },
                    leadingIcon = {
                        Icon(Icons.Default.Notifications, contentDescription = null, modifier = Modifier.size(16.dp))
                    }
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                InfoChip(
                    icon = Icons.Default.Person,
                    text = check.teacherName
                )
                if (check.classroom != null) {
                    InfoChip(
                        icon = Icons.Default.LocationOn,
                        text = check.classroom
                    )
                }
            }
            
            // Show progress info (check X of Y) if available
            if (check.totalChecksPlanned > 0) {
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    "Check ${check.checkNumber} of ${check.totalChecksPlanned}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.primary
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            HorizontalDivider()
            Spacer(modifier = Modifier.height(12.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    if (check.hideTiming) {
                        // Hidden timing mode: show alert message instead of countdown
                        Text(
                            "ACTIVE NOW",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.error
                        )
                        Text(
                            check.message ?: "Respond immediately!",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.error
                        )
                    } else {
                        // Normal mode: show time remaining
                        Text(
                            "Time Remaining",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Text(
                            if (isExpired) "Expired" else (timeRemaining ?: "Active"),
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold,
                            color = when {
                                isExpired -> MaterialTheme.colorScheme.error
                                isUrgent -> MaterialTheme.colorScheme.tertiary
                                else -> MaterialTheme.colorScheme.primary
                            }
                        )
                    }
                }
                
                Button(
                    onClick = onRespond,
                    enabled = !isExpired
                ) {
                    Icon(Icons.Default.CheckCircle, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Respond Now")
                }
            }
        }
    }
}

@Composable
private fun InfoChip(icon: androidx.compose.ui.graphics.vector.ImageVector, text: String) {
    AssistChip(
        onClick = {},
        label = { Text(text, style = MaterialTheme.typography.bodySmall) },
        leadingIcon = {
            Icon(icon, contentDescription = null, modifier = Modifier.size(16.dp))
        }
    )
}

@Composable
private fun ActiveSessionCard(session: ActiveClassSession) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surfaceVariant
        )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        session.subjectName,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        session.subjectCode,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                
                // Progress indicator
                Badge(
                    containerColor = MaterialTheme.colorScheme.primary
                ) {
                    Text("${session.studentSuccessfulChecks}/${session.totalChecksPlanned}")
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                InfoChip(
                    icon = Icons.Default.Person,
                    text = session.teacherName
                )
                if (session.classroom != null) {
                    InfoChip(
                        icon = Icons.Default.LocationOn,
                        text = session.classroom
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Show session status message
            session.message?.let { message ->
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.secondaryContainer.copy(alpha = 0.5f)
                    )
                ) {
                    Row(
                        modifier = Modifier.padding(12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            Icons.Default.Info,
                            contentDescription = null,
                            modifier = Modifier.size(18.dp),
                            tint = MaterialTheme.colorScheme.secondary
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            message,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSecondaryContainer
                        )
                    }
                }
            }
            
            // Progress bar for checks
            if (session.totalChecksPlanned > 0) {
                Spacer(modifier = Modifier.height(12.dp))
                Column {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            "Your Progress",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Text(
                            "${session.checksRemaining} check(s) remaining",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                    LinearProgressIndicator(
                        progress = { 
                            session.studentSuccessfulChecks.toFloat() / session.totalChecksPlanned.coerceAtLeast(1)
                        },
                        modifier = Modifier.fillMaxWidth(),
                        color = MaterialTheme.colorScheme.primary,
                        trackColor = MaterialTheme.colorScheme.primary.copy(alpha = 0.2f)
                    )
                }
            }
        }
    }
}
