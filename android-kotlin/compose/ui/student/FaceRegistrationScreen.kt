package com.sams.app.ui.student

import android.Manifest
import android.graphics.Bitmap
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
import com.google.accompanist.permissions.rememberPermissionState
import com.sams.app.data.models.FaceRegistrationResponse
import com.sams.app.ui.theme.PresentColor
import com.sams.app.utils.FaceDetectionHelper
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.util.concurrent.Executors

// Liveness detection data classes
data class RegistrationStep(
    val instruction: String,
    val action: String,
    val requiredAction: LivenessAction
)

enum class LivenessAction {
    BLINK,
    SMILE,
    HEAD_TURN_LEFT,
    HEAD_TURN_RIGHT,
    BLINK_UP
}

@OptIn(ExperimentalMaterial3Api::class, ExperimentalPermissionsApi::class)
@Composable
fun FaceRegistrationScreen(
    viewModel: StudentViewModel = hiltViewModel(),
    onBack: () -> Unit,
    onSuccess: () -> Unit
) {
    val uiState by viewModel.faceRegistrationState.collectAsState()
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val configuration = LocalConfiguration.current
    
    // Responsive sizing
    val isSmallScreen = configuration.screenWidthDp < 360
    val screenPadding = if (isSmallScreen) 4.dp else 16.dp
    val iconSize = if (isSmallScreen) 48.dp else 72.dp
    val largeIconSize = if (isSmallScreen) 64.dp else 96.dp
    val cameraSize = if (isSmallScreen) 200.dp else 280.dp
    val titleStyle = if (isSmallScreen) MaterialTheme.typography.titleMedium else MaterialTheme.typography.titleLarge
    val bodyStyle = if (isSmallScreen) MaterialTheme.typography.bodySmall else MaterialTheme.typography.bodyMedium
    
    val cameraPermissionState = rememberPermissionState(Manifest.permission.CAMERA)
    
    val faceDetectionHelper = remember { FaceDetectionHelper(context) }
    
    var capturedCount by remember { mutableStateOf(0) }
    val requiredCaptures = 5
    
    // Store embeddings from each capture
    val capturedEmbeddings = remember { mutableStateListOf<FloatArray>() }
    var captureError by remember { mutableStateOf<String?>(null) }
    var isProcessing by remember { mutableStateOf(false) }
    
    LaunchedEffect(uiState) {
        if (uiState is StudentUiState.Success) {
            kotlinx.coroutines.delay(2000)
            viewModel.resetFaceRegistrationState()
            onSuccess()
        }
    }
    
    DisposableEffect(Unit) {
        onDispose {
            viewModel.resetFaceRegistrationState()
            faceDetectionHelper.close()
        }
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Register Face") },
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
            when {
                !cameraPermissionState.status.isGranted -> {
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
                        Spacer(modifier = Modifier.height(if (isSmallScreen) 12.dp else 16.dp))
                        Text(
                            "Camera Permission Required",
                            style = titleStyle,
                            fontWeight = FontWeight.Bold
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            "We need camera access to capture your face for attendance verification.",
                            style = bodyStyle,
                            textAlign = TextAlign.Center
                        )
                        Spacer(modifier = Modifier.height(if (isSmallScreen) 16.dp else 24.dp))
                        Button(onClick = { cameraPermissionState.launchPermissionRequest() }) {
                            Text("Grant Permission")
                        }
                    }
                }
                uiState is StudentUiState.Success -> {
                    Column(
                        modifier = Modifier.fillMaxSize(),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Icon(
                            Icons.Default.CheckCircle,
                            contentDescription = null,
                            modifier = Modifier.size(largeIconSize),
                            tint = PresentColor
                        )
                        Spacer(modifier = Modifier.height(if (isSmallScreen) 16.dp else 24.dp))
                        Text(
                            "Face Registered!",
                            style = titleStyle,
                            fontWeight = FontWeight.Bold
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            "You can now mark your attendance using face verification.",
                            style = bodyStyle,
                            textAlign = TextAlign.Center
                        )
                    }
                }
                uiState is StudentUiState.Error -> {
                    Column(
                        modifier = Modifier.fillMaxSize(),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Icon(
                            Icons.Default.Error,
                            contentDescription = null,
                            modifier = Modifier.size(iconSize),
                            tint = MaterialTheme.colorScheme.error
                        )
                        Spacer(modifier = Modifier.height(if (isSmallScreen) 12.dp else 16.dp))
                        Text(
                            (uiState as StudentUiState.Error).message,
                            style = bodyStyle,
                            color = MaterialTheme.colorScheme.error
                        )
                        Spacer(modifier = Modifier.height(if (isSmallScreen) 16.dp else 24.dp))
                        Button(onClick = { 
                            viewModel.resetFaceRegistrationState()
                            capturedCount = 0
                        }) {
                            Text("Try Again")
                        }
                    }
                }
                else -> {
                    FaceCapture(
                        capturedCount = capturedCount,
                        requiredCaptures = requiredCaptures,
                        isLoading = uiState is StudentUiState.Loading || isProcessing,
                        captureError = captureError,
                        onCapture = { bitmap ->
                            if (bitmap != null) {
                                isProcessing = true
                                captureError = null
                                scope.launch {
                                    try {
                                        // Detect face in captured image
                                        val faces = withContext(Dispatchers.Default) {
                                            faceDetectionHelper.detectFaces(bitmap)
                                        }
                                        
                                        if (faces.isEmpty()) {
                                            captureError = "No face detected. Please try again."
                                            isProcessing = false
                                            return@launch
                                        }
                                        
                                        if (faces.size > 1) {
                                            captureError = "Multiple faces detected. Please ensure only your face is visible."
                                            isProcessing = false
                                            return@launch
                                        }
                                        
                                        // Extract embedding
                                        val embedding = withContext(Dispatchers.Default) {
                                            faceDetectionHelper.extractFaceEmbedding(faces.first(), bitmap)
                                        }
                                        
                                        capturedEmbeddings.add(embedding)
                                        capturedCount++
                                        
                                        // If all captures done, combine and register
                                        if (capturedCount >= requiredCaptures) {
                                            // Average the embeddings
                                            val averagedEmbedding = FloatArray(128)
                                            for (i in 0 until 128) {
                                                averagedEmbedding[i] = capturedEmbeddings.map { it[i] }.average().toFloat()
                                            }
                                            // Normalize
                                            val norm = kotlin.math.sqrt(averagedEmbedding.map { it * it }.sum())
                                            if (norm > 0) {
                                                for (i in averagedEmbedding.indices) {
                                                    averagedEmbedding[i] /= norm
                                                }
                                            }
                                            
                                            val embeddingString = faceDetectionHelper.embeddingToString(averagedEmbedding)
                                            viewModel.registerFace(embeddingString)
                                        }
                                    } catch (e: Exception) {
                                        captureError = e.message ?: "Failed to process face"
                                    } finally {
                                        isProcessing = false
                                    }
                                }
                            }
                        }
                    )
                }
            }
        }
    }
}

@Composable
private fun FaceCapture(
    capturedCount: Int,
    requiredCaptures: Int,
    isLoading: Boolean,
    captureError: String? = null,
    onCapture: (Bitmap?) -> Unit
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val cameraExecutor = remember { Executors.newSingleThreadExecutor() }
    val faceDetectionHelper = remember { FaceDetectionHelper(context) }
    val scope = rememberCoroutineScope()
    val configuration = LocalConfiguration.current
    
    // Responsive sizing
    val isSmallScreen = configuration.screenWidthDp < 360
    val cameraSize = if (isSmallScreen) 200.dp else 280.dp
    val bodyStyle = if (isSmallScreen) MaterialTheme.typography.bodySmall else MaterialTheme.typography.bodyMedium
    
    var imageCapture by remember { mutableStateOf<ImageCapture?>(null) }
    
    // Enhanced instructions with liveness actions
    val registrationSteps = listOf(
        RegistrationStep("Position your face in the frame", "Blink naturally when ready", LivenessAction.BLINK),
        RegistrationStep("Keep looking at the camera", "Smile for the camera", LivenessAction.SMILE),
        RegistrationStep("Turn your head slowly", "Turn left and back to center", LivenessAction.HEAD_TURN_LEFT),
        RegistrationStep("Turn your head slowly", "Turn right and back to center", LivenessAction.HEAD_TURN_RIGHT),
        RegistrationStep("Look up slightly", "Look up and blink", LivenessAction.BLINK_UP)
    )
    
    // Liveness detection states
    var currentStep by remember { mutableStateOf(0) }
    var livenessAction by remember { mutableStateOf<LivenessAction?>(null) }
    var isCheckingLiveness by remember { mutableStateOf(false) }
    var livenessProgress by remember { mutableStateOf(0f) }
    var livenessStatus by remember { mutableStateOf("Preparing...") }
    var canCapture by remember { mutableStateOf(false) }
    var faceDetected by remember { mutableStateOf(false) }
    
    val currentRegistrationStep = registrationSteps.getOrNull(capturedCount) ?: registrationSteps[0]
    
    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        // Progress
        LinearProgressIndicator(
            progress = { capturedCount.toFloat() / requiredCaptures },
            modifier = Modifier
                .fillMaxWidth()
                .height(8.dp)
                .clip(RoundedCornerShape(4.dp)),
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Text(
            "Capture ${capturedCount + 1} of $requiredCaptures",
            style = if (isSmallScreen) MaterialTheme.typography.titleSmall else MaterialTheme.typography.titleMedium
        )
        
        Spacer(modifier = Modifier.height(if (isSmallScreen) 8.dp else 16.dp))
        
        // Instructions with liveness action
        Card(
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer
            )
        ) {
            Column(
                modifier = Modifier.padding(if (isSmallScreen) 12.dp else 16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    text = currentRegistrationStep.instruction,
                    style = bodyStyle,
                    fontWeight = FontWeight.Medium,
                    textAlign = TextAlign.Center
                )
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = currentRegistrationStep.action,
                    style = if (isSmallScreen) MaterialTheme.typography.bodySmall else MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.primary,
                    fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.Center
                )
            }
        }
        
        Spacer(modifier = Modifier.height(if (isSmallScreen) 8.dp else 16.dp))
        
        // Liveness detection status
        if (isCheckingLiveness) {
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = PresentColor.copy(alpha = 0.1f)
                )
            ) {
                Column(
                    modifier = Modifier.padding(if (isSmallScreen) 8.dp else 12.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text(
                        text = livenessStatus,
                        style = bodyStyle,
                        color = PresentColor,
                        textAlign = TextAlign.Center
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    LinearProgressIndicator(
                        progress = livenessProgress,
                        modifier = Modifier
                            .width(if (isSmallScreen) 120.dp else 160.dp)
                            .height(3.dp),
                        color = PresentColor,
                        trackColor = Color.Gray.copy(alpha = 0.3f)
                    )
                }
            }
            Spacer(modifier = Modifier.height(if (isSmallScreen) 8.dp else 16.dp))
        }
        
        // Camera Preview with Face Detection
        Box(
            modifier = Modifier
                .size(cameraSize)
                .clip(CircleShape)
                .border(
                    width = 3.dp,
                    color = when {
                        canCapture -> PresentColor
                        faceDetected -> MaterialTheme.colorScheme.primary
                        else -> Color.Gray
                    },
                    shape = CircleShape
                ),
            contentAlignment = Alignment.Center
        ) {
            FaceDetectionCamera(
                faceDetectionHelper = faceDetectionHelper,
                onFaceDetected = { face, bitmap ->
                    faceDetected = true
                    if (!isCheckingLiveness && !canCapture) {
                        // Start liveness detection
                        isCheckingLiveness = true
                        livenessStatus = "Analyzing..."
                        livenessProgress = 0f
                        
                        scope.launch {
                            try {
                                val result = performLivenessCheck(
                                    faceDetectionHelper,
                                    currentRegistrationStep.requiredAction,
                                    bitmap
                                )
                                
                                when (result) {
                                    is LivenessCheckResult.Success -> {
                                        livenessStatus = "✓ ${result.message}"
                                        livenessProgress = 1f
                                        canCapture = true
                                        kotlinx.coroutines.delay(1000) // Show success briefly
                                        // Auto capture
                                        performCapture()
                                    }
                                    is LivenessCheckResult.Progress -> {
                                        livenessStatus = result.message
                                        livenessProgress = result.progress
                                    }
                                    is LivenessCheckResult.Failed -> {
                                        livenessStatus = "✗ ${result.message}"
                                        livenessProgress = 0f
                                        kotlinx.coroutines.delay(2000)
                                        isCheckingLiveness = false
                                    }
                                }
                            } catch (e: Exception) {
                                livenessStatus = "Error: ${e.message}"
                                isCheckingLiveness = false
                            }
                        }
                    }
                },
                onNoFaceDetected = {
                    faceDetected = false
                    isCheckingLiveness = false
                    canCapture = false
                    livenessProgress = 0f
                    livenessStatus = "Position your face in the frame"
                },
                modifier = Modifier.fillMaxSize()
            )
        }
            
            if (isLoading) {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(Color.Black.copy(alpha = 0.5f)),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        CircularProgressIndicator(color = Color.White)
                        Spacer(modifier = Modifier.height(16.dp))
                        Text(
                            "Registering...",
                            color = Color.White,
                            style = MaterialTheme.typography.titleMedium
                        )
                    }
                }
            }
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Error message
        if (captureError != null) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.errorContainer
                )
            ) {
                Row(
                    modifier = Modifier.padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        Icons.Default.Warning,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.error
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        captureError,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.error
                    )
                }
            }
            Spacer(modifier = Modifier.height(8.dp))
        }
        
        // Capture Button (manual fallback)
        if (!isCheckingLiveness && !canCapture) {
            Button(
                onClick = { performCapture() },
                enabled = !isLoading && faceDetected,
                modifier = Modifier
                    .size(if (isSmallScreen) 60.dp else 72.dp)
                    .clip(CircleShape)
            ) {
                Icon(
                    Icons.Default.CameraAlt,
                    contentDescription = "Capture",
                    modifier = Modifier.size(if (isSmallScreen) 24.dp else 32.dp)
                )
            }
        }
        
        // Auto-capture indicator
        if (canCapture) {
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = PresentColor
                ),
                modifier = Modifier.padding(if (isSmallScreen) 4.dp else 8.dp)
            ) {
                Text(
                    text = "✓ Ready to capture",
                    style = if (isSmallScreen) MaterialTheme.typography.bodySmall else MaterialTheme.typography.bodyMedium,
                    color = Color.White,
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                )
            }
        }
        
        Spacer(modifier = Modifier.height(if (isSmallScreen) 16.dp else 24.dp))
    }
    
    // Capture function
    val performCapture = {
        imageCapture?.let { capture ->
            capture.takePicture(
                cameraExecutor,
                object : ImageCapture.OnImageCapturedCallback() {
                    override fun onCaptureSuccess(image: ImageProxy) {
                        val bitmap = faceDetectionHelper.imageProxyToBitmap(image)
                        image.close()
                        onCapture(bitmap)
                        // Reset states for next capture
                        canCapture = false
                        isCheckingLiveness = false
                        livenessProgress = 0f
                    }
                    
                    override fun onError(exception: ImageCaptureException) {
                        onCapture(null)
                    }
                }
            )
        }
    }
    
    DisposableEffect(Unit) {
        onDispose {
            cameraExecutor.shutdown()
            faceDetectionHelper.close()
        }
    }
}

// Liveness check result classes
sealed class LivenessCheckResult {
    data class Success(val message: String) : LivenessCheckResult()
    data class Progress(val message: String, val progress: Float) : LivenessCheckResult()
    data class Failed(val message: String) : LivenessCheckResult()
}

// Perform liveness check based on required action
suspend fun performLivenessCheck(
    faceDetectionHelper: FaceDetectionHelper,
    action: LivenessAction,
    bitmap: Bitmap
): LivenessCheckResult {
    return when (action) {
        LivenessAction.BLINK -> checkBlinkLiveness(faceDetectionHelper, bitmap)
        LivenessAction.SMILE -> checkSmileLiveness(faceDetectionHelper, bitmap)
        LivenessAction.HEAD_TURN_LEFT -> checkHeadTurnLiveness(faceDetectionHelper, bitmap, "left")
        LivenessAction.HEAD_TURN_RIGHT -> checkHeadTurnLiveness(faceDetectionHelper, bitmap, "right")
        LivenessAction.BLINK_UP -> checkBlinkUpLiveness(faceDetectionHelper, bitmap)
    }
}

suspend fun checkBlinkLiveness(faceDetectionHelper: FaceDetectionHelper, bitmap: Bitmap): LivenessCheckResult {
    val faces = faceDetectionHelper.detectFaces(bitmap)
    if (faces.isEmpty()) return LivenessCheckResult.Failed("No face detected")
    
    val face = faces[0]
    val leftEyeOpen = face.leftEyeOpenProbability ?: 0f
    val rightEyeOpen = face.rightEyeOpenProbability ?: 0f
    
    return if (leftEyeOpen < 0.3f || rightEyeOpen < 0.3f) {
        LivenessCheckResult.Success("Blink detected!")
    } else {
        LivenessCheckResult.Progress("Blink to continue...", 0.5f)
    }
}

suspend fun checkSmileLiveness(faceDetectionHelper: FaceDetectionHelper, bitmap: Bitmap): LivenessCheckResult {
    val faces = faceDetectionHelper.detectFaces(bitmap)
    if (faces.isEmpty()) return LivenessCheckResult.Failed("No face detected")
    
    val face = faces[0]
    val smilingProbability = face.smilingProbability ?: 0f
    
    return if (smilingProbability > 0.7f) {
        LivenessCheckResult.Success("Smile detected!")
    } else {
        LivenessCheckResult.Progress("Smile for the camera...", smilingProbability)
    }
}

suspend fun checkHeadTurnLiveness(faceDetectionHelper: FaceDetectionHelper, bitmap: Bitmap, direction: String): LivenessCheckResult {
    val faces = faceDetectionHelper.detectFaces(bitmap)
    if (faces.isEmpty()) return LivenessCheckResult.Failed("No face detected")
    
    val face = faces[0]
    val headEulerAngleY = face.headEulerAngleY
    
    val threshold = when (direction) {
        "left" -> -20f
        "right" -> 20f
        else -> 0f
    }
    
    val progress = when (direction) {
        "left" -> if (headEulerAngleY < 0) kotlin.math.abs(headEulerAngleY) / 20f else 0f
        "right" -> if (headEulerAngleY > 0) headEulerAngleY / 20f else 0f
        else -> 0f
    }.coerceIn(0f, 1f)
    
    return if ((direction == "left" && headEulerAngleY < threshold) || 
               (direction == "right" && headEulerAngleY > threshold)) {
        LivenessCheckResult.Success("Head turn detected!")
    } else {
        LivenessCheckResult.Progress("Turn head $direction...", progress)
    }
}

suspend fun checkBlinkUpLiveness(faceDetectionHelper: FaceDetectionHelper, bitmap: Bitmap): LivenessCheckResult {
    val faces = faceDetectionHelper.detectFaces(bitmap)
    if (faces.isEmpty()) return LivenessCheckResult.Failed("No face detected")
    
    val face = faces[0]
    val headEulerAngleX = face.headEulerAngleX
    val leftEyeOpen = face.leftEyeOpenProbability ?: 0f
    val rightEyeOpen = face.rightEyeOpenProbability ?: 0f
    
    val lookingUp = headEulerAngleX < -15f
    val blinking = leftEyeOpen < 0.3f || rightEyeOpen < 0.3f
    
    return if (lookingUp && blinking) {
        LivenessCheckResult.Success("Look up and blink detected!")
    } else if (lookingUp) {
        LivenessCheckResult.Progress("Now blink while looking up...", 0.7f)
    } else {
        LivenessCheckResult.Progress("Look up slightly...", 0.3f)
    }
}

// Face Detection Camera Composable
@Composable
private fun FaceDetectionCamera(
    faceDetectionHelper: FaceDetectionHelper,
    onFaceDetected: (Face, Bitmap) -> Unit,
    onNoFaceDetected: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()
    
    var lastAnalysisTime by remember { mutableStateOf(0L) }
    
    AndroidView(
        factory = { ctx ->
            PreviewView(ctx).apply {
                scaleType = PreviewView.ScaleType.FILL_CENTER
                
                val cameraProviderFuture = ProcessCameraProvider.getInstance(ctx)
                cameraProviderFuture.addListener({
                    val cameraProvider = cameraProviderFuture.get()
                    
                    val preview = Preview.Builder().build().also {
                        it.setSurfaceProvider(surfaceProvider)
                    }
                    
                    val imageAnalysis = ImageAnalysis.Builder()
                        .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                        .build()
                    
                    imageAnalysis.setAnalyzer(Executors.newSingleThreadExecutor()) { imageProxy ->
                        val currentTime = System.currentTimeMillis()
                        if (currentTime - lastAnalysisTime < 500) { // Analyze every 500ms
                            imageProxy.close()
                            return@setAnalyzer
                        }
                        lastAnalysisTime = currentTime
                        
                        scope.launch {
                            try {
                                val bitmap = faceDetectionHelper.imageProxyToBitmap(imageProxy)
                                val faces = faceDetectionHelper.detectFaces(bitmap)
                                
                                if (faces.isNotEmpty()) {
                                    val face = faces[0]
                                    // Check if face is properly positioned
                                    val headEulerAngleY = face.headEulerAngleY
                                    val headEulerAngleZ = face.headEulerAngleZ
                                    
                                    if (kotlin.math.abs(headEulerAngleY) < 30 && kotlin.math.abs(headEulerAngleZ) < 20) {
                                        onFaceDetected(face, bitmap)
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
                    
                    val cameraSelector = CameraSelector.DEFAULT_FRONT_CAMERA
                    
                    try {
                        cameraProvider.unbindAll()
                        cameraProvider.bindToLifecycle(
                            lifecycleOwner,
                            cameraSelector,
                            preview,
                            imageAnalysis
                        )
                    } catch (e: Exception) {
                        e.printStackTrace()
                    }
                }, ContextCompat.getMainExecutor(ctx))
            }
        },
        modifier = modifier
    )
}
