package com.sams.app.ui.student

import android.Manifest
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.ImageFormat
import android.graphics.Rect
import android.graphics.YuvImage
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.animation.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.accompanist.permissions.ExperimentalPermissionsApi
import com.google.accompanist.permissions.isGranted
import com.google.accompanist.permissions.rememberMultiplePermissionsState
import com.google.android.gms.location.LocationServices
import com.sams.app.data.models.MarkAttendanceResponse
import com.sams.app.ui.components.*
import com.sams.app.ui.theme.*
import com.sams.app.utils.FaceDetectionHelper
import kotlinx.coroutines.launch
import kotlinx.coroutines.tasks.await
import java.io.ByteArrayOutputStream
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
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    
    var currentStep by remember { mutableStateOf(0) } // 0: Location, 1: Face, 2: Confirm
    var userLatitude by remember { mutableStateOf(0.0) }
    var userLongitude by remember { mutableStateOf(0.0) }
    var distanceToTeacher by remember { mutableStateOf(0.0) }
    var faceVerified by remember { mutableStateOf(false) }
    var faceConfidence by remember { mutableStateOf(0.0) }
    var locationError by remember { mutableStateOf<String?>(null) }
    var faceVerificationError by remember { mutableStateOf<String?>(null) }
    var isVerifyingFace by remember { mutableStateOf(false) }
    var capturedFaceEmbedding by remember { mutableStateOf<FloatArray?>(null) }
    
    // Get stored face embedding for comparison 
    val storedFaceEmbedding by viewModel.storedFaceEmbedding.collectAsState()
    val faceThreshold by viewModel.faceThreshold.collectAsState()
    
    // Load stored face embedding when screen opens
    LaunchedEffect(Unit) {
        viewModel.loadStoredFaceEmbedding()
    }
    
    // Calculate distance between two GPS coordinates using Haversine formula
    fun calculateDistance(lat1: Double, lon1: Double, lat2: Double, lon2: Double): Double {
        val earthRadius = 6371000.0 // meters
        val dLat = Math.toRadians(lat2 - lat1)
        val dLon = Math.toRadians(lon2 - lon1)
        val a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(Math.toRadians(lat1)) * Math.cos(Math.toRadians(lat2)) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2)
        val c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
        return earthRadius * c
    }
    
    val locationPermissions = rememberMultiplePermissionsState(
        listOf(
            Manifest.permission.ACCESS_FINE_LOCATION,
            Manifest.permission.ACCESS_COARSE_LOCATION
        )
    )
    
    val cameraPermissionState = com.google.accompanist.permissions.rememberPermissionState(
        Manifest.permission.CAMERA
    )
    
    LaunchedEffect(uiState) {
        if (uiState is StudentUiState.Success) {
            currentStep = 3 // Success step
        }
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Mark Attendance", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Color.Transparent
                )
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(16.dp)
        ) {
            // Subject Info Card
            SAMSCard(modifier = Modifier.fillMaxWidth()) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(
                            brush = Brush.horizontalGradient(
                                colors = listOf(PrimaryBlueContainer, AccentPurpleContainer)
                            )
                        )
                        .padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        modifier = Modifier
                            .size(48.dp)
                            .clip(RoundedCornerShape(12.dp))
                            .background(Color.White),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.School,
                            contentDescription = null,
                            tint = PrimaryBlue
                        )
                    }
                    Spacer(modifier = Modifier.width(12.dp))
                    Column {
                        Text(
                            text = subjectName,
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.SemiBold
                        )
                        Text(
                            text = "Schedule ID: $scheduleId",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Progress Steps
            StepIndicator(currentStep = currentStep)
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Step Content
            Box(
                modifier = Modifier.weight(1f),
                contentAlignment = Alignment.Center
            ) {
                when (currentStep) {
                    0 -> LocationStep(
                        hasPermission = locationPermissions.allPermissionsGranted,
                        onRequestPermission = { locationPermissions.launchMultiplePermissionRequest() },
                        locationError = locationError,
                        distanceToTeacher = distanceToTeacher,
                        maxDistance = 50.0,
                        onVerifyLocation = {
                            scope.launch {
                                try {
                                    val fusedLocationClient = LocationServices.getFusedLocationProviderClient(context)
                                    val location = fusedLocationClient.lastLocation.await()
                                    if (location != null) {
                                        userLatitude = location.latitude
                                        userLongitude = location.longitude
                                        
                                        // Calculate distance to teacher
                                        val distance = calculateDistance(
                                            userLatitude, userLongitude,
                                            teacherLat, teacherLon
                                        )
                                        distanceToTeacher = distance
                                        
                                        // Check if within 50m radius
                                        if (distance <= 50.0) {
                                            locationError = null
                                            currentStep = 1 // Proceed to face verification
                                        } else {
                                            locationError = "You are ${distance.toInt()}m away from the classroom. Please move within 50m radius."
                                        }
                                    } else {
                                        locationError = "Could not get location. Please try again."
                                    }
                                } catch (e: Exception) {
                                    locationError = "Location error: ${e.message}"
                                }
                            }
                        }
                    )
                    
                    1 -> FaceVerificationStep(
                        hasPermission = cameraPermissionState.status.isGranted,
                        onRequestPermission = { cameraPermissionState.launchPermissionRequest() },
                        storedEmbedding = storedFaceEmbedding,
                        threshold = faceThreshold,
                        isVerifying = isVerifyingFace,
                        error = faceVerificationError,
                        isMarkingAttendance = uiState is StudentUiState.Loading,
                        attendanceError = (uiState as? StudentUiState.Error)?.message,
                        onFaceVerified = { confidence, embedding ->
                            faceConfidence = confidence
                            capturedFaceEmbedding = embedding
                            faceVerified = true
                            faceVerificationError = null
                            isVerifyingFace = false
                            
                            // Automatically mark attendance without confirmation
                            viewModel.markAttendance(
                                scheduleId = scheduleId,
                                latitude = userLatitude,
                                longitude = userLongitude,
                                faceConfidence = confidence,
                                faceEmbedding = embedding.joinToString(",") { String.format("%.6f", it) }
                            )
                            // Stay on current step to show loading, will move to success on completion
                        },
                        onVerificationStarted = {
                            isVerifyingFace = true
                            faceVerificationError = null
                        },
                        onVerificationFailed = { error ->
                            isVerifyingFace = false
                            faceVerificationError = error
                        }
                    )
                    
                    2 -> ConfirmationStep(
                        isLoading = uiState is StudentUiState.Loading,
                        error = (uiState as? StudentUiState.Error)?.message,
                        onConfirm = {
                            viewModel.markAttendance(
                                scheduleId = scheduleId,
                                latitude = userLatitude,
                                longitude = userLongitude,
                                faceConfidence = faceConfidence, // Already 0-100 from compareFaces
                                faceEmbedding = capturedFaceEmbedding?.let { embedding ->
                                    embedding.joinToString(",") { String.format("%.6f", it) }
                                }
                            )
                        }
                    )
                    
                    3 -> SuccessStep(
                        response = (uiState as? StudentUiState.Success)?.data as? MarkAttendanceResponse,
                        onDone = onSuccess
                    )
                }
            }
        }
    }
}

@Composable
private fun StepIndicator(currentStep: Int) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceEvenly
    ) {
        StepDot(
            stepNumber = 1,
            label = "Location",
            isActive = currentStep >= 0,
            isCompleted = currentStep > 0
        )
        StepLine(isCompleted = currentStep > 0)
        StepDot(
            stepNumber = 2,
            label = "Face",
            isActive = currentStep >= 1,
            isCompleted = currentStep > 1
        )
        StepLine(isCompleted = currentStep > 1)
        StepDot(
            stepNumber = 3,
            label = "Confirm",
            isActive = currentStep >= 2,
            isCompleted = currentStep > 2
        )
    }
}

@Composable
private fun StepDot(
    stepNumber: Int,
    label: String,
    isActive: Boolean,
    isCompleted: Boolean
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Box(
            modifier = Modifier
                .size(40.dp)
                .clip(CircleShape)
                .background(
                    when {
                        isCompleted -> SuccessGreen
                        isActive -> PrimaryBlue
                        else -> MaterialTheme.colorScheme.surfaceVariant
                    }
                ),
            contentAlignment = Alignment.Center
        ) {
            if (isCompleted) {
                Icon(
                    imageVector = Icons.Default.Check,
                    contentDescription = null,
                    tint = Color.White,
                    modifier = Modifier.size(20.dp)
                )
            } else {
                Text(
                    text = stepNumber.toString(),
                    style = MaterialTheme.typography.labelLarge,
                    fontWeight = FontWeight.Bold,
                    color = if (isActive) Color.White else MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
        Spacer(modifier = Modifier.height(4.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            color = if (isActive) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

@Composable
private fun RowScope.StepLine(isCompleted: Boolean) {
    Box(
        modifier = Modifier
            .weight(1f)
            .height(3.dp)
            .padding(horizontal = 8.dp)
            .align(Alignment.Top)
            .offset(y = 18.dp)
            .clip(RoundedCornerShape(1.5.dp))
            .background(
                if (isCompleted) SuccessGreen else MaterialTheme.colorScheme.surfaceVariant
            )
    )
}

@Composable
private fun LocationStep(
    hasPermission: Boolean,
    onRequestPermission: () -> Unit,
    locationError: String?,
    distanceToTeacher: Double,
    maxDistance: Double,
    onVerifyLocation: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.padding(32.dp)
    ) {
        Box(
            modifier = Modifier
                .size(120.dp)
                .clip(CircleShape)
                .background(PrimaryBlueContainer),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Outlined.LocationOn,
                contentDescription = null,
                modifier = Modifier.size(56.dp),
                tint = PrimaryBlue
            )
        }
        
        Spacer(modifier = Modifier.height(24.dp))
        
        Text(
            text = "Verify Your Location",
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Text(
            text = "You must be within ${maxDistance.toInt()}m of the classroom to mark attendance.",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center
        )
        
        // Show distance if already calculated
        if (distanceToTeacher > 0) {
            Spacer(modifier = Modifier.height(16.dp))
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = if (distanceToTeacher <= maxDistance) SuccessGreenContainer else WarningOrangeContainer
                )
            ) {
                Row(
                    modifier = Modifier.padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = if (distanceToTeacher <= maxDistance) Icons.Default.CheckCircle else Icons.Default.Warning,
                        contentDescription = null,
                        tint = if (distanceToTeacher <= maxDistance) SuccessGreen else WarningOrange,
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = "Distance: ${distanceToTeacher.toInt()}m",
                        color = if (distanceToTeacher <= maxDistance) SuccessGreen else WarningOrange,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.SemiBold
                    )
                }
            }
        }
        
        locationError?.let {
            Spacer(modifier = Modifier.height(16.dp))
            Card(
                colors = CardDefaults.cardColors(containerColor = ErrorRedContainer)
            ) {
                Row(
                    modifier = Modifier.padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.Error,
                        contentDescription = null,
                        tint = ErrorRed,
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = it,
                        color = ErrorRed,
                        style = MaterialTheme.typography.bodySmall
                    )
                }
            }
        }
        
        Spacer(modifier = Modifier.height(32.dp))
        
        if (!hasPermission) {
            Button(
                onClick = onRequestPermission,
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp)
            ) {
                Icon(Icons.Default.LocationOn, contentDescription = null)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Grant Location Permission")
            }
        } else {
            Button(
                onClick = onVerifyLocation,
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(containerColor = PrimaryBlue)
            ) {
                Icon(Icons.Default.MyLocation, contentDescription = null)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Verify Location", fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

@Composable
private fun FaceVerificationStep(
    hasPermission: Boolean,
    onRequestPermission: () -> Unit,
    storedEmbedding: String?,
    threshold: Int = 75,
    isVerifying: Boolean,
    error: String?,
    isMarkingAttendance: Boolean = false,
    attendanceError: String? = null,
    onFaceVerified: (confidence: Double, embedding: FloatArray) -> Unit,
    onVerificationStarted: () -> Unit,
    onVerificationFailed: (String) -> Unit
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()
    
    var faceDetected by remember { mutableStateOf(false) }
    var detectionStatus by remember { mutableStateOf("Position your face in the frame and blink or move slightly") }
    var currentConfidence by remember { mutableStateOf(0.0) }
    var capturedEmbedding by remember { mutableStateOf<FloatArray?>(null) }
    var showCamera by remember { mutableStateOf(false) }
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
        modifier = Modifier.padding(16.dp)
    ) {
        if (!hasPermission) {
            // Permission request view
            Box(
                modifier = Modifier
                    .size(120.dp)
                    .clip(CircleShape)
                    .background(SecondaryTealContainer),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Outlined.Face,
                    contentDescription = null,
                    modifier = Modifier.size(56.dp),
                    tint = SecondaryTeal
                )
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Text(
                text = "Camera Permission Required",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = "We need camera access to verify your face for attendance.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(32.dp))
            
            Button(
                onClick = onRequestPermission,
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp)
            ) {
                Icon(Icons.Default.CameraAlt, contentDescription = null)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Grant Camera Permission")
            }
        } else if (storedEmbedding == null) {
            // No face registered
            Box(
                modifier = Modifier
                    .size(120.dp)
                    .clip(CircleShape)
                    .background(ErrorRedContainer),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Outlined.Face,
                    contentDescription = null,
                    modifier = Modifier.size(56.dp),
                    tint = ErrorRed
                )
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Text(
                text = "Face Not Registered",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = "Please register your face first before marking attendance.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center
            )
        } else if (!showCamera) {
            // Show start verification button
            Box(
                modifier = Modifier
                    .size(120.dp)
                    .clip(CircleShape)
                    .background(SecondaryTealContainer),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Outlined.Face,
                    contentDescription = null,
                    modifier = Modifier.size(56.dp),
                    tint = SecondaryTeal
                )
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Text(
                text = "Verify Your Face",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = "Look at the camera to verify your identity.\nMinimum ${threshold}% confidence required.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center
            )
            
            if (error != null) {
                Spacer(modifier = Modifier.height(16.dp))
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = ErrorRedContainer)
                ) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.Error, contentDescription = null, tint = ErrorRed)
                        Spacer(modifier = Modifier.width(12.dp))
                        Text(text = error, color = ErrorRed, style = MaterialTheme.typography.bodyMedium)
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(32.dp))
            
            Button(
                onClick = { showCamera = true },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(containerColor = SecondaryTeal)
            ) {
                Icon(Icons.Default.Face, contentDescription = null)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Start Face Verification", fontWeight = FontWeight.SemiBold)
            }
        } else {
            // Camera preview with face detection
            Text(
                text = "Face Verification",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Camera preview
            Box(
                modifier = Modifier
                    .size(280.dp)
                    .clip(CircleShape)
                    .border(
                        width = 4.dp,
                        color = when {
                            isVerifying -> Color.Yellow
                            faceDetected && currentConfidence >= threshold -> SuccessGreen
                            faceDetected -> SecondaryTeal
                            else -> Color.Gray
                        },
                        shape = CircleShape
                    ),
                contentAlignment = Alignment.Center
            ) {
                FaceVerificationCamera(
                    faceDetectionHelper = faceDetectionHelper,
                    storedEmbedding = storedEmbedding!!,
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
                        if (confidence >= threshold) {
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
                                detectionStatus = "Face detected - ${String.format("%.1f", confidence)}% match (need ${threshold}%+ for ${3 - stableConfidenceCount} more checks)"
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
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Status text
            Text(
                text = detectionStatus,
                style = MaterialTheme.typography.bodyMedium,
                color = when {
                    faceDetected && currentConfidence >= threshold -> SuccessGreen
                    faceDetected -> SecondaryTeal
                    else -> MaterialTheme.colorScheme.onSurfaceVariant
                },
                textAlign = TextAlign.Center
            )
            
            // Verification progress indicator
            if (faceDetected && verificationStartTime > 0L && !hasAutoProceeded) {
                val elapsedSeconds = (System.currentTimeMillis() - verificationStartTime) / 1000.0
                val progress = (elapsedSeconds / 3.0).coerceIn(0.0, 1.0) // 3 seconds for full verification
                
                Spacer(modifier = Modifier.height(12.dp))
                
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        text = "Verifying...",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    
                    Spacer(modifier = Modifier.height(8.dp))
                    
                    LinearProgressIndicator(
                        progress = { progress.toFloat() },
                        modifier = Modifier
                            .width(200.dp)
                            .height(4.dp)
                            .clip(RoundedCornerShape(2.dp)),
                        color = if (stableConfidenceCount >= 3) SuccessGreen else PrimaryBlue,
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
                Spacer(modifier = Modifier.height(12.dp))
                
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
                            color = if (currentConfidence >= threshold) SuccessGreen else SecondaryTeal
                        )
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                    LinearProgressIndicator(
                        progress = { (currentConfidence / 100).toFloat().coerceIn(0f, 1f) },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(8.dp)
                            .clip(RoundedCornerShape(4.dp)),
                        color = if (currentConfidence >= threshold) SuccessGreen else SecondaryTeal,
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = "Minimum required: ${threshold}%",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            
            // Show attendance error if any
            attendanceError?.let {
                Spacer(modifier = Modifier.height(16.dp))
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = ErrorRedContainer)
                ) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.Error, contentDescription = null, tint = ErrorRed)
                        Spacer(modifier = Modifier.width(12.dp))
                        Text(text = it, color = ErrorRed, style = MaterialTheme.typography.bodyMedium)
                    }
                }
            }
            
            if (error != null) {
                Spacer(modifier = Modifier.height(16.dp))
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = ErrorRedContainer)
                ) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.Error, contentDescription = null, tint = ErrorRed)
                        Spacer(modifier = Modifier.width(12.dp))
                        Text(text = error, color = ErrorRed, style = MaterialTheme.typography.bodyMedium)
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
                    modifier = Modifier.padding(32.dp),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Column(
                        modifier = Modifier.padding(32.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(48.dp),
                            color = PrimaryBlue
                        )
                        
                        Spacer(modifier = Modifier.height(16.dp))
                        
                        Text(
                            text = "Marking Attendance...",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )
                        
                        Spacer(modifier = Modifier.height(8.dp))
                        
                        Text(
                            text = "Please wait while we process your attendance",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            textAlign = TextAlign.Center
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
            .setTargetResolution(android.util.Size(480, 640))  // Lower resolution for faster processing
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
                                    val confidence = faceDetectionHelper.compareFaces(
                                        currentEmbedding,
                                        storedEmbeddingArray
                                    )
                                    // Pass both confidence and the captured embedding
                                    onFaceDetected(confidence, currentEmbedding.copyOf())
                                    bitmap.recycle()
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
        modifier = modifier.clip(CircleShape)
    )
}

@Composable
private fun ConfirmationStep(
    isLoading: Boolean,
    error: String?,
    onConfirm: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.padding(32.dp)
    ) {
        Box(
            modifier = Modifier
                .size(120.dp)
                .clip(CircleShape)
                .background(SuccessGreenContainer),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Outlined.CheckCircle,
                contentDescription = null,
                modifier = Modifier.size(56.dp),
                tint = SuccessGreen
            )
        }
        
        Spacer(modifier = Modifier.height(24.dp))
        
        Text(
            text = "Ready to Mark",
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Text(
            text = "Location and face verification completed. Confirm to mark your attendance.",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center
        )
        
        error?.let {
            Spacer(modifier = Modifier.height(16.dp))
            Card(
                colors = CardDefaults.cardColors(containerColor = ErrorRedContainer)
            ) {
                Row(
                    modifier = Modifier.padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.Error,
                        contentDescription = null,
                        tint = ErrorRed,
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = it,
                        color = ErrorRed,
                        style = MaterialTheme.typography.bodySmall
                    )
                }
            }
        }
        
        Spacer(modifier = Modifier.height(32.dp))
        
        Button(
            onClick = onConfirm,
            modifier = Modifier.fillMaxWidth(),
            enabled = !isLoading,
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = SuccessGreen)
        ) {
            if (isLoading) {
                CircularProgressIndicator(
                    modifier = Modifier.size(24.dp),
                    color = Color.White,
                    strokeWidth = 2.dp
                )
            } else {
                Icon(Icons.Default.Check, contentDescription = null)
                Spacer(modifier = Modifier.width(8.dp))
                Text("Confirm Attendance", fontWeight = FontWeight.SemiBold)
            }
        }
    }
}

@Composable
private fun SuccessStep(
    response: MarkAttendanceResponse?,
    onDone: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.padding(32.dp)
    ) {
        Box(
            modifier = Modifier
                .size(120.dp)
                .clip(CircleShape)
                .background(SuccessGreenContainer),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Default.CheckCircle,
                contentDescription = null,
                modifier = Modifier.size(64.dp),
                tint = SuccessGreen
            )
        }
        
        Spacer(modifier = Modifier.height(24.dp))
        
        Text(
            text = "Attendance Marked!",
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
            color = SuccessGreen
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Text(
            text = response?.message ?: "Your attendance has been recorded successfully.",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center
        )
        
        response?.faceConfidence?.let {
            Spacer(modifier = Modifier.height(16.dp))
            Row(
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                InfoChip(
                    icon = Icons.Default.Face,
                    label = "Face: ${(it * 100).toInt()}%"
                )
                response.distanceMeters?.let { dist ->
                    InfoChip(
                        icon = Icons.Default.LocationOn,
                        label = "${dist.toInt()}m away"
                    )
                }
            }
        }
        
        Spacer(modifier = Modifier.height(32.dp))
        
        Button(
            onClick = onDone,
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.buttonColors(containerColor = PrimaryBlue)
        ) {
            Text("Done", fontWeight = FontWeight.SemiBold)
        }
    }
}

@Composable
private fun InfoChip(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String
) {
    Surface(
        shape = RoundedCornerShape(20.dp),
        color = MaterialTheme.colorScheme.surfaceVariant
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                modifier = Modifier.size(16.dp),
                tint = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Spacer(modifier = Modifier.width(6.dp))
            Text(
                text = label,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}
