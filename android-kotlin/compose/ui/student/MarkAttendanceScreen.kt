package com.sams.app.ui.student

import android.Manifest
import android.util.Size
import androidx.camera.core.*
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.accompanist.permissions.ExperimentalPermissionsApi
import com.google.accompanist.permissions.isGranted
import com.google.accompanist.permissions.rememberMultiplePermissionsState
import com.sams.app.data.models.MarkAttendanceResponse
import com.sams.app.ui.theme.PresentColor
import com.sams.app.ui.theme.AbsentColor
import com.sams.app.utils.FaceDetectionHelper
import com.sams.app.utils.LocationHelper
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.util.concurrent.Executors

@OptIn(ExperimentalMaterial3Api::class, ExperimentalPermissionsApi::class)
@Composable
fun MarkAttendanceScreen(
    scheduleId: Int,
    teacherLat: Double,
    teacherLon: Double,
    subjectName: String,
    viewModel: StudentViewModel = hiltViewModel(),
    onBack: () -> Unit,
    onSuccess: () -> Unit
) {
    val uiState by viewModel.attendanceState.collectAsState()
    val storedEmbedding by viewModel.storedFaceEmbedding.collectAsState()
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val configuration = LocalConfiguration.current

    // Responsive sizing based on screen width
    val isSmallScreen = configuration.screenWidthDp < 360
    val screenPadding = if (isSmallScreen) 4.dp else 16.dp
    val iconSize = if (isSmallScreen) 48.dp else 72.dp
    val largeIconSize = if (isSmallScreen) 64.dp else 96.dp
    val cameraSize = if (isSmallScreen) 200.dp else 280.dp
    val progressWidth = if (isSmallScreen) 140.dp else 200.dp
    val titleStyle = if (isSmallScreen) MaterialTheme.typography.titleMedium else MaterialTheme.typography.titleLarge
    val bodyStyle = if (isSmallScreen) MaterialTheme.typography.bodySmall else MaterialTheme.typography.bodyMedium
    
    val permissionsState = rememberMultiplePermissionsState(
        permissions = listOf(
            Manifest.permission.CAMERA,
            Manifest.permission.ACCESS_FINE_LOCATION
        )
    )
    
    // Location and face detection helpers
    val locationHelper = remember { LocationHelper(context) }
    val faceDetectionHelper = remember { FaceDetectionHelper(context) }
    
    // Location state
    var studentLat by remember { mutableStateOf<Double?>(null) }
    var studentLon by remember { mutableStateOf<Double?>(null) }
    var distanceToTeacher by remember { mutableStateOf<Double?>(null) }
    var isWithinProximity by remember { mutableStateOf(false) }
    var locationError by remember { mutableStateOf<String?>(null) }
    
    // Face verification state
    var faceConfidence by remember { mutableStateOf<Double?>(null) }
    var verificationError by remember { mutableStateOf<String?>(null) }
    var isVerifying by remember { mutableStateOf(false) }
    
    // Load stored face embedding on start
    LaunchedEffect(Unit) {
        viewModel.loadStoredFaceEmbedding()
    }
    
    // Get current location when permissions granted
    LaunchedEffect(permissionsState.allPermissionsGranted) {
        if (permissionsState.allPermissionsGranted) {
            try {
                val location = locationHelper.getCurrentLocation()
                if (location != null) {
                    studentLat = location.latitude
                    studentLon = location.longitude
                    distanceToTeacher = locationHelper.getDistanceToTeacher(
                        location.latitude, location.longitude,
                        teacherLat, teacherLon
                    )
                    isWithinProximity = locationHelper.isWithinProximity(
                        location.latitude, location.longitude,
                        teacherLat, teacherLon
                    )
                } else {
                    locationError = "Unable to get location"
                }
            } catch (e: Exception) {
                locationError = e.message ?: "Location error"
            }
        }
    }
    
    LaunchedEffect(uiState) {
        if (uiState is StudentUiState.Success) {
            // Delay to show success message before navigation
            kotlinx.coroutines.delay(2000)
            viewModel.resetAttendanceState()
            onSuccess()
        }
    }
    
    DisposableEffect(Unit) {
        onDispose {
            viewModel.resetAttendanceState()
            faceDetectionHelper.close()
        }
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Mark Attendance") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                }
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(screenPadding),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Subject Name
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer
                )
            ) {
                Text(
                    text = subjectName,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(screenPadding),
                    textAlign = TextAlign.Center
                )
            }
            
            Spacer(modifier = Modifier.height(if (isSmallScreen) 16.dp else 24.dp))
            
            when {
                !permissionsState.allPermissionsGranted -> {
                    PermissionRequest(
                        onRequestPermission = { permissionsState.launchMultiplePermissionRequest() }
                    )
                }
                uiState is StudentUiState.Success -> {
                    val result = (uiState as StudentUiState.Success).data as MarkAttendanceResponse
                    SuccessContent(result)
                }
                uiState is StudentUiState.Error -> {
                    ErrorContent(
                        message = (uiState as StudentUiState.Error).message,
                        onRetry = { 
                            viewModel.resetAttendanceState()
                            verificationError = null
                        }
                    )
                }
                locationError != null -> {
                    ErrorContent(
                        message = locationError!!,
                        onRetry = { locationError = null }
                    )
                }
                storedEmbedding == null -> {
                    val isSmallScreen = configuration.screenWidthDp < 360
                    val iconSize = if (isSmallScreen) 56.dp else 72.dp
                    val titleStyle = if (isSmallScreen) MaterialTheme.typography.titleMedium else MaterialTheme.typography.titleLarge
                    val spacing = if (isSmallScreen) 12.dp else 16.dp
                    val largeSpacing = if (isSmallScreen) 20.dp else 24.dp

                    Column(
                        modifier = Modifier.fillMaxSize(),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Icon(
                            Icons.Default.Face,
                            contentDescription = null,
                            modifier = Modifier.size(iconSize),
                            tint = MaterialTheme.colorScheme.error
                        )
                        Spacer(modifier = Modifier.height(spacing))
                        Text(
                            "Face Not Registered",
                            style = titleStyle,
                            fontWeight = FontWeight.Bold
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            "Please register your face first before marking attendance.",
                            style = MaterialTheme.typography.bodyMedium,
                            textAlign = TextAlign.Center,
                            modifier = Modifier.padding(horizontal = if (isSmallScreen) 8.dp else 0.dp)
                        )
                        Spacer(modifier = Modifier.height(largeSpacing))
                        Button(onClick = onBack) {
                            Text("Go Back")
                        }
                    }
                }
                else -> {
                    // Show location status card
                    LocationStatusCard(
                        distance = distanceToTeacher,
                        isWithinProximity = isWithinProximity
                    )
                    
                    Spacer(modifier = Modifier.height(if (isSmallScreen) 12.dp else 16.dp))
                    
                    if (!isWithinProximity && distanceToTeacher != null) {
                        // Not within proximity
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(
                                containerColor = AbsentColor.copy(alpha = 0.1f)
                            )
                        ) {
                            Row(
                                modifier = Modifier.padding(16.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    Icons.Default.Warning,
                                    contentDescription = null,
                                    tint = AbsentColor
                                )
                                Spacer(modifier = Modifier.width(12.dp))
                                Text(
                                    "You must be within 50m of your teacher to mark attendance.",
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = AbsentColor
                                )
                            }
                        }
                    } else {
                        FaceVerificationStep(
                            storedEmbedding = storedEmbedding,
                            isVerifying = isVerifying,
                            verificationError = verificationError,
                            isMarkingAttendance = uiState is StudentUiState.Loading,
                            attendanceError = (uiState as? StudentUiState.Error)?.message,
                            onFaceVerified = { confidence, embedding ->
                                faceConfidence = confidence
                                verificationError = null
                                isVerifying = false

                                // Automatically mark attendance without confirmation
                                viewModel.markAttendance(
                                    scheduleId = scheduleId,
                                    latitude = studentLat!!,
                                    longitude = studentLon!!,
                                    faceConfidence = confidence
                                )
                            },
                            onVerificationStarted = {
                                isVerifying = true
                                verificationError = null
                            },
                            onVerificationFailed = { error ->
                                isVerifying = false
                                verificationError = error
                            }
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun PermissionRequest(onRequestPermission: () -> Unit) {
    val configuration = LocalConfiguration.current
    val isSmallScreen = configuration.screenWidthDp < 360
    val iconSize = if (isSmallScreen) 56.dp else 72.dp
    val titleStyle = if (isSmallScreen) MaterialTheme.typography.titleMedium else MaterialTheme.typography.titleLarge
    val spacing = if (isSmallScreen) 12.dp else 16.dp
    val largeSpacing = if (isSmallScreen) 20.dp else 24.dp

    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Icon(
            Icons.Default.CameraAlt,
            contentDescription = null,
            modifier = Modifier.size(iconSize),
            tint = MaterialTheme.colorScheme.primary
        )
        Spacer(modifier = Modifier.height(spacing))
        Text(
            "Camera & Location Required",
            style = titleStyle,
            fontWeight = FontWeight.Bold
        )
        Spacer(modifier = Modifier.height(8.dp))
        Text(
            "To mark attendance, we need access to your camera for face verification and location to verify you're in the classroom.",
            style = MaterialTheme.typography.bodyMedium,
            textAlign = TextAlign.Center,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.padding(horizontal = if (isSmallScreen) 8.dp else 0.dp)
        )
        Spacer(modifier = Modifier.height(largeSpacing))
        Button(onClick = onRequestPermission) {
            Text("Grant Permissions")
        }
    }
}

@Composable
private fun LocationStatusCard(
    distance: Double?,
    isWithinProximity: Boolean
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = if (isWithinProximity) 
                PresentColor.copy(alpha = 0.1f) 
            else 
                MaterialTheme.colorScheme.surfaceVariant
        )
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                Icons.Default.LocationOn,
                contentDescription = null,
                tint = if (isWithinProximity) PresentColor else MaterialTheme.colorScheme.onSurfaceVariant
            )
            Spacer(modifier = Modifier.width(12.dp))
            Column {
                Text(
                    if (isWithinProximity) "Within Range" else "Checking Location...",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold
                )
                if (distance != null) {
                    Text(
                        "Distance: ${distance.toInt()}m from teacher",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            Spacer(modifier = Modifier.weight(1f))
            if (isWithinProximity) {
                Icon(
                    Icons.Default.CheckCircle,
                    contentDescription = null,
                    tint = PresentColor
                )
            }
        }
    }
}


@Composable
private fun SuccessContent(result: MarkAttendanceResponse) {
    val configuration = LocalConfiguration.current
    val isSmallScreen = configuration.screenWidthDp < 360
    val iconSize = if (isSmallScreen) 80.dp else 96.dp
    val titleStyle = if (isSmallScreen) MaterialTheme.typography.titleLarge else MaterialTheme.typography.headlineSmall
    val spacing = if (isSmallScreen) 16.dp else 24.dp
    val cardPadding = if (isSmallScreen) 16.dp else 24.dp

    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Icon(
            Icons.Default.CheckCircle,
            contentDescription = null,
            modifier = Modifier.size(iconSize),
            tint = PresentColor
        )
        Spacer(modifier = Modifier.height(spacing))
        Text(
            "Attendance Marked!",
            style = titleStyle,
            fontWeight = FontWeight.Bold
        )
        Spacer(modifier = Modifier.height(16.dp))
        Card(
            colors = CardDefaults.cardColors(
                containerColor = PresentColor.copy(alpha = 0.1f)
            )
        ) {
            Column(
                modifier = Modifier.padding(cardPadding),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text("Face Confidence: ${result.faceConfidence?.toInt() ?: 0}%")
                Text("Distance: ${result.distanceMeters?.toInt() ?: 0}m")
                Text("Status: ${result.verificationStatus ?: "Success"}")
            }
        }
    }
}

@Composable
private fun ErrorContent(message: String, onRetry: () -> Unit) {
    val configuration = LocalConfiguration.current
    val isSmallScreen = configuration.screenWidthDp < 360
    val iconSize = if (isSmallScreen) 80.dp else 96.dp
    val titleStyle = if (isSmallScreen) MaterialTheme.typography.titleLarge else MaterialTheme.typography.headlineSmall
    val spacing = if (isSmallScreen) 16.dp else 24.dp

    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Icon(
            Icons.Default.Error,
            contentDescription = null,
            modifier = Modifier.size(iconSize),
            tint = AbsentColor
        )
        Spacer(modifier = Modifier.height(spacing))
        Text(
            "Verification Failed",
            style = titleStyle,
            fontWeight = FontWeight.Bold
        )
        Spacer(modifier = Modifier.height(8.dp))
        Text(
            message,
            style = MaterialTheme.typography.bodyMedium,
            textAlign = TextAlign.Center,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.padding(horizontal = if (isSmallScreen) 8.dp else 0.dp)
        )
        Spacer(modifier = Modifier.height(spacing))
        Button(onClick = onRetry) {
            Icon(Icons.Default.Refresh, contentDescription = null)
            Spacer(modifier = Modifier.width(8.dp))
            Text("Try Again")
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FaceVerificationStep(
    storedEmbedding: String?,
    isVerifying: Boolean,
    verificationError: String?,
    isMarkingAttendance: Boolean = false,
    attendanceError: String? = null,
    onFaceVerified: (confidence: Double, embedding: FloatArray) -> Unit,
    onVerificationStarted: () -> Unit,
    onVerificationFailed: (String) -> Unit
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()
    val configuration = LocalConfiguration.current

    // Responsive sizing
    val isSmallScreen = configuration.screenWidthDp < 360
    val iconSize = if (isSmallScreen) 48.dp else 72.dp
    val cameraSize = if (isSmallScreen) 200.dp else 280.dp
    val progressWidth = if (isSmallScreen) 140.dp else 200.dp
    val titleStyle = if (isSmallScreen) MaterialTheme.typography.titleMedium else MaterialTheme.typography.titleLarge
    val bodyStyle = if (isSmallScreen) MaterialTheme.typography.bodySmall else MaterialTheme.typography.bodyMedium
    val spacing = if (isSmallScreen) 8.dp else 16.dp
    val smallSpacing = if (isSmallScreen) 4.dp else 12.dp

    var faceDetected by remember { mutableStateOf(false) }
    var detectionStatus by remember { mutableStateOf("Position your face in the frame and blink or move slightly") }
    var currentConfidence by remember { mutableStateOf(0.0) }
    var capturedEmbedding by remember { mutableStateOf<FloatArray?>(null) }
    var hasAutoProceeded by remember { mutableStateOf(false) }
    var stableConfidenceCount by remember { mutableStateOf(0) }
    var verificationStartTime by remember { mutableStateOf(0L) }

    // Face detection helper
    val faceDetectionHelper = remember { FaceDetectionHelper(context) }

    // Cleanup
    DisposableEffect(Unit) {
        onDispose {
            faceDetectionHelper.close()
        }
    }

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.padding(if (isSmallScreen) 8.dp else 16.dp)
    ) {
        if (storedEmbedding == null) {
            // No face registered
            Icon(
                Icons.Default.Face,
                contentDescription = null,
                modifier = Modifier.size(iconSize),
                tint = MaterialTheme.colorScheme.error
            )
            Spacer(modifier = Modifier.height(spacing))
            Text(
                "Face Not Registered",
                style = titleStyle,
                fontWeight = FontWeight.Bold
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                "Please register your face first before marking attendance.",
                style = MaterialTheme.typography.bodyMedium,
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(horizontal = if (isSmallScreen) 4.dp else 0.dp)
            )
        } else {
            // Camera preview with face detection
            Text(
                text = "Face Verification",
                style = titleStyle,
                fontWeight = FontWeight.Bold
            )

            Spacer(modifier = Modifier.height(spacing))

            // Camera preview
            Box(
                modifier = Modifier
                    .size(cameraSize)
                    .clip(CircleShape)
                    .border(
                        width = 4.dp,
                        color = when {
                            isVerifying -> Color.Yellow
                            faceDetected && currentConfidence >= 75.0 -> PresentColor
                            faceDetected -> MaterialTheme.colorScheme.primary
                            else -> Color.Gray
                        },
                        shape = CircleShape
                    ),
                contentAlignment = Alignment.Center
            ) {
                FaceVerificationCamera(
                    faceDetectionHelper = faceDetectionHelper,
                    storedEmbedding = storedEmbedding,
                    onFaceDetected = { confidence, embedding ->
                        faceDetected = true
                        currentConfidence = confidence
                        capturedEmbedding = embedding

                        // Start timing when we first detect a face
                        if (verificationStartTime == 0L) {
                            verificationStartTime = System.currentTimeMillis()
                        }

                        val elapsedSeconds = (System.currentTimeMillis() - verificationStartTime) / 1000.0

                        // Track confidence stability - require consistent high confidence
                        if (confidence >= 75.0) {
                            stableConfidenceCount++

                            // Update status based on progress
                            when {
                                elapsedSeconds < 2.0 -> {
                                    detectionStatus = "Verifying... ${String.format("%.1f", confidence)}% match (keep looking at camera)"
                                }
                                stableConfidenceCount >= 3 && elapsedSeconds >= 2.0 && !hasAutoProceeded -> {
                                    hasAutoProceeded = true
                                    detectionStatus = "Face verified! ${String.format("%.1f", confidence)}% match"
                                    onFaceVerified(confidence, embedding)
                                }
                                stableConfidenceCount >= 3 -> {
                                    detectionStatus = "Face verified! ${String.format("%.1f", confidence)}% match"
                                }
                                else -> {
                                    detectionStatus = "Almost there... ${String.format("%.1f", confidence)}% match (hold steady)"
                                }
                            }
                        } else {
                            stableConfidenceCount = 0 // Reset if confidence drops
                            if (elapsedSeconds < 2.0) {
                                detectionStatus = "Verifying... ${String.format("%.1f", confidence)}% match (keep looking at camera)"
                            } else {
                                detectionStatus = "Face detected - ${String.format("%.1f", confidence)}% match (need 75%+ for ${3 - stableConfidenceCount} more checks)"
                            }
                        }
                    },
                    onNoFaceDetected = {
                        faceDetected = false
                        capturedEmbedding = null
                        stableConfidenceCount = 0 // Reset stability counter
                        verificationStartTime = 0L // Reset timing
                        detectionStatus = "No face detected - Position your face in the frame and blink or move slightly"
                    },
                    modifier = Modifier.fillMaxSize()
                )
            }

            Spacer(modifier = Modifier.height(spacing))

            // Status text
            Text(
                text = detectionStatus,
                style = bodyStyle,
                color = when {
                    faceDetected && currentConfidence >= 75.0 -> PresentColor
                    faceDetected -> MaterialTheme.colorScheme.primary
                    else -> MaterialTheme.colorScheme.onSurfaceVariant
                },
                textAlign = TextAlign.Center,
                modifier = Modifier.padding(horizontal = if (isSmallScreen) 4.dp else 0.dp)
            )

            // Verification progress indicator
            if (faceDetected && verificationStartTime > 0L && !hasAutoProceeded) {
                val elapsedSeconds = (System.currentTimeMillis() - verificationStartTime) / 1000.0
                val progress = (elapsedSeconds / 3.0).coerceIn(0.0, 1.0) // 3 seconds for full verification

                Spacer(modifier = Modifier.height(smallSpacing))

                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        text = "Verifying...",
                        style = if (isSmallScreen) MaterialTheme.typography.bodySmall else MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )

                    Spacer(modifier = Modifier.height(8.dp))

                    LinearProgressIndicator(
                        progress = progress.toFloat(),
                        modifier = Modifier
                            .width(progressWidth)
                            .height(if (isSmallScreen) 3.dp else 4.dp)
                            .clip(RoundedCornerShape(2.dp)),
                        color = if (stableConfidenceCount >= 3) PresentColor else MaterialTheme.colorScheme.primary,
                        trackColor = Color.Gray.copy(alpha = 0.3f),
                    )

                    Spacer(modifier = Modifier.height(4.dp))

                    Text(
                        text = "${String.format("%.1f", elapsedSeconds)}s / 3.0s",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }

            // Confidence indicator
            if (faceDetected) {
                Spacer(modifier = Modifier.height(smallSpacing))

                Column(modifier = Modifier.fillMaxWidth()) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text("Confidence", style = MaterialTheme.typography.labelSmall)
                        Text(
                            "${String.format("%.1f", currentConfidence)}%",
                            style = MaterialTheme.typography.labelSmall,
                            fontWeight = FontWeight.Bold,
                            color = if (currentConfidence >= 75.0) PresentColor else MaterialTheme.colorScheme.primary
                        )
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                    LinearProgressIndicator(
                        progress = (currentConfidence / 100).toFloat().coerceIn(0f, 1f),
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(8.dp)
                            .clip(RoundedCornerShape(4.dp)),
                        color = if (currentConfidence >= 75.0) PresentColor else MaterialTheme.colorScheme.primary,
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = "Minimum required: 75%",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }

            // Show attendance error if any
            attendanceError?.let {
                Spacer(modifier = Modifier.height(spacing))
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = AbsentColor.copy(alpha = 0.1f)
                    )
                ) {
                    Row(
                        modifier = Modifier.padding(if (isSmallScreen) 8.dp else 12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.Warning, contentDescription = null, tint = AbsentColor)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = it,
                            color = AbsentColor,
                            style = MaterialTheme.typography.bodySmall,
                            modifier = Modifier.weight(1f)
                        )
                    }
                }
            }

            if (verificationError != null) {
                Spacer(modifier = Modifier.height(spacing))
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = AbsentColor.copy(alpha = 0.1f)
                    )
                ) {
                    Row(
                        modifier = Modifier.padding(if (isSmallScreen) 8.dp else 12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.Warning, contentDescription = null, tint = AbsentColor)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = verificationError,
                            color = AbsentColor,
                            style = MaterialTheme.typography.bodySmall,
                            modifier = Modifier.weight(1f)
                        )
                    }
                }
            }
        }

        // Loading overlay when marking attendance
        if (isMarkingAttendance) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.Black.copy(alpha = 0.7f)),
                contentAlignment = Alignment.Center
            ) {
                Card(
                    modifier = Modifier.padding(if (isSmallScreen) 16.dp else 32.dp),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Column(
                        modifier = Modifier.padding(if (isSmallScreen) 20.dp else 32.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(if (isSmallScreen) 40.dp else 48.dp),
                            color = MaterialTheme.colorScheme.primary
                        )

                        Spacer(modifier = Modifier.height(if (isSmallScreen) 12.dp else 16.dp))

                        Text(
                            text = "Marking Attendance...",
                            style = if (isSmallScreen) MaterialTheme.typography.titleMedium else MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold
                        )

                        Spacer(modifier = Modifier.height(8.dp))

                        Text(
                            text = "Please wait while we process your attendance",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            textAlign = TextAlign.Center,
                            modifier = Modifier.padding(horizontal = if (isSmallScreen) 4.dp else 0.dp)
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun FaceVerificationCamera(
    faceDetectionHelper: FaceDetectionHelper,
    storedEmbedding: String,
    onFaceDetected: (confidence: Double, embedding: FloatArray) -> Unit,
    onNoFaceDetected: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val previewView = remember { PreviewView(context) }
    val scope = rememberCoroutineScope()
    val frameCounter = remember { java.util.concurrent.atomic.AtomicInteger(0) }

    val storedEmbeddingArray = remember(storedEmbedding) {
        faceDetectionHelper.stringToEmbedding(storedEmbedding)
    }

    LaunchedEffect(Unit) {
        val cameraProviderFuture = ProcessCameraProvider.getInstance(context)
        val cameraProvider = cameraProviderFuture.get()

        val preview = Preview.Builder().build().also {
            it.setSurfaceProvider(previewView.surfaceProvider)
        }

        val imageAnalyzer = ImageAnalysis.Builder()
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .setTargetResolution(Size(480, 640))  // Lower resolution for faster processing
            .build()
            .also { analysis ->
                analysis.setAnalyzer(Executors.newSingleThreadExecutor()) { imageProxy ->
                    val frame = frameCounter.incrementAndGet()
                    // Process every 3rd frame for better performance
                    if (frame % 3 != 0) {
                        imageProxy.close()
                        return@setAnalyzer
                    }
                    scope.launch {
                        try {
                            val faces = faceDetectionHelper.detectFaces(imageProxy)
                            if (faces.isNotEmpty()) {
                                val face = faces.first()

                                // Check liveness first - prevent photo/video spoofing
                                val livenessResult = faceDetectionHelper.checkLiveness(face)
                                if (!livenessResult.isProbablyLive()) {
                                    onNoFaceDetected() // Treat as no face if not live
                                    imageProxy.close()
                                    return@launch
                                }

                                // Convert ImageProxy to bitmap for embedding extraction
                                val bitmap = faceDetectionHelper.imageProxyToBitmap(imageProxy)
                                if (bitmap != null) {
                                    val currentEmbedding = faceDetectionHelper.extractFaceEmbedding(face, bitmap)
                                    val confidence = faceDetectionHelper.compareFaces(currentEmbedding, storedEmbeddingArray)

                                    onFaceDetected(confidence, currentEmbedding)
                                } else {
                                    onNoFaceDetected()
                                }
                            } else {
                                onNoFaceDetected()
                            }
                        } catch (e: Exception) {
                            onNoFaceDetected()
                        } finally {
                            imageProxy.close()
                        }
                    }
                }
            }

        val cameraSelector = CameraSelector.DEFAULT_FRONT_CAMERA

        try {
            cameraProvider.unbindAll()
            cameraProvider.bindToLifecycle(
                lifecycleOwner,
                cameraSelector,
                preview,
                imageAnalyzer
            )
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    AndroidView(
        factory = { previewView },
        modifier = modifier
    )
}
