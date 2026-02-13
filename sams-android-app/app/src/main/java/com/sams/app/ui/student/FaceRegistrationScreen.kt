package com.sams.app.ui.student

import android.Manifest
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.animation.*
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
import com.google.mlkit.vision.face.Face
import com.sams.app.ui.components.*
import com.sams.app.ui.theme.*
import com.sams.app.utils.FaceDetectionHelper
import kotlinx.coroutines.launch
import java.util.concurrent.Executors
import kotlin.math.abs

@OptIn(ExperimentalMaterial3Api::class, ExperimentalPermissionsApi::class)
@Composable
fun FaceRegistrationScreen(
    viewModel: StudentViewModel = hiltViewModel(),
    onBack: () -> Unit,
    onSuccess: () -> Unit
) {
    val uiState by viewModel.faceRegistrationState.collectAsState()
    val cameraPermissionState = rememberPermissionState(Manifest.permission.CAMERA)
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    // Multi-step registration state
    var currentStep by remember { mutableStateOf(0) }
    val totalSteps = 4

    // Face collection data
    var collectedEmbeddings by remember { mutableStateOf(mutableListOf<FloatArray>()) }
    var currentInstruction by remember { mutableStateOf("Position your face in the circle") }
    var isCapturing by remember { mutableStateOf(false) }
    var progressValue by remember { mutableStateOf(0f) }

    val faceDetectionHelper = remember { FaceDetectionHelper(context) }

    // Cleanup
    DisposableEffect(Unit) {
        onDispose {
            faceDetectionHelper.close()
        }
    }

    LaunchedEffect(uiState) {
        if (uiState is StudentUiState.Success) {
            onSuccess()
        }
    }

    // Step instructions like iPhone Face ID
    val stepInstructions = listOf(
        "Position your face in the circle",
        "Slowly move your head in a circle",
        "Keep moving slowly",
        "Almost done - hold still"
    )

    val stepIcons = listOf(
        Icons.Outlined.Face,
        Icons.Outlined.Refresh,
        Icons.Outlined.Sync,
        Icons.Outlined.CheckCircle
    )

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Face ID Setup", fontWeight = FontWeight.Bold) },
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
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Progress indicator
            LinearProgressIndicator(
                progress = { progressValue },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(8.dp)
                    .clip(RoundedCornerShape(4.dp)),
                color = PrimaryBlue,
                trackColor = PrimaryBlueContainer,
            )

            Spacer(modifier = Modifier.height(24.dp))

            // Step indicator
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                for (i in 0 until totalSteps) {
                    val isCompleted = i < currentStep
                    val isCurrent = i == currentStep

                    Box(
                        modifier = Modifier
                            .size(40.dp)
                            .clip(CircleShape)
                            .background(
                                when {
                                    isCompleted -> SuccessGreen
                                    isCurrent -> PrimaryBlue
                                    else -> Color.Gray.copy(alpha = 0.3f)
                                }
                            ),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = if (isCompleted) Icons.Default.Check else stepIcons[i],
                            contentDescription = null,
                            tint = Color.White,
                            modifier = Modifier.size(20.dp)
                        )
                    }

                    if (i < totalSteps - 1) {
                        Spacer(modifier = Modifier.width(8.dp))
                        Box(
                            modifier = Modifier
                                .width(32.dp)
                                .height(2.dp)
                                .background(
                                    if (i < currentStep) SuccessGreen else Color.Gray.copy(alpha = 0.3f)
                                )
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                    }
                }
            }

            Spacer(modifier = Modifier.height(32.dp))

            // Current instruction with animation
            AnimatedContent(
                targetState = currentInstruction,
                transitionSpec = {
                    fadeIn() + slideInVertically() togetherWith fadeOut() + slideOutVertically()
                }
            ) { instruction ->
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(
                        imageVector = stepIcons[currentStep.coerceAtMost(stepIcons.size - 1)],
                        contentDescription = null,
                        modifier = Modifier.size(64.dp),
                        tint = PrimaryBlue
                    )

                    Spacer(modifier = Modifier.height(16.dp))

                    Text(
                        text = instruction,
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold,
                        textAlign = TextAlign.Center
                    )
                }
            }

            Spacer(modifier = Modifier.height(32.dp))

            if (!cameraPermissionState.status.isGranted) {
                PermissionRequest(
                    onRequestPermission = { cameraPermissionState.launchPermissionRequest() }
                )
            } else {
                // Camera with guided face registration
                FaceRegistrationCamera(
                    faceDetectionHelper = faceDetectionHelper,
                    currentStep = currentStep,
                    onFaceCaptured = { embedding ->
                        collectedEmbeddings.add(embedding)

                        if (currentStep < totalSteps - 1) {
                            currentStep++
                            progressValue = (currentStep + 1).toFloat() / totalSteps
                            currentInstruction = stepInstructions[currentStep]
                        } else {
                            // Registration complete - combine embeddings
                            isCapturing = true
                            scope.launch {
                                try {
                                    val combinedEmbedding = combineEmbeddings(collectedEmbeddings)
                                    val embeddingString = faceDetectionHelper.embeddingToString(combinedEmbedding)
                                    viewModel.registerFace(embeddingString)
                                } catch (e: Exception) {
                                    isCapturing = false
                                    currentInstruction = "Registration failed - try again"
                                }
                            }
                        }
                    },
                    onInstructionUpdate = { instruction ->
                        currentInstruction = instruction
                    },
                    modifier = Modifier
                        .size(280.dp)
                        .clip(CircleShape)
                        .border(
                            width = 4.dp,
                            color = PrimaryBlue,
                            shape = CircleShape
                        )
                )
            }

            Spacer(modifier = Modifier.weight(1f))

            // Status and error messages
            AnimatedVisibility(
                visible = uiState is StudentUiState.Error,
                enter = fadeIn() + expandVertically(),
                exit = fadeOut() + shrinkVertically()
            ) {
                if (uiState is StudentUiState.Error) {
                    SAMSCard(modifier = Modifier.fillMaxWidth()) {
                        Row(
                            modifier = Modifier.padding(16.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = Icons.Default.Error,
                                contentDescription = null,
                                tint = ErrorRed
                            )
                            Spacer(modifier = Modifier.width(12.dp))
                            Text(
                                text = (uiState as StudentUiState.Error).message,
                                color = ErrorRed,
                                style = MaterialTheme.typography.bodyMedium
                            )
                        }
                    }
                }
            }

            // Loading state
            if (uiState is StudentUiState.Loading || isCapturing) {
                SAMSCard(modifier = Modifier.fillMaxWidth()) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(24.dp),
                            color = PrimaryBlue
                        )
                        Spacer(modifier = Modifier.width(12.dp))
                        Text(
                            text = if (isCapturing) "Setting up Face ID..." else "Processing...",
                            style = MaterialTheme.typography.bodyMedium
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun FaceRegistrationCamera(
    faceDetectionHelper: FaceDetectionHelper,
    currentStep: Int,
    onFaceCaptured: (FloatArray) -> Unit,
    onInstructionUpdate: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val previewView = remember { PreviewView(context) }
    val scope = rememberCoroutineScope()

    // State for guided registration
    var lastCaptureTime by remember { mutableStateOf(0L) }
    var facePositionHistory by remember { mutableStateOf(mutableListOf<Triple<Float, Float, Float>>()) }
    var stepCompleted by remember(currentStep) { mutableStateOf(false) }

    // Step-specific logic
    val stepRequirements = when (currentStep) {
        0 -> StepRequirement("center", "Position your face in the center")
        1 -> StepRequirement("circle", "Slowly move your head in a circle")
        2 -> StepRequirement("continue", "Keep moving slowly")
        3 -> StepRequirement("complete", "Almost done - hold still")
        else -> StepRequirement("center", "Position your face in the center")
    }

    LaunchedEffect(Unit) {
        val cameraProviderFuture = ProcessCameraProvider.getInstance(context)
        val cameraProvider = cameraProviderFuture.get()

        val preview = Preview.Builder().build().also {
            it.setSurfaceProvider(previewView.surfaceProvider)
        }

        val imageAnalyzer = ImageAnalysis.Builder()
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .build()
            .also { analysis ->
                analysis.setAnalyzer(Executors.newSingleThreadExecutor()) { imageProxy ->
                    scope.launch {
                        try {
                            val faces = faceDetectionHelper.detectFaces(imageProxy)
                            if (faces.isNotEmpty()) {
                                val face = faces.first()

                                // Check liveness
                                val livenessResult = faceDetectionHelper.checkLiveness(face)
                                if (!livenessResult.isProbablyLive()) {
                                    onInstructionUpdate("Please show a live face - blink or move")
                                    imageProxy.close()
                                    return@launch
                                }

                                // Process based on current step
                                when (currentStep) {
                                    0 -> {
                                        // Step 1: Center face
                                        val headY = face.headEulerAngleY
                                        val headZ = face.headEulerAngleZ
                                        val isCentered = abs(headY) < 10 && Math.abs(headZ) < 10

                                        if (isCentered) {
                                            onInstructionUpdate("Perfect! Hold still...")
                                            // Auto-capture after 2 seconds of being centered
                                            if (System.currentTimeMillis() - lastCaptureTime > 2000) {
                                                val bitmap = faceDetectionHelper.imageProxyToBitmap(imageProxy)
                                                if (bitmap != null) {
                                                    val embedding = faceDetectionHelper.extractFaceEmbedding(face, bitmap)
                                                    onFaceCaptured(embedding)
                                                    lastCaptureTime = System.currentTimeMillis()
                                                    bitmap.recycle()
                                                }
                                            }
                                        } else {
                                            onInstructionUpdate("Position your face in the center")
                                        }
                                    }
                                    1, 2 -> {
                                        // Steps 2-3: Circular movement
                                        val headY = face.headEulerAngleY
                                        val headZ = face.headEulerAngleZ
                                        val headX = face.headEulerAngleX

                                        // Track face position
                                        facePositionHistory.add(Triple(headY, headZ, headX))

                                        // Keep only recent positions (last 10 frames)
                                        if (facePositionHistory.size > 10) {
                                            facePositionHistory.removeAt(0)
                                        }

                                        // Check if user has moved in a circle
                                        val hasCircularMovement = checkCircularMovement(facePositionHistory)

                                        if (hasCircularMovement && !stepCompleted) {
                                            stepCompleted = true
                                            onInstructionUpdate("Great! Hold still...")
                                            // Auto-capture after movement detected
                                            scope.launch {
                                                kotlinx.coroutines.delay(1000)
                                                val bitmap = faceDetectionHelper.imageProxyToBitmap(imageProxy)
                                                if (bitmap != null) {
                                                    val embedding = faceDetectionHelper.extractFaceEmbedding(face, bitmap)
                                                    onFaceCaptured(embedding)
                                                    bitmap.recycle()
                                                }
                                            }
                                        } else if (!hasCircularMovement) {
                                            onInstructionUpdate("Slowly move your head in a circle")
                                        }
                                    }
                                    3 -> {
                                        // Step 4: Final capture
                                        onInstructionUpdate("Perfect! Capturing...")
                                        val bitmap = faceDetectionHelper.imageProxyToBitmap(imageProxy)
                                        if (bitmap != null) {
                                            val embedding = faceDetectionHelper.extractFaceEmbedding(face, bitmap)
                                            onFaceCaptured(embedding)
                                            bitmap.recycle()
                                        }
                                    }
                                }
                            } else {
                                onInstructionUpdate(stepRequirements.instruction)
                            }
                        } catch (e: Exception) {
                            onInstructionUpdate("Face detection error - try again")
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

@Composable
private fun PermissionRequest(onRequestPermission: () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Icon(
            imageVector = Icons.Outlined.CameraAlt,
            contentDescription = null,
            modifier = Modifier.size(80.dp),
            tint = MaterialTheme.colorScheme.primary
        )

        Spacer(modifier = Modifier.height(24.dp))

        Text(
            text = "Camera Permission Required",
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold
        )

        Spacer(modifier = Modifier.height(12.dp))

        Text(
            text = "We need camera access to capture your face for attendance verification.",
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
            Text("Grant Permission")
        }
    }
}

/**
 * Check if the face has moved in a roughly circular pattern
 */
private fun checkCircularMovement(positions: List<Triple<Float, Float, Float>>): Boolean {
    if (positions.size < 8) return false

    // Calculate movement range
    val yRange = positions.maxOf { it.first } - positions.minOf { it.first }
    val zRange = positions.maxOf { it.second } - positions.minOf { it.second }

    // Need significant movement in both Y and Z axes (circular motion)
    return yRange > 20 && zRange > 20
}

/**
 * Requirements for each registration step
 */
private data class StepRequirement(
    val type: String,
    val instruction: String
)

/**
 * Combine multiple face embeddings into a single robust embedding
 */
private fun combineEmbeddings(embeddings: List<FloatArray>): FloatArray {
    if (embeddings.isEmpty()) return FloatArray(128)

    val combined = FloatArray(128)
    for (i in combined.indices) {
        var sum = 0f
        for (embedding in embeddings) {
            if (i < embedding.size) {
                sum += embedding[i]
            }
        }
        combined[i] = sum / embeddings.size
    }

    return combined
}
