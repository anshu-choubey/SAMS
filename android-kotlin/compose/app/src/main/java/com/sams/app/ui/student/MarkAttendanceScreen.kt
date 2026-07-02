package com.sams.app.ui.student

import android.Manifest
import android.annotation.SuppressLint
import android.util.Size
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
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.accompanist.permissions.ExperimentalPermissionsApi
import com.google.accompanist.permissions.rememberMultiplePermissionsState
import com.sams.app.data.models.MarkAttendanceResponse
import com.sams.app.ui.theme.AbsentColor
import com.sams.app.ui.theme.PresentColor
import com.sams.app.utils.FaceDetectionHelper
import com.sams.app.utils.LocationHelper
import kotlinx.coroutines.launch
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
    val storedEmbeddingLoaded by viewModel.storedFaceEmbeddingLoaded.collectAsState()
    val context = LocalContext.current
    val isDark = isSystemInDarkTheme()

    val gpsRadius = viewModel.getGpsRadius()
    val faceConfidenceThreshold = viewModel.getFaceConfidenceThreshold()
    val enableLivenessDetection = viewModel.getEnableLivenessDetection()

    val permissionsState = rememberMultiplePermissionsState(
        permissions = listOf(
            Manifest.permission.CAMERA,
            Manifest.permission.ACCESS_FINE_LOCATION
        )
    )

    val locationHelper = remember { LocationHelper(context) }
    val faceDetectionHelper = remember { FaceDetectionHelper(context) }

    var studentLat by remember { mutableStateOf<Double?>(null) }
    var studentLon by remember { mutableStateOf<Double?>(null) }
    var distanceToTeacher by remember { mutableStateOf<Double?>(null) }
    var isWithinProximity by remember { mutableStateOf(false) }
    var locationError by remember { mutableStateOf<String?>(null) }
    var verificationError by remember { mutableStateOf<String?>(null) }
    var isVerifying by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) { viewModel.loadStoredFaceEmbedding() }

    LaunchedEffect(permissionsState.allPermissionsGranted) {
        if (permissionsState.allPermissionsGranted) {
            try {
                val location = locationHelper.getCurrentLocation()
                if (location != null) {
                    studentLat = location.latitude
                    studentLon = location.longitude
                    distanceToTeacher = locationHelper.getDistanceToTeacher(
                        location.latitude, location.longitude, teacherLat, teacherLon
                    )
                    isWithinProximity = locationHelper.isWithinProximity(
                        location.latitude, location.longitude, teacherLat, teacherLon,
                        gpsRadius.toDouble()
                    )
                } else {
                    locationError = "Unable to get location. Enable GPS and try again."
                }
            } catch (e: Exception) {
                locationError = e.message ?: "Location error"
            }
        }
    }

    LaunchedEffect(uiState) {
        if (uiState is StudentUiState.Success) {
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
                title = {
                    Text(
                        text = "Mark Attendance",
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

        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(horizontal = 16.dp, vertical = 12.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {

            // ── Subject Banner ────────────────────────────────
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                elevation = CardDefaults.cardElevation(
                    defaultElevation = if (isDark) 4.dp else 0.dp
                )
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(
                            Brush.linearGradient(
                                colors = listOf(
                                    MaterialTheme.colorScheme.primary,
                                    MaterialTheme.colorScheme.tertiary
                                )
                            )
                        )
                        .padding(horizontal = 20.dp, vertical = 16.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(
                            text = "Now Attending",
                            style = MaterialTheme.typography.labelMedium,
                            color = Color.White.copy(alpha = 0.75f)
                        )
                        Spacer(Modifier.height(2.dp))
                        Text(
                            text = subjectName,
                            style = MaterialTheme.typography.titleMedium.copy(
                                fontWeight = FontWeight.ExtraBold
                            ),
                            color = Color.White,
                            textAlign = TextAlign.Center
                        )
                    }
                }
            }

            // ── Main State Content ────────────────────────────
            AnimatedContent(
                targetState = when {
                    !permissionsState.allPermissionsGranted -> "permission"
                    !storedEmbeddingLoaded -> "loadingFace"
                    uiState is StudentUiState.Success -> "success"
                    uiState is StudentUiState.Error -> "apiError"
                    locationError != null -> "locationError"
                    storedEmbedding == null -> "noFace"
                    !isWithinProximity && distanceToTeacher != null -> "outOfRange"
                    else -> "camera"
                },
                label = "attendanceState",
                transitionSpec = {
                    fadeIn(tween(300)) togetherWith fadeOut(tween(200))
                }
            ) { screenState ->
                when (screenState) {

                    "permission" -> AttendanceCenteredState(
                        icon = Icons.Default.CameraAlt,
                        iconTint = MaterialTheme.colorScheme.primary,
                        iconBgColor = MaterialTheme.colorScheme.primaryContainer,
                        title = "Permissions Required",
                        subtitle = "Camera for face verification and location to confirm you're in the classroom.",
                        actionLabel = "Grant Permissions",
                        onAction = { permissionsState.launchMultiplePermissionRequest() }
                    )

                    "loadingFace" -> AttendanceCenteredState(
                        icon = Icons.Default.Face,
                        iconTint = MaterialTheme.colorScheme.primary,
                        iconBgColor = MaterialTheme.colorScheme.primaryContainer,
                        title = "Checking face registration",
                        subtitle = "Please wait while we load your registered face data.",
                        actionLabel = "Loading",
                        onAction = {}
                    )

                    "noFace" -> AttendanceCenteredState(
                        icon = Icons.Default.Face,
                        iconTint = MaterialTheme.colorScheme.error,
                        iconBgColor = MaterialTheme.colorScheme.errorContainer,
                        title = "Face Not Registered",
                        subtitle = "Please register your face before marking attendance.",
                        actionLabel = "Go Back",
                        onAction = onBack
                    )

                    "outOfRange" -> AttendanceCenteredState(
                        icon = Icons.Default.LocationOff,
                        iconTint = AbsentColor,
                        // ✅ Alpha-based — works in dark mode
                        iconBgColor = AbsentColor.copy(alpha = 0.12f),
                        title = "Out of Range",
                        subtitle = "You must be within ${gpsRadius}m of your teacher.\nCurrent distance: ${distanceToTeacher?.toInt()}m",
                        actionLabel = "Go Back",
                        onAction = onBack
                    )

                    "locationError" -> AttendanceCenteredState(
                        icon = Icons.Default.LocationOff,
                        iconTint = MaterialTheme.colorScheme.error,
                        iconBgColor = MaterialTheme.colorScheme.errorContainer,
                        title = "Location Error",
                        subtitle = locationError ?: "Failed to get location",
                        actionLabel = "Retry",
                        onAction = { locationError = null }
                    )

                    "apiError" -> AttendanceCenteredState(
                        icon = Icons.Default.ErrorOutline,
                        iconTint = MaterialTheme.colorScheme.error,
                        iconBgColor = MaterialTheme.colorScheme.errorContainer,
                        title = "Verification Failed",
                        subtitle = (uiState as? StudentUiState.Error)?.message
                            ?: "Something went wrong. Please try again.",
                        actionLabel = "Try Again",
                        onAction = {
                            viewModel.resetAttendanceState()
                            verificationError = null
                        }
                    )

                    "success" -> {
                        val result = (uiState as? StudentUiState.Success)
                            ?.data as? MarkAttendanceResponse
                        AttendanceSuccessContent(result = result)
                    }

                    "camera" -> {
                        Column(
                            modifier = Modifier.fillMaxSize(),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            LocationStatusCard(
                                distance = distanceToTeacher,
                                isWithinProximity = isWithinProximity,
                                gpsRadius = gpsRadius
                            )

                            FaceVerificationStep(
                                storedEmbedding = storedEmbedding,
                                isVerifying = isVerifying,
                                verificationError = verificationError,
                                isMarkingAttendance = uiState is StudentUiState.Loading,
                                attendanceError = (uiState as? StudentUiState.Error)?.message,
                                faceConfidenceThreshold = faceConfidenceThreshold,
                                enableLivenessDetection = enableLivenessDetection,
                                isDark = isDark,
                                onFaceVerified = { confidence, _ ->
                                    isVerifying = false
                                    verificationError = null
                                    viewModel.markAttendance(
                                        scheduleId = scheduleId,
                                        latitude = studentLat!!,
                                        longitude = studentLon!!,
                                        faceConfidence = confidence
                                    )
                                }
                            )
                        }
                    }
                }
            }
        }
    }
}

// ── Centered State Screen ─────────────────────────────────────────────────────

@Composable
private fun AttendanceCenteredState(
    icon: ImageVector,
    iconTint: Color,
    iconBgColor: Color,
    title: String,
    subtitle: String,
    actionLabel: String,
    onAction: () -> Unit
) {
    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Box(
            modifier = Modifier
                .size(96.dp)
                .clip(CircleShape)
                .background(iconBgColor),
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
            color = MaterialTheme.colorScheme.onSurface,
            textAlign = TextAlign.Center
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

// ── Location Status Card ──────────────────────────────────────────────────────

@Composable
private fun LocationStatusCard(
    distance: Double?,
    isWithinProximity: Boolean,
    gpsRadius: Int
) {
    val cardColor = if (isWithinProximity)
        PresentColor.copy(alpha = 0.10f)
    else
        MaterialTheme.colorScheme.surfaceVariant

    val borderColor = if (isWithinProximity)
        PresentColor.copy(alpha = 0.35f)
    else
        Color.Transparent

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = cardColor),
        border = BorderStroke(1.dp, borderColor),
        elevation = CardDefaults.cardElevation(0.dp)
    ) {
        Row(
            modifier = Modifier.padding(14.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(42.dp)
                    .clip(CircleShape)
                    .background(
                        if (isWithinProximity) PresentColor.copy(alpha = 0.15f)
                        else MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.1f)
                    ),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.LocationOn,
                    contentDescription = null,
                    tint = if (isWithinProximity) PresentColor
                    else MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.size(20.dp)
                )
            }

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = when {
                        isWithinProximity -> "Within Range ✓"
                        distance == null -> "Fetching location..."
                        else -> "Checking range..."
                    },
                    style = MaterialTheme.typography.titleSmall.copy(
                        fontWeight = FontWeight.Bold
                    ),
                    color = if (isWithinProximity) PresentColor
                    else MaterialTheme.colorScheme.onSurface
                )
                distance?.let {
                    Text(
                        text = "${it.toInt()}m from teacher · Max ${gpsRadius}m",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }

            if (!isWithinProximity && distance == null) {
                CircularProgressIndicator(
                    modifier = Modifier.size(20.dp),
                    strokeWidth = 2.dp,
                    color = MaterialTheme.colorScheme.primary
                )
            }
        }
    }
}

// ── Success Content ───────────────────────────────────────────────────────────

@Composable
private fun AttendanceSuccessContent(result: MarkAttendanceResponse?) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Box(
            modifier = Modifier
                .size(100.dp)
                .clip(CircleShape)
                // ✅ Alpha-based — works in dark mode
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

        Spacer(Modifier.height(16.dp))

        Text(
            text = "Attendance Marked!",
            style = MaterialTheme.typography.headlineSmall.copy(
                fontWeight = FontWeight.ExtraBold
            ),
            color = PresentColor
        )

        Spacer(Modifier.height(6.dp))

        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            CircularProgressIndicator(
                modifier = Modifier.size(12.dp),
                strokeWidth = 2.dp,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            Text(
                text = "Redirecting...",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }

        result?.let {
            Spacer(Modifier.height(24.dp))

            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(
                    containerColor = PresentColor.copy(alpha = 0.08f)
                ),
                border = BorderStroke(1.dp, PresentColor.copy(alpha = 0.2f)),
                elevation = CardDefaults.cardElevation(0.dp)
            ) {
                Column(
                    modifier = Modifier.padding(20.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Text(
                        text = "Verification Summary",
                        style = MaterialTheme.typography.titleSmall.copy(
                            fontWeight = FontWeight.Bold
                        ),
                        color = MaterialTheme.colorScheme.onSurface
                    )

                    HorizontalDivider(color = PresentColor.copy(alpha = 0.15f))

                    ResultRow(
                        label = "Face Match",
                        value = "${it.faceConfidence?.toInt() ?: 0}%"
                    )
                    HorizontalDivider(color = PresentColor.copy(alpha = 0.1f))
                    ResultRow(
                        label = "Distance",
                        value = "${it.distanceMeters?.toInt() ?: 0}m"
                    )
                    HorizontalDivider(color = PresentColor.copy(alpha = 0.1f))
                    ResultRow(
                        label = "Status",
                        value = it.verificationStatus ?: "Success"
                    )
                }
            }
        }
    }
}

@Composable
private fun ResultRow(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium.copy(
                fontWeight = FontWeight.Bold
            ),
            color = PresentColor
        )
    }
}

// ── Face Verification Step ────────────────────────────────────────────────────

@SuppressLint("DefaultLocale")
@Composable
private fun FaceVerificationStep(
    storedEmbedding: String?,
    isVerifying: Boolean,
    verificationError: String?,
    isMarkingAttendance: Boolean,
    attendanceError: String?,
    faceConfidenceThreshold: Int,
    enableLivenessDetection: Boolean,
    isDark: Boolean,
    onFaceVerified: (confidence: Double, embedding: FloatArray) -> Unit
) {
    val context = LocalContext.current

    var faceDetected by remember { mutableStateOf(false) }
    var detectionStatus by remember { mutableStateOf("Position your face in the frame") }
    var currentConfidence by remember { mutableStateOf(0.0) }
    var hasAutoProceeded by remember { mutableStateOf(false) }
    var stableConfidenceCount by remember { mutableStateOf(0) }
    var verificationStartTime by remember { mutableLongStateOf(0L) }
    var elapsedProgress by remember { mutableFloatStateOf(0f) }

    val faceDetectionHelper = remember { FaceDetectionHelper(context) }
    DisposableEffect(Unit) { onDispose { faceDetectionHelper.close() } }

    LaunchedEffect(attendanceError) {
        if (attendanceError != null) {
            hasAutoProceeded = false
            stableConfidenceCount = 0
            verificationStartTime = 0L
            elapsedProgress = 0f
        }
    }

    LaunchedEffect(verificationStartTime) {
        if (verificationStartTime > 0L) {
            while (!hasAutoProceeded) {
                val e = (System.currentTimeMillis() - verificationStartTime) / 3000.0
                elapsedProgress = e.toFloat().coerceIn(0f, 1f)
                kotlinx.coroutines.delay(100)
            }
        }
    }

    if (storedEmbedding == null) return

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .verticalScroll(rememberScrollState()), // ✅ Scroll for small screens
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Text(
            text = "Face Verification",
            style = MaterialTheme.typography.titleMedium.copy(
                fontWeight = FontWeight.Bold
            ),
            color = MaterialTheme.colorScheme.onSurface
        )

        // ── Camera Circle ─────────────────────────────────
        BoxWithConstraints(
            modifier = Modifier.fillMaxWidth(),
            contentAlignment = Alignment.Center
        ) {
            val maxW = this.maxWidth
            val cameraSize = (maxW * 0.72f).coerceIn(200.dp, 280.dp)

            Box(
                modifier = Modifier
                    .size(cameraSize)
                    .clip(CircleShape)
                    .border(
                        width = 4.dp,
                        brush = Brush.linearGradient(
                            colors = when {
                                isVerifying -> listOf(
                                    Color(0xFFFFD700), Color(0xFFFFA000)
                                )
                                faceDetected && currentConfidence >= faceConfidenceThreshold ->
                                    listOf(PresentColor, PresentColor)
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
                FaceVerificationCamera(
                    faceDetectionHelper = faceDetectionHelper,
                    storedEmbedding = storedEmbedding,
                    enableLivenessDetection = enableLivenessDetection,
                    onFaceDetected = { confidence, embedding ->
                        faceDetected = true
                        currentConfidence = confidence
                        if (verificationStartTime == 0L)
                            verificationStartTime = System.currentTimeMillis()
                        val elapsed = (System.currentTimeMillis() - verificationStartTime) / 1000.0

                        if (confidence >= faceConfidenceThreshold) {
                            stableConfidenceCount++
                            detectionStatus = if (stableConfidenceCount >= 3
                                && elapsed >= 2.0
                                && !hasAutoProceeded
                            ) {
                                hasAutoProceeded = true
                                onFaceVerified(confidence, embedding)
                                "Verified! ${String.format("%.1f", confidence)}% match"
                            } else {
                                "Almost there... ${String.format("%.1f", confidence)}% (hold steady)"
                            }
                        } else {
                            stableConfidenceCount = 0
                            detectionStatus =
                                "${String.format("%.1f", confidence)}% — need ${faceConfidenceThreshold}%+"
                        }
                    },
                    onNoFaceDetected = {
                        faceDetected = false
                        stableConfidenceCount = 0
                        verificationStartTime = 0L
                        elapsedProgress = 0f
                        detectionStatus = "No face detected — look at the camera"
                    },
                    modifier = Modifier.fillMaxSize()
                )
            }
        }

        // ── Status Text ───────────────────────────────────
        Text(
            text = detectionStatus,
            style = MaterialTheme.typography.bodySmall.copy(
                fontWeight = FontWeight.Medium
            ),
            textAlign = TextAlign.Center,
            color = when {
                faceDetected && currentConfidence >= faceConfidenceThreshold -> PresentColor
                faceDetected -> MaterialTheme.colorScheme.primary
                else -> MaterialTheme.colorScheme.onSurfaceVariant
            }
        )

        // ── Confidence Card ───────────────────────────────
        AnimatedVisibility(
            visible = faceDetected,
            enter = fadeIn() + expandVertically(),
            exit = fadeOut() + shrinkVertically()
        ) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(
                    // ✅ Solid surfaceVariant — visible in dark mode
                    containerColor = MaterialTheme.colorScheme.surfaceVariant
                ),
                elevation = CardDefaults.cardElevation(0.dp)
            ) {
                Column(
                    modifier = Modifier.padding(14.dp),
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = "Match Confidence",
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Text(
                            text = "${String.format("%.1f", currentConfidence)}%",
                            style = MaterialTheme.typography.labelSmall.copy(
                                fontWeight = FontWeight.ExtraBold
                            ),
                            color = if (currentConfidence >= faceConfidenceThreshold)
                                PresentColor
                            else
                                MaterialTheme.colorScheme.primary
                        )
                    }

                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(8.dp)
                            .clip(RoundedCornerShape(4.dp))
                            .background(MaterialTheme.colorScheme.background)
                    ) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth(
                                    (currentConfidence / 100).toFloat().coerceIn(0f, 1f)
                                )
                                .fillMaxHeight()
                                .clip(RoundedCornerShape(4.dp))
                                .background(
                                    if (currentConfidence >= faceConfidenceThreshold)
                                        PresentColor
                                    else
                                        MaterialTheme.colorScheme.primary
                                )
                        )
                    }

                    Text(
                        text = "Minimum required: $faceConfidenceThreshold%",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
        }

        // ── Ticking Progress Bar ──────────────────────────
        AnimatedVisibility(
            visible = faceDetected && verificationStartTime > 0L && !hasAutoProceeded,
            enter = fadeIn(),
            exit = fadeOut()
        ) {
            LinearProgressIndicator(
                progress = { elapsedProgress },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(6.dp)
                    .clip(RoundedCornerShape(3.dp)),
                color = if (stableConfidenceCount >= 3) PresentColor
                else MaterialTheme.colorScheme.primary,
                trackColor = MaterialTheme.colorScheme.surfaceVariant
            )
        }

        // ── Error Card ────────────────────────────────────
        val errorMsg = attendanceError ?: verificationError
        AnimatedVisibility(
            visible = errorMsg != null,
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
                        tint = MaterialTheme.colorScheme.error,
                        modifier = Modifier.size(20.dp)
                    )
                    Text(
                        text = errorMsg ?: "",
                        style = MaterialTheme.typography.bodySmall,
                        // ✅ Uses onErrorContainer — readable in both modes
                        color = MaterialTheme.colorScheme.onErrorContainer,
                        modifier = Modifier.weight(1f)
                    )
                }
            }
        }

        // ── Marking Attendance Loader ─────────────────────
        AnimatedVisibility(
            visible = isMarkingAttendance,
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
                    modifier = Modifier.padding(18.dp),
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
                            text = "Marking Attendance...",
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

// ── Face Verification Camera ──────────────────────────────────────────────────

@Composable
private fun FaceVerificationCamera(
    faceDetectionHelper: FaceDetectionHelper,
    storedEmbedding: String,
    enableLivenessDetection: Boolean,
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
        val cameraProvider = ProcessCameraProvider.getInstance(context).get()
        val preview = Preview.Builder().build().also {
            it.setSurfaceProvider(previewView.surfaceProvider)
        }
        val imageAnalyzer = ImageAnalysis.Builder()
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .setTargetResolution(Size(480, 640))
            .build().also { analysis ->
                analysis.setAnalyzer(Executors.newSingleThreadExecutor()) { imageProxy ->
                    if (frameCounter.incrementAndGet() % 3 != 0) {
                        imageProxy.close()
                        return@setAnalyzer
                    }
                    val bitmap = try {
                        faceDetectionHelper.imageProxyToBitmap(imageProxy)
                    } catch (_: Exception) { null }
                    imageProxy.close()

                    if (bitmap == null) {
                        onNoFaceDetected()
                        return@setAnalyzer
                    }

                    scope.launch {
                        try {
                            val faces = faceDetectionHelper.detectFaces(bitmap)
                            if (faces.isNotEmpty()) {
                                val face = faces.first()
                                
                                // Check liveness only if enabled in settings
                                if (enableLivenessDetection) {
                                    val liveness = faceDetectionHelper.checkLiveness(face)
                                    val eyeBlink = faceDetectionHelper.detectEyeBlink(face)
                                    if (!liveness.isLive || eyeBlink.eyesClosed) {
                                        onNoFaceDetected()
                                        return@launch
                                    }
                                }

                                val embedding = faceDetectionHelper
                                    .extractFaceEmbedding(face, bitmap)
                                val confidence = faceDetectionHelper
                                    .compareFaces(embedding, storedEmbeddingArray)
                                onFaceDetected(confidence, embedding)
                            } else {
                                onNoFaceDetected()
                            }
                        } catch (_: Exception) {
                            onNoFaceDetected()
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
                imageAnalyzer
            )
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    AndroidView(factory = { previewView }, modifier = modifier)
}
