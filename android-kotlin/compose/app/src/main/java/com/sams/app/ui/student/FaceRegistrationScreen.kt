package com.sams.app.ui.student

import android.Manifest
import android.graphics.Bitmap
import androidx.camera.core.*
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.animation.*
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
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
import com.sams.app.ui.theme.PresentColor
import com.sams.app.utils.FaceDetectionHelper
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import timber.log.Timber
import java.util.concurrent.Executors

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
    val isDark = isSystemInDarkTheme()

    val faceConfidenceThreshold = viewModel.getFaceConfidenceThreshold()
    val enableLivenessDetection = viewModel.getEnableLivenessDetection()
    val cameraPermissionState = rememberPermissionState(Manifest.permission.CAMERA)
    val faceDetectionHelper = remember { FaceDetectionHelper(context) }

    var capturedCount by remember { mutableIntStateOf(0) }
    val requiredCaptures = 3
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
                title = {
                    Text(
                        text = "Register Face",
                        style = MaterialTheme.typography.titleLarge.copy(
                            fontWeight = FontWeight.Bold
                        )
                    )
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back",
                            tint = MaterialTheme.colorScheme.onSurface
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surface,
                    titleContentColor = MaterialTheme.colorScheme.onSurface,
                    navigationIconContentColor = MaterialTheme.colorScheme.onSurface
                )
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { padding ->

        AnimatedContent(
            targetState = when {
                !cameraPermissionState.status.isGranted -> "permission"
                uiState is StudentUiState.Success -> "success"
                uiState is StudentUiState.Error -> "error"
                else -> "capture"
            },
            label = "faceRegistrationState",
            transitionSpec = {
                fadeIn(tween(300)) togetherWith fadeOut(tween(200))
            }
        ) { screenState ->
            when (screenState) {

                // ── Permission ────────────────────────────────
                "permission" -> CenteredStateScreen(
                    icon = Icons.Default.CameraAlt,
                    iconTint = MaterialTheme.colorScheme.primary,
                    iconBgColor = MaterialTheme.colorScheme.primaryContainer,
                    title = "Camera Required",
                    subtitle = "We need camera access to capture your face for attendance verification.",
                    actionLabel = "Grant Permission",
                    onAction = { cameraPermissionState.launchPermissionRequest() },
                    modifier = Modifier
                        .padding(padding)
                        .padding(24.dp)
                )

                // ── Success ───────────────────────────────────
                "success" -> Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(padding)
                        .padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center
                ) {
                    Box(
                        modifier = Modifier
                            .size(100.dp)
                            .clip(CircleShape)
                            // ✅ Alpha-based — visible in dark mode
                            .background(PresentColor.copy(alpha = 0.12f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            Icons.Default.CheckCircle,
                            contentDescription = null,
                            modifier = Modifier.size(56.dp),
                            tint = PresentColor
                        )
                    }

                    Spacer(Modifier.height(20.dp))

                    Text(
                        text = "Face Registered!",
                        style = MaterialTheme.typography.headlineSmall.copy(
                            fontWeight = FontWeight.ExtraBold
                        ),
                        color = PresentColor
                    )

                    Spacer(Modifier.height(8.dp))

                    Text(
                        text = "You can now mark attendance using face verification.",
                        style = MaterialTheme.typography.bodyMedium,
                        textAlign = TextAlign.Center,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        modifier = Modifier.padding(horizontal = 32.dp)
                    )

                    Spacer(Modifier.height(6.dp))

                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(6.dp)
                    ) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(14.dp),
                            strokeWidth = 2.dp,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Text(
                            text = "Redirecting...",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }

                // ── Error ─────────────────────────────────────
                "error" -> CenteredStateScreen(
                    icon = Icons.Default.ErrorOutline,
                    iconTint = MaterialTheme.colorScheme.error,
                    iconBgColor = MaterialTheme.colorScheme.errorContainer,
                    title = "Registration Failed",
                    subtitle = (uiState as? StudentUiState.Error)?.message
                        ?: "Something went wrong. Please try again.",
                    actionLabel = "Try Again",
                    onAction = {
                        viewModel.resetFaceRegistrationState()
                        capturedCount = 0
                        capturedEmbeddings.clear()
                        captureError = null
                        isProcessing = false
                    },
                    modifier = Modifier
                        .padding(padding)
                        .padding(24.dp)
                )

                // ── Capture ───────────────────────────────────
                "capture" -> FaceCapture(
                    capturedCount = capturedCount,
                    requiredCaptures = requiredCaptures,
                    isLoading = uiState is StudentUiState.Loading,
                    isProcessing = isProcessing,
                    captureError = captureError,
                    faceDetectionHelper = faceDetectionHelper,
                    faceConfidenceThreshold = faceConfidenceThreshold,
                    enableLivenessDetection = enableLivenessDetection,
                    isDark = isDark,
                    modifier = Modifier
                        .padding(padding)
                        .padding(horizontal = 20.dp, vertical = 12.dp),
                    onCapture = { bitmap ->
                        if (bitmap != null && !isProcessing) {
                            isProcessing = true
                            captureError = null
                            scope.launch {
                                try {
                                    val faces = withContext(Dispatchers.Default) {
                                        faceDetectionHelper.detectFaces(bitmap)
                                    }
                                    when {
                                        faces.isEmpty() -> {
                                            captureError = "No face detected. Try again."
                                            isProcessing = false
                                            return@launch
                                        }
                                        faces.size > 1 -> {
                                            captureError = "Multiple faces detected."
                                            isProcessing = false
                                            return@launch
                                        }
                                    }
                                    val embedding = withContext(Dispatchers.Default) {
                                        try {
                                            faceDetectionHelper.extractFaceEmbedding(
                                                faces.first(), bitmap
                                            )
                                        } catch (_: Exception) {
                                            floatArrayOf()
                                        }
                                    }
                                    if (embedding.size != 128 || embedding.none { it != 0f }) {
                                        captureError = "Face not clear. Try again."
                                        isProcessing = false
                                        return@launch
                                    }
                                    capturedEmbeddings.add(embedding)
                                    
                                    capturedCount++      // ✅ Increment first
                                    captureError = null
                                    isProcessing = false

                                    if (capturedCount >= requiredCaptures) {
                                        val averagedEmbedding = FloatArray(embedding.size)
                                        capturedEmbeddings.forEach { sample ->
                                            sample.forEachIndexed { index, value ->
                                                averagedEmbedding[index] += value
                                            }
                                        }

                                        averagedEmbedding.indices.forEach { index ->
                                            averagedEmbedding[index] /= capturedEmbeddings.size.toFloat()
                                        }

                                        val norm = kotlin.math.sqrt(
                                            averagedEmbedding.map { it * it }.sum().toDouble()
                                        ).toFloat()
                                        if (norm > 0) {
                                            averagedEmbedding.indices.forEach { index ->
                                                averagedEmbedding[index] /= norm
                                            }
                                        }

                                        viewModel.registerFace(
                                            faceDetectionHelper.embeddingToString(averagedEmbedding)
                                        )
                                    }
                                } catch (e: Exception) {
                                    captureError = "Error: ${e.message ?: "Processing failed"}"
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

// ── Centered State Screen ─────────────────────────────────────────────────────

@Composable
private fun CenteredStateScreen(
    icon: ImageVector,
    iconTint: Color,
    iconBgColor: Color,
    title: String,
    subtitle: String,
    actionLabel: String,
    onAction: () -> Unit,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Box(
            modifier = Modifier
                .size(96.dp)
                .clip(CircleShape)
                .background(iconBgColor), // ✅ Uses theme errorContainer / primaryContainer
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                modifier = Modifier.size(48.dp),
                tint = iconTint
            )
        }

        Spacer(Modifier.height(20.dp))

        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge.copy(
                fontWeight = FontWeight.ExtraBold
            ),
            color = MaterialTheme.colorScheme.onSurface
        )

        Spacer(Modifier.height(8.dp))

        Text(
            text = subtitle,
            style = MaterialTheme.typography.bodyMedium,
            textAlign = TextAlign.Center,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.padding(horizontal = 24.dp)
        )

        Spacer(Modifier.height(28.dp))

        Button(
            onClick = onAction,
            modifier = Modifier
                .fillMaxWidth(0.65f)
                .height(52.dp),
            shape = RoundedCornerShape(14.dp),
            colors = ButtonDefaults.buttonColors(
                containerColor = MaterialTheme.colorScheme.primary
            )
        ) {
            Text(
                text = actionLabel,
                style = MaterialTheme.typography.titleSmall.copy(
                    fontWeight = FontWeight.Bold
                )
            )
        }
    }
}

// ── Face Capture ──────────────────────────────────────────────────────────────

@Composable
private fun FaceCapture(
    capturedCount: Int,
    requiredCaptures: Int,
    isLoading: Boolean,
    isProcessing: Boolean,
    captureError: String?,
    faceDetectionHelper: FaceDetectionHelper,
    faceConfidenceThreshold: Int,
    enableLivenessDetection: Boolean,
    isDark: Boolean,
    modifier: Modifier = Modifier,
    onCapture: (Bitmap?) -> Unit
) {
    val scope = rememberCoroutineScope()
    var lastCaptureTime by remember { mutableLongStateOf(-2000L) }
    var isCapturing by remember { mutableStateOf(false) }
    var faceDetected by remember { mutableStateOf(false) }
    var livenessStatus by remember { mutableStateOf("") }

    LaunchedEffect(capturedCount) {
        isCapturing = false
        lastCaptureTime = System.currentTimeMillis()
        livenessStatus = ""
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()), // ✅ Scroll on small screens
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {

        // ── Header Row ────────────────────────────────────
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.fillMaxWidth()
        ) {
            Box(
                modifier = Modifier
                    .width(4.dp)
                    .height(20.dp)
                    .clip(RoundedCornerShape(2.dp))
                    .background(MaterialTheme.colorScheme.primary)
            )
            Spacer(Modifier.width(8.dp))
            Text(
                text = "Face Registration",
                style = MaterialTheme.typography.titleMedium.copy(
                    fontWeight = FontWeight.Bold
                ),
                color = MaterialTheme.colorScheme.onSurface
            )
            Spacer(Modifier.weight(1f))
            Surface(
                shape = RoundedCornerShape(50.dp),
                color = MaterialTheme.colorScheme.primaryContainer
            ) {
                Text(
                    text = "$capturedCount / $requiredCaptures",
                    style = MaterialTheme.typography.labelMedium.copy(
                        fontWeight = FontWeight.Bold
                    ),
                    color = MaterialTheme.colorScheme.onPrimaryContainer,
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 5.dp)
                )
            }
        }

        // ── Progress Bar ──────────────────────────────────
        Column(
            modifier = Modifier.fillMaxWidth(),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(8.dp)
                    .clip(RoundedCornerShape(4.dp))
                    .background(MaterialTheme.colorScheme.surfaceVariant)
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth(capturedCount.toFloat() / requiredCaptures)
                        .fillMaxHeight()
                        .clip(RoundedCornerShape(4.dp))
                        .background(
                            Brush.horizontalGradient(
                                listOf(
                                    MaterialTheme.colorScheme.primary,
                                    MaterialTheme.colorScheme.tertiary
                                )
                            )
                        )
                )
            }
            Text(
                text = if (requiredCaptures > 0) "${(capturedCount * 100) / requiredCaptures}% complete" else "0% complete",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.align(Alignment.End)
            )
        }

        // ── Instruction Card ──────────────────────────────
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(
                // ✅ Solid surface — always visible in dark mode
                containerColor = MaterialTheme.colorScheme.surfaceVariant
            ),
            elevation = CardDefaults.cardElevation(0.dp)
        ) {
            Column(
                modifier = Modifier.padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(40.dp)
                            .clip(CircleShape)
                            .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.15f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = "${capturedCount + 1}",
                            style = MaterialTheme.typography.titleSmall.copy(
                                fontWeight = FontWeight.ExtraBold
                            ),
                            color = MaterialTheme.colorScheme.primary
                        )
                    }
                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = "Keep your face in the frame",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Text(
                            text = "Look directly at the camera",
                            style = MaterialTheme.typography.bodyMedium.copy(
                                fontWeight = FontWeight.Bold
                            ),
                            color = MaterialTheme.colorScheme.primary
                        )
                    }
                }

                HorizontalDivider(
                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.15f)
                )

                // Requirements
                listOf(
                    "Eyes must be open",
                    "Face must be real (not a photo)",
                    "Look straight at the camera"
                ).forEach { requirement ->
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Icon(
                            Icons.Default.Done,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = PresentColor
                        )
                        Text(
                            text = requirement,
                            style = MaterialTheme.typography.labelMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
        }

        // ── Camera Circle ─────────────────────────────────
        BoxWithConstraints(
            modifier = Modifier.fillMaxWidth(),
            contentAlignment = Alignment.Center
        ) {
            val maxW = this.maxWidth
            val cameraSize = (maxW * 0.72f).coerceIn(200.dp, 300.dp)

            Box(
                modifier = Modifier
                    .size(cameraSize) // ✅ Responsive circle size
                    .clip(CircleShape)
                    .border(
                        width = 4.dp,
                        brush = Brush.linearGradient(
                            colors = when {
                                isCapturing -> listOf(PresentColor, PresentColor)
                                faceDetected -> listOf(
                                    MaterialTheme.colorScheme.primary,
                                    MaterialTheme.colorScheme.tertiary
                                )
                                else -> listOf(
                                    MaterialTheme.colorScheme.outlineVariant,
                                    MaterialTheme.colorScheme.outlineVariant
                                )
                            }
                        ),
                        shape = CircleShape
                    ),
                contentAlignment = Alignment.Center
            ) {
                FaceDetectionCamera(
                    faceDetectionHelper = faceDetectionHelper,
                    faceConfidenceThreshold = faceConfidenceThreshold,
                    enableLivenessDetection = enableLivenessDetection,
                    onFaceDetected = { _, bitmap ->
                        faceDetected = true
                        val now = System.currentTimeMillis()
                        if (!isCapturing && !isProcessing && (now - lastCaptureTime) > 800L) {
                            lastCaptureTime = now
                            isCapturing = true
                            scope.launch {
                                try {
                                    onCapture(bitmap)
                                } finally {
                                    isCapturing = false
                                }
                            }
                        }
                    },
                    onNoFaceDetected = { faceDetected = false },
                    modifier = Modifier.fillMaxSize()
                )
            }
        }

        // ── Status Text ───────────────────────────────────
        Text(
            text = when {
                isCapturing -> "Capturing ${capturedCount + 1} of $requiredCaptures..."
                faceDetected -> "Face detected — verifying liveness..."
                livenessStatus.isNotEmpty() -> livenessStatus
                else -> "Position your face in the circle"
            },
            style = MaterialTheme.typography.bodySmall.copy(
                fontWeight = FontWeight.Medium
            ),
            textAlign = TextAlign.Center,
            color = when {
                isCapturing -> PresentColor
                faceDetected -> MaterialTheme.colorScheme.primary
                livenessStatus.contains("Not a real face") -> MaterialTheme.colorScheme.error
                else -> MaterialTheme.colorScheme.onSurfaceVariant
            }
        )

        // ── Capture Progress ──────────────────────────────
        AnimatedVisibility(
            visible = isCapturing,
            enter = fadeIn() + expandVertically(),
            exit = fadeOut() + shrinkVertically()
        ) {
            LinearProgressIndicator(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(6.dp)
                    .clip(RoundedCornerShape(3.dp)),
                color = PresentColor,
                trackColor = MaterialTheme.colorScheme.surfaceVariant
            )
        }

        // ── Error Card ────────────────────────────────────
        AnimatedVisibility(
            visible = captureError != null,
            enter = fadeIn() + expandVertically(),
            exit = fadeOut() + shrinkVertically()
        ) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.errorContainer
                ),
                border = BorderStroke(
                    1.dp,
                    MaterialTheme.colorScheme.error.copy(alpha = 0.3f)
                ),
                elevation = CardDefaults.cardElevation(0.dp)
            ) {
                Row(
                    modifier = Modifier.padding(14.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Icon(
                        Icons.Default.Warning,
                        contentDescription = null,
                        modifier = Modifier.size(18.dp),
                        tint = MaterialTheme.colorScheme.error
                    )
                    Text(
                        text = captureError ?: "",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onErrorContainer,
                        modifier = Modifier.weight(1f)
                    )
                }
            }
        }

        // ── Loading Card (uploading) ───────────────────────
        AnimatedVisibility(
            visible = isLoading,
            enter = fadeIn() + expandVertically(),
            exit = fadeOut() + shrinkVertically()
        ) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.surface
                ),
                elevation = CardDefaults.cardElevation(
                    defaultElevation = if (isDark) 4.dp else 2.dp
                )
            ) {
                Row(
                    modifier = Modifier.padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(14.dp)
                ) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(24.dp),
                        strokeWidth = 2.5.dp,
                        color = MaterialTheme.colorScheme.primary
                    )
                    Column {
                        Text(
                            text = "Registering Face...",
                            style = MaterialTheme.typography.titleSmall.copy(
                                fontWeight = FontWeight.Bold
                            ),
                            color = MaterialTheme.colorScheme.onSurface
                        )
                        Text(
                            text = "Uploading to server, please wait",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }
        }

        Spacer(Modifier.height(8.dp))
    }
}

// ── Face Detection Camera ─────────────────────────────────────────────────────

@Composable
private fun FaceDetectionCamera(
    faceDetectionHelper: FaceDetectionHelper,
    faceConfidenceThreshold: Int,
    enableLivenessDetection: Boolean,
    onFaceDetected: (Face, Bitmap) -> Unit,
    onNoFaceDetected: () -> Unit,
    onImageCaptureReady: (ImageCapture) -> Unit = {},
    modifier: Modifier = Modifier
) {
    val lifecycleOwner = LocalLifecycleOwner.current
    val onFaceDetectedRef = rememberUpdatedState(onFaceDetected)
    val onNoFaceDetectedRef = rememberUpdatedState(onNoFaceDetected)
    var lastAnalysisTime by remember { mutableLongStateOf(0L) }

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
                    val imageCapture = ImageCapture.Builder()
                        .setCaptureMode(ImageCapture.CAPTURE_MODE_MINIMIZE_LATENCY)
                        .build()
                    onImageCaptureReady(imageCapture)

                    val imageAnalysis = ImageAnalysis.Builder()
                        .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                        .build()

                    val executor = Executors.newSingleThreadExecutor()
                    imageAnalysis.setAnalyzer(executor) { imageProxy ->
                        val now = System.currentTimeMillis()
                        if (now - lastAnalysisTime < 500) {
                            imageProxy.close()
                            return@setAnalyzer
                        }
                        lastAnalysisTime = now

                        val bitmap = try {
                            faceDetectionHelper.imageProxyToBitmap(imageProxy)
                        } catch (e: Exception) { null }
                        imageProxy.close()

                        if (bitmap == null) {
                            onNoFaceDetectedRef.value()
                            return@setAnalyzer
                        }

                        kotlinx.coroutines.runBlocking {
                            try {
                                val faces = faceDetectionHelper.detectFaces(bitmap)
                                Timber.tag("FaceCam").d("Faces: ${faces.size}")
                                if (faces.isNotEmpty()) {
                                    val face = faces[0]
                                    val yaw = kotlin.math.abs(face.headEulerAngleY)
                                    val roll = kotlin.math.abs(face.headEulerAngleZ)
                                    
                                    // Check if face angle and liveness requirements are met
                                    val isAngleValid = yaw < 35 && roll < 25
                                    val isLivenessValid = if (enableLivenessDetection) {
                                        val liveness = faceDetectionHelper.checkLiveness(face)
                                        val eyeBlink = faceDetectionHelper.detectEyeBlink(face)
                                        liveness.confidence >= faceConfidenceThreshold && !eyeBlink.eyesClosed
                                    } else {
                                        true  // Skip liveness check if disabled in settings
                                    }

                                    if (isAngleValid && isLivenessValid) {
                                        onFaceDetectedRef.value(face, bitmap)
                                    } else {
                                        onNoFaceDetectedRef.value()
                                    }
                                } else {
                                    onNoFaceDetectedRef.value()
                                }
                            } catch (e: Exception) {
                                Timber.tag("FaceCam").e("Detection error: ${e.message}")
                                onNoFaceDetectedRef.value()
                            }
                        }
                    }

                    try {
                        cameraProvider.unbindAll()
                        cameraProvider.bindToLifecycle(
                            lifecycleOwner,
                            CameraSelector.DEFAULT_FRONT_CAMERA,
                            preview, imageCapture, imageAnalysis
                        )
                    } catch (e: Exception) {
                        Timber.tag("FaceCam").e("Bind error: ${e.message}")
                    }
                }, ContextCompat.getMainExecutor(ctx))
            }
        },
        modifier = modifier
    )
}


