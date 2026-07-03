package com.sams.app.ui.student

import android.Manifest
import android.content.Context
import android.os.Build
import androidx.activity.compose.BackHandler
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.accompanist.permissions.ExperimentalPermissionsApi
import com.google.accompanist.permissions.rememberMultiplePermissionsState
import com.google.mlkit.vision.face.FaceDetection
import com.google.mlkit.vision.face.FaceDetectorOptions
import com.sams.app.data.models.ActiveAttendanceChecksData
import kotlinx.coroutines.delay
import java.text.SimpleDateFormat
import java.util.*

/**
 * Continuous Attendance Screen
 * Student stays on this screen for entire class duration
 * Auto-responds to attendance checks with ML Kit face detection
 */
@OptIn(ExperimentalMaterial3Api::class, ExperimentalPermissionsApi::class)
@Composable
fun ContinuousAttendanceScreen(
    sessionId: Int,
    subjectName: String? = null,
    expectedEndTime: String? = null,
    totalChecksPlanned: Int? = null,
    viewModel: StudentViewModel = hiltViewModel(),
    onNavigateBack: () -> Unit,
    onSessionComplete: () -> Unit
) {
    val context = LocalContext.current
    val activeChecksState by viewModel.activeChecksState.collectAsState()
    val checkResponseState by viewModel.checkResponseState.collectAsState()
    val configState by viewModel.continuousMonitoringConfigState.collectAsState()
    
    var currentCheckNumber by remember { mutableStateOf(0) }
    var totalChecks by remember { mutableStateOf(totalChecksPlanned ?: 3) }
    var successfulChecks by remember { mutableStateOf(0) }
    var isSessionActive by remember { mutableStateOf(true) }
    var showExitDialog by remember { mutableStateOf(false) }
    var isLoading by remember { mutableStateOf(true) }
    
    // Backend config
    var displaySubjectName by remember { mutableStateOf(subjectName ?: "") }
    var sessionEndTime by remember { mutableStateOf(expectedEndTime ?: "") }
    var pollingIntervalSeconds by remember { mutableStateOf(10) }
    var faceDetectionIntervalSeconds by remember { mutableStateOf(30) }
    var livenessEnabled by remember { mutableStateOf(true) }
    var livenessMinScore by remember { mutableStateOf(60) }
    var faceConfidenceThreshold by remember { mutableStateOf(75) }
    var autoResponseEnabled by remember { mutableStateOf(true) }
    
    // Load configuration from backend
    LaunchedEffect(sessionId) {
        viewModel.loadContinuousMonitoringConfig(sessionId)
    }
    
    // Handle config state
    LaunchedEffect(configState) {
        when (val state = configState) {
            is StudentUiState.Success -> {
                val config = state.data as? com.sams.app.data.models.ContinuousMonitoringConfig
                config?.let {
                    displaySubjectName = it.session.subjectName
                    sessionEndTime = it.session.expectedEnd
                    totalChecks = it.session.totalChecksPlanned
                    pollingIntervalSeconds = 10 // Fixed for active checks
                    faceDetectionIntervalSeconds = it.settings.faceDetectionIntervalSeconds
                    livenessEnabled = it.settings.livenessDetectionEnabled
                    livenessMinScore = it.settings.livenessMinScore
                    faceConfidenceThreshold = it.settings.faceConfidenceThreshold
                    autoResponseEnabled = it.settings.autoResponseEnabled
                    isLoading = false
                }
            }
            is StudentUiState.Error -> {
                isLoading = false
                // Use defaults if config fails to load
            }
            else -> {
                isLoading = true
            }
        }
    }
    
    // Permissions
    val permissionsState = rememberMultiplePermissionsState(
        permissions = listOf(
            Manifest.permission.CAMERA,
            Manifest.permission.ACCESS_FINE_LOCATION,
            Manifest.permission.ACCESS_COARSE_LOCATION
        )
    )
    
    // ML Kit Face Detector with liveness detection
    val faceDetectorOptions = remember(livenessEnabled) {
        FaceDetectorOptions.Builder()
            .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_ACCURATE)
            .setLandmarkMode(if (livenessEnabled) FaceDetectorOptions.LANDMARK_MODE_ALL else FaceDetectorOptions.LANDMARK_MODE_NONE)
            .setClassificationMode(if (livenessEnabled) FaceDetectorOptions.CLASSIFICATION_MODE_ALL else FaceDetectorOptions.CLASSIFICATION_MODE_NONE)
            .setMinFaceSize(0.15f)
            .enableTracking()
            .build()
    }
    
    val faceDetector = remember { FaceDetection.getClient(faceDetectorOptions) }
    
    // Polling for active checks
    LaunchedEffect(isSessionActive, pollingIntervalSeconds) {
        if (!isLoading) {
            permissionsState.launchMultiplePermissionRequest()
            
            while (isSessionActive) {
                viewModel.loadActiveAttendanceChecks()
                delay(pollingIntervalSeconds * 1000L)
                
                // Check if session ended
                if (sessionEndTime.isNotEmpty()) {
                    val now = System.currentTimeMillis()
                    val endTime = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
                        .parse(sessionEndTime)?.time ?: 0
                    
                    if (now >= endTime) {
                        isSessionActive = false
                        onSessionComplete()
                    }
                }
            }
        }
    }
    
    // Handle new checks with auto-response
    LaunchedEffect(activeChecksState, autoResponseEnabled) {
        if (activeChecksState is StudentUiState.Success && autoResponseEnabled) {
            val data = (activeChecksState as StudentUiState.Success).data as? ActiveAttendanceChecksData
            data?.activeChecks?.firstOrNull()?.let { check ->
                if (check.checkNumber > currentCheckNumber && !check.isExpired) {
                    currentCheckNumber = check.checkNumber
                    // TODO: Auto-trigger attendance marking
                    // This would capture face and location, then respond
                    // For now, just increment successful checks when implemented
                }
            }
        }
    }
    
    // Prevent back navigation during active session
    BackHandler(enabled = isSessionActive) {
        showExitDialog = true
    }
    
    if (isLoading) {
        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.Center
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                CircularProgressIndicator()
                Spacer(modifier = Modifier.height(16.dp))
                Text("Loading session configuration...")
            }
        }
        return
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Continuous Attendance") },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer
                )
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Subject info
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.surface
                )
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text(
                        displaySubjectName,
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        "Stay on this screen until class ends",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            
            // Progress indicator
            Card(
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp)
                ) {
                    Text(
                        "Attendance Progress",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceEvenly
                    ) {
                        ProgressItem(
                            label = "Checks Done",
                            value = "$currentCheckNumber",
                            total = "/$totalChecks",
                            color = MaterialTheme.colorScheme.primary
                        )
                        ProgressItem(
                            label = "Successful",
                            value = "$successfulChecks",
                            total = "/$totalChecks",
                            color = MaterialTheme.colorScheme.tertiary
                        )
                    }
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    LinearProgressIndicator(
                        progress = if (totalChecks > 0) currentCheckNumber.toFloat() / totalChecks else 0f,
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            }
            
            // Active monitoring indicator
            MonitoringIndicator(isActive = isSessionActive)
            
            Spacer(modifier = Modifier.weight(1f))
            
            // Instructions
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.secondaryContainer
                )
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp)
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Info, contentDescription = null)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            "Important Instructions",
                            style = MaterialTheme.typography.titleSmall,
                            fontWeight = FontWeight.Bold
                        )
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        "• Keep your phone unlocked\n" +
                        "• Stay in well-lit area\n" +
                        "• Face the camera when check triggers\n" +
                        "• Don't close the app\n" +
                        "• You'll be notified for each check",
                        style = MaterialTheme.typography.bodySmall
                    )
                }
            }
            
            // Exit button
            if (isSessionActive) {
                OutlinedButton(
                    onClick = { showExitDialog = true },
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Icon(Icons.Default.ExitToApp, contentDescription = null)
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Exit Session (will affect attendance)")
                }
            }
        }
    }
    
    // Exit confirmation dialog
    if (showExitDialog) {
        AlertDialog(
            onDismissRequest = { showExitDialog = false },
            icon = { Icon(Icons.Default.Warning, contentDescription = null) },
            title = { Text("Exit Session?") },
            text = { 
                Text("Exiting now may mark you absent. Are you sure you want to leave?")
            },
            confirmButton = {
                Button(onClick = {
                    isSessionActive = false
                    onNavigateBack()
                }) {
                    Text("Yes, Exit")
                }
            },
            dismissButton = {
                TextButton(onClick = { showExitDialog = false }) {
                    Text("Stay")
                }
            }
        )
    }
}

@Composable
private fun ProgressItem(
    label: String,
    value: String,
    total: String,
    color: Color
) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(
            label,
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Row(
            verticalAlignment = Alignment.Bottom
        ) {
            Text(
                value,
                style = MaterialTheme.typography.displaySmall,
                fontWeight = FontWeight.Bold,
                color = color
            )
            Text(
                total,
                style = MaterialTheme.typography.titleLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun MonitoringIndicator(isActive: Boolean) {
    val infiniteTransition = rememberInfiniteTransition(label = "pulse")
    val alpha by infiniteTransition.animateFloat(
        initialValue = 0.3f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(1000, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "alpha"
    )
    
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = if (isActive) {
            CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer.copy(alpha = alpha)
            )
        } else {
            CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.errorContainer
            )
        }
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(12.dp)
                    .clip(CircleShape)
                    .background(if (isActive) Color.Green else Color.Red)
            )
            Spacer(modifier = Modifier.width(12.dp))
            Text(
                if (isActive) "Monitoring Active - Ready for checks" else "Session Ended",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold
            )
        }
    }
}
