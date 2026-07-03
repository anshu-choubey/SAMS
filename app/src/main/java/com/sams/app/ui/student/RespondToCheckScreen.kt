package com.sams.app.ui.student

import android.Manifest
import android.widget.Toast
import androidx.camera.core.*
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.background
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
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size as ComposeSize
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.accompanist.permissions.ExperimentalPermissionsApi
import com.google.accompanist.permissions.rememberMultiplePermissionsState
import com.sams.app.utils.FaceDetectionHelper
import com.sams.app.utils.LocationHelper
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.util.concurrent.Executors

@OptIn(ExperimentalMaterial3Api::class, ExperimentalPermissionsApi::class)
@Composable
fun RespondToCheckScreen(
    checkPointId: Int,
    teacherLat: Double,
    teacherLon: Double,
    subjectName: String,
    viewModel: StudentViewModel = hiltViewModel(),
    onBack: () -> Unit,
    onSuccess: () -> Unit
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    
    val checkResponseState by viewModel.checkResponseState.collectAsState()
    val storedFaceEmbedding by viewModel.storedFaceEmbedding.collectAsState()
    val storedFaceEmbeddingLoaded by viewModel.storedFaceEmbeddingLoaded.collectAsState()
    
    // Load settings from ViewModel
    val faceConfidenceThreshold = viewModel.getFaceConfidenceThreshold()
    val gpsProximityRadius = viewModel.getGpsRadius()
    val enableLiveness = viewModel.getEnableLivenessDetection()
    
    var currentStep by remember { mutableStateOf(1) }
    var statusMessage by remember { mutableStateOf("Preparing...") }
    var isProcessing by remember { mutableStateOf(false) }
    var errorMessage by remember { mutableStateOf<String?>(null) }
    
    var studentLat by remember { mutableStateOf(0.0) }
    var studentLon by remember { mutableStateOf(0.0) }
    var gpsAccuracy by remember { mutableStateOf(0f) }
    var locationReady by remember { mutableStateOf(false) }
    
    var faceConfidence by remember { mutableStateOf(0.0) }
    var faceVerified by remember { mutableStateOf(false) }
    
    val locationHelper = remember { LocationHelper(context) }
    val faceDetectionHelper = remember { FaceDetectionHelper(context) }
    
    val permissionsState = rememberMultiplePermissionsState(
        permissions = listOf(
            Manifest.permission.ACCESS_FINE_LOCATION,
            Manifest.permission.ACCESS_COARSE_LOCATION,
            Manifest.permission.CAMERA
        )
    )
    
    LaunchedEffect(Unit) {
        viewModel.resetCheckResponseState()
        viewModel.loadStoredFaceEmbedding()
        if (!permissionsState.allPermissionsGranted) {
            permissionsState.launchMultiplePermissionRequest()
        }
    }
    
    LaunchedEffect(permissionsState.allPermissionsGranted, storedFaceEmbeddingLoaded) {
        if (permissionsState.allPermissionsGranted && storedFaceEmbeddingLoaded) {
            statusMessage = "Getting your location..."
            currentStep = 1
            
            when (val result = locationHelper.getValidatedLocation()) {
                is LocationHelper.LocationResult.Success -> {
                    studentLat = result.location.latitude
                    studentLon = result.location.longitude
                    gpsAccuracy = result.location.accuracy
                    locationReady = true
                    currentStep = 2
                    statusMessage = "Location verified. Now verify your face."
                }
                is LocationHelper.LocationResult.Error -> {
                    errorMessage = result.message
                    statusMessage = "Location error: ${result.message}"
                }
            }
        }
    }
    
    LaunchedEffect(checkResponseState) {
        when (checkResponseState) {
            is StudentUiState.Success -> {
                Toast.makeText(context, "Attendance check responded successfully!", Toast.LENGTH_SHORT).show()
                onSuccess()
            }
            is StudentUiState.Error -> {
                val error = (checkResponseState as StudentUiState.Error).message
                if (error.contains("already responded", ignoreCase = true)) {
                    Toast.makeText(context, "Check already completed!", Toast.LENGTH_SHORT).show()
                    onSuccess()
                } else {
                    errorMessage = error
                    statusMessage = "Error: $error"
                    isProcessing = false
                }
            }
            else -> {}
        }
    }
    
    fun submitResponse() {
        if (!locationReady || !faceVerified) {
            errorMessage = "Please complete location and face verification first"
            return
        }
        if (isProcessing) return
        
        isProcessing = true
        statusMessage = "Submitting attendance response..."
        currentStep = 3
        
        viewModel.respondAttendanceCheck(
            checkPointId = checkPointId,
            latitude = studentLat,
            longitude = studentLon,
            faceConfidence = faceConfidence,
            gpsAccuracy = gpsAccuracy
        )
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Respond to Check") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back")
                    }
                }
            )
        }
    ) { padding ->
        if (!permissionsState.allPermissionsGranted) {
            PermissionRequestContent(
                modifier = Modifier.padding(padding),
                onRequestPermissions = { permissionsState.launchMultiplePermissionRequest() }
            )
        } else if (!storedFaceEmbeddingLoaded) {
            LoadingContent(modifier = Modifier.padding(padding), message = "Loading face data...")
        } else if (storedFaceEmbedding == null) {
            NoFaceRegisteredContent(modifier = Modifier.padding(padding), onBack = onBack)
        } else {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = subjectName,
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold
                )
                
                Spacer(modifier = Modifier.height(8.dp))
                
                StepIndicator(currentStep = currentStep)
                
                Spacer(modifier = Modifier.height(16.dp))
                
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = when {
                            errorMessage != null -> MaterialTheme.colorScheme.errorContainer
                            currentStep == 3 && checkResponseState is StudentUiState.Success -> MaterialTheme.colorScheme.primaryContainer
                            else -> MaterialTheme.colorScheme.surfaceVariant
                        }
                    )
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Icon(
                            imageVector = when {
                                errorMessage != null -> Icons.Default.Warning
                                currentStep == 1 -> Icons.Default.LocationOn
                                currentStep == 2 -> Icons.Default.Face
                                else -> Icons.Default.CheckCircle
                            },
                            contentDescription = null,
                            modifier = Modifier.size(48.dp),
                            tint = when {
                                errorMessage != null -> MaterialTheme.colorScheme.error
                                currentStep == 3 && checkResponseState is StudentUiState.Success -> MaterialTheme.colorScheme.primary
                                else -> MaterialTheme.colorScheme.onSurfaceVariant
                            }
                        )
                        
                        Spacer(modifier = Modifier.height(8.dp))
                        
                        Text(
                            text = statusMessage,
                            style = MaterialTheme.typography.bodyMedium,
                            textAlign = TextAlign.Center
                        )
                        
                        errorMessage?.let { error ->
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                text = error,
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.error,
                                textAlign = TextAlign.Center
                            )
                        }
                    }
                }
                
                Spacer(modifier = Modifier.height(16.dp))
                
                if (currentStep == 2 && locationReady && !faceVerified) {
                    FaceVerificationCamera(
                        storedEmbedding = storedFaceEmbedding!!,
                        faceDetectionHelper = faceDetectionHelper,
                        faceConfidenceThreshold = faceConfidenceThreshold,
                        enableLiveness = enableLiveness,
                        onVerificationSuccess = { confidence ->
                            faceConfidence = confidence
                            faceVerified = true
                            statusMessage = "Face verified! Submitting response..."
                            submitResponse()
                        },
                        onVerificationFailed = { error ->
                            errorMessage = error
                            statusMessage = "Face verification failed"
                        }
                    )
                }
                
                Spacer(modifier = Modifier.weight(1f))
                
                if (errorMessage != null) {
                    Button(
                        onClick = {
                            errorMessage = null
                            statusMessage = "Retrying..."
                            currentStep = 1
                            locationReady = false
                            faceVerified = false
                            
                            scope.launch {
                                when (val result = locationHelper.getValidatedLocation()) {
                                    is LocationHelper.LocationResult.Success -> {
                                        studentLat = result.location.latitude
                                        studentLon = result.location.longitude
                                        gpsAccuracy = result.location.accuracy
                                        locationReady = true
                                        currentStep = 2
                                        statusMessage = "Location verified. Now verify your face."
                                    }
                                    is LocationHelper.LocationResult.Error -> {
                                        errorMessage = result.message
                                        statusMessage = "Location error: ${result.message}"
                                    }
                                }
                            }
                        },
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Icon(Icons.Default.Refresh, contentDescription = null)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Retry")
                    }
                }
                
                if (isProcessing) {
                    CircularProgressIndicator(modifier = Modifier.padding(16.dp))
                }
            }
        }
    }
}

@Composable
private fun StepIndicator(currentStep: Int) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically
    ) {
        StepCircle(stepNumber = 1, isActive = currentStep >= 1, isComplete = currentStep > 1, label = "Location")
        StepConnector(isActive = currentStep > 1)
        StepCircle(stepNumber = 2, isActive = currentStep >= 2, isComplete = currentStep > 2, label = "Face")
        StepConnector(isActive = currentStep > 2)
        StepCircle(stepNumber = 3, isActive = currentStep >= 3, isComplete = false, label = "Submit")
    }
}

@Composable
private fun StepCircle(stepNumber: Int, isActive: Boolean, isComplete: Boolean, label: String) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Box(
            modifier = Modifier
                .size(36.dp)
                .clip(CircleShape)
                .background(
                    when {
                        isComplete -> MaterialTheme.colorScheme.primary
                        isActive -> MaterialTheme.colorScheme.primaryContainer
                        else -> MaterialTheme.colorScheme.surfaceVariant
                    }
                ),
            contentAlignment = Alignment.Center
        ) {
            if (isComplete) {
                Icon(
                    Icons.Default.Check,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onPrimary,
                    modifier = Modifier.size(20.dp)
                )
            } else {
                Text(
                    text = "$stepNumber",
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.Bold,
                    color = if (isActive) MaterialTheme.colorScheme.onPrimaryContainer else MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
        Spacer(modifier = Modifier.height(4.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            color = if (isActive) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

@Composable
private fun StepConnector(isActive: Boolean) {
    Box(
        modifier = Modifier
            .width(40.dp)
            .height(2.dp)
            .background(
                if (isActive) MaterialTheme.colorScheme.primary
                else MaterialTheme.colorScheme.surfaceVariant
            )
    )
}

@Composable
private fun PermissionRequestContent(modifier: Modifier, onRequestPermissions: () -> Unit) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Icon(
            Icons.Default.Warning,
            contentDescription = null,
            modifier = Modifier.size(64.dp),
            tint = MaterialTheme.colorScheme.error
        )
        Spacer(modifier = Modifier.height(16.dp))
        Text(
            "Camera and Location Required",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold
        )
        Spacer(modifier = Modifier.height(8.dp))
        Text(
            "Please grant camera and location permissions to respond to attendance checks.",
            style = MaterialTheme.typography.bodyMedium,
            textAlign = TextAlign.Center
        )
        Spacer(modifier = Modifier.height(16.dp))
        Button(onClick = onRequestPermissions) {
            Text("Grant Permissions")
        }
    }
}

@Composable
private fun LoadingContent(modifier: Modifier, message: String) {
    Box(
        modifier = modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            CircularProgressIndicator()
            Spacer(modifier = Modifier.height(16.dp))
            Text(message)
        }
    }
}

@Composable
private fun NoFaceRegisteredContent(modifier: Modifier, onBack: () -> Unit) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Icon(
            Icons.Default.Face,
            contentDescription = null,
            modifier = Modifier.size(64.dp),
            tint = MaterialTheme.colorScheme.error
        )
        Spacer(modifier = Modifier.height(16.dp))
        Text(
            "Face Not Registered",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold
        )
        Spacer(modifier = Modifier.height(8.dp))
        Text(
            "Please register your face first before responding to attendance checks.",
            style = MaterialTheme.typography.bodyMedium,
            textAlign = TextAlign.Center
        )
        Spacer(modifier = Modifier.height(16.dp))
        Button(onClick = onBack) {
            Text("Go Back")
        }
    }
}

@Composable
private fun FaceVerificationCamera(
    storedEmbedding: String,
    faceDetectionHelper: FaceDetectionHelper,
    faceConfidenceThreshold: Int = 75,
    enableLiveness: Boolean = true,
    onVerificationSuccess: (Double) -> Unit,
    onVerificationFailed: (String) -> Unit
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val previewView = remember { PreviewView(context) }
    val scope = rememberCoroutineScope()
    val frameCounter = remember { java.util.concurrent.atomic.AtomicInteger(0) }

    var verificationStatus by remember { mutableStateOf("Position your face in the frame") }
    var verificationProgress by remember { mutableStateOf(0f) }
    var challengeText by remember { mutableStateOf("") }
    var isComplete by remember { mutableStateOf(false) }
    val isCompleteAtomic = remember { java.util.concurrent.atomic.AtomicBoolean(false) }
    var timedOut by remember { mutableStateOf(false) }

    val storedEmbeddingArray = remember(storedEmbedding) {
        faceDetectionHelper.stringToEmbedding(storedEmbedding)
    }

    DisposableEffect(Unit) {
        faceDetectionHelper.resetVerification()
        onDispose { }
    }

    // 45-second timeout for face verification
    LaunchedEffect(Unit) {
        delay(45_000L)
        if (!isComplete) {
            timedOut = true
            verificationStatus = "Verification timed out"
            onVerificationFailed("Face verification timed out. Please retry.")
        }
    }

    LaunchedEffect(Unit) {
        val cameraProvider = ProcessCameraProvider.getInstance(context).get()
        val preview = Preview.Builder().build().also {
            it.setSurfaceProvider(previewView.surfaceProvider)
        }
        val imageAnalysis = ImageAnalysis.Builder()
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .setTargetResolution(android.util.Size(480, 640))
            .build().also { analysis ->
                analysis.setAnalyzer(Executors.newSingleThreadExecutor()) { imageProxy ->
                    if (isCompleteAtomic.get() || timedOut || frameCounter.incrementAndGet() % 2 != 0) {
                        imageProxy.close()
                        return@setAnalyzer
                    }

                    val bitmap = try {
                        faceDetectionHelper.imageProxyToBitmap(imageProxy)
                    } catch (_: Exception) { null }
                    imageProxy.close()
                    if (bitmap == null) return@setAnalyzer

                    scope.launch {
                        try {
                            val result = faceDetectionHelper.verifyFaceWithLiveness(
                                bitmap = bitmap,
                                storedEmbedding = storedEmbeddingArray,
                                confidenceThreshold = faceConfidenceThreshold.toDouble(),
                                enableLiveness = enableLiveness
                            )

                            verificationStatus = result.message
                            challengeText = if (!faceDetectionHelper.isLivenessPassed() && enableLiveness)
                                faceDetectionHelper.getLivenessInstruction() else ""
                            verificationProgress = when {
                                result.success -> 1f
                                faceDetectionHelper.isLivenessPassed() ->
                                    0.5f + (result.framesVerified.toFloat() / result.requiredFrames) * 0.5f
                                else -> faceDetectionHelper.getLivenessProgress() * 0.5f
                            }

                            if (result.success && isCompleteAtomic.compareAndSet(false, true)) {
                                isComplete = true
                                verificationStatus = "Face verified!"
                                onVerificationSuccess(result.faceMatch)
                            }
                        } catch (e: Exception) {
                            verificationStatus = "Error: ${e.message}"
                        } finally {
                            bitmap.recycle()
                        }
                    }
                }
            }

        try {
            cameraProvider.unbindAll()
            cameraProvider.bindToLifecycle(
                lifecycleOwner,
                CameraSelector.DEFAULT_FRONT_CAMERA,
                preview,
                imageAnalysis
            )
        } catch (e: Exception) {
            onVerificationFailed("Camera error: ${e.message}")
        }
    }

    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .height(300.dp)
        ) {
            Box(modifier = Modifier.fillMaxSize()) {
                AndroidView(
                    factory = { previewView },
                    modifier = Modifier.fillMaxSize()
                )

                FaceFrameOverlay(
                    modifier = Modifier.fillMaxSize(),
                    frameColor = when {
                        verificationProgress >= 1f -> Color(0xFF4CAF50)
                        verificationProgress > 0f -> Color(0xFFFFA000)
                        else -> Color.White
                    }
                )

                if (challengeText.isNotEmpty()) {
                    Box(
                        modifier = Modifier
                            .align(Alignment.TopCenter)
                            .padding(8.dp)
                            .background(
                                color = Color(0xCC1565C0),
                                shape = RoundedCornerShape(8.dp)
                            )
                            .padding(horizontal = 14.dp, vertical = 8.dp)
                    ) {
                        Text(
                            text = challengeText,
                            color = Color.White,
                            style = MaterialTheme.typography.labelLarge,
                            fontWeight = FontWeight.Bold,
                            textAlign = TextAlign.Center
                        )
                    }
                }

                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(16.dp),
                    contentAlignment = Alignment.BottomCenter
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        LinearProgressIndicator(
                            progress = { verificationProgress },
                            modifier = Modifier.fillMaxWidth(),
                            color = if (verificationProgress >= 1f) Color(0xFF4CAF50)
                                else MaterialTheme.colorScheme.primary
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Surface(
                            color = Color.Black.copy(alpha = 0.6f),
                            shape = MaterialTheme.shapes.small
                        ) {
                            Text(
                                text = verificationStatus,
                                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
                                color = Color.White,
                                style = MaterialTheme.typography.bodyMedium
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun FaceFrameOverlay(
    modifier: Modifier = Modifier,
    frameColor: Color = Color.White
) {
    androidx.compose.foundation.Canvas(modifier = modifier) {
        val side = minOf(size.width, size.height) * 0.65f
        val left = (size.width - side) / 2f
        val top = (size.height - side) / 2f
        val cornerLen = side * 0.15f
        val strokeW = 3.dp.toPx()

        val corners = listOf(
            Offset(left, top) to listOf(
                Offset(left + cornerLen, top),
                Offset(left, top + cornerLen)
            ),
            Offset(left + side, top) to listOf(
                Offset(left + side - cornerLen, top),
                Offset(left + side, top + cornerLen)
            ),
            Offset(left, top + side) to listOf(
                Offset(left + cornerLen, top + side),
                Offset(left, top + side - cornerLen)
            ),
            Offset(left + side, top + side) to listOf(
                Offset(left + side - cornerLen, top + side),
                Offset(left + side, top + side - cornerLen)
            )
        )

        corners.forEach { (corner, ends) ->
            ends.forEach { end ->
                drawLine(color = frameColor, start = corner, end = end, strokeWidth = strokeW)
            }
        }

        drawRect(
            color = frameColor.copy(alpha = 0.3f),
            topLeft = Offset(left, top),
            size = ComposeSize(side, side),
            style = Stroke(
                width = 1.dp.toPx(),
                pathEffect = PathEffect.dashPathEffect(floatArrayOf(10f, 10f))
            )
        )
    }
}
