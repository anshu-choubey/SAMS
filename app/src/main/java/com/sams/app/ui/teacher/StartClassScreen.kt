package com.sams.app.ui.teacher

import android.util.Log
import androidx.compose.animation.*
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.StartSessionData
import com.sams.app.ui.theme.PresentColor
import com.sams.app.utils.LocationHelper
import com.sams.app.utils.LocationPermissionHandler

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StartClassScreen(
    scheduleId: Int,
    viewModel: TeacherViewModel = hiltViewModel(),
    onBack: () -> Unit,
    onSessionStarted: (Int) -> Unit
) {
    val uiState by viewModel.sessionState.collectAsState()
    val context = LocalContext.current
    val isDark = isSystemInDarkTheme()
    val locationHelper = remember { LocationHelper(context) }
    val gpsRadius = viewModel.getGpsRadius()

    var currentLocation by remember { mutableStateOf<Pair<Double, Double>?>(null) }
    var locationAccuracy by remember { mutableStateOf<LocationHelper.LocationAccuracy?>(null) }
    var locationError by remember { mutableStateOf<String?>(null) }
    var isFetchingLocation by remember { mutableStateOf(false) }
    var locationRetryTrigger by remember { mutableIntStateOf(0) }
    var permissionGranted by remember { mutableStateOf(false) }

    LaunchedEffect(permissionGranted, locationRetryTrigger) {
        if (!permissionGranted) {
            Log.d("StartClassScreen", "Permission not granted yet, skipping location fetch")
            return@LaunchedEffect
        }
        Log.d("StartClassScreen", "Starting location fetch (attempt ${locationRetryTrigger + 1})")
        isFetchingLocation = true
        locationError = null
        currentLocation = null

        when (val result = locationHelper.getCurrentLocationResult()) {
            is LocationHelper.LocationResult.Success -> {
                Log.d("StartClassScreen", "Location obtained: lat=${result.location.latitude}, lon=${result.location.longitude}, accuracy=${result.accuracy.label()}")
                if (!result.accuracy.isUsable()) {
                    locationError = "GPS accuracy too low (${locationHelper.run { result.accuracy.label() }}). " +
                            "Move to an open area and retry."
                    Log.w("StartClassScreen", "Location accuracy unusable: $locationError")
                } else {
                    currentLocation = result.location.latitude to result.location.longitude
                    locationAccuracy = result.accuracy
                    Log.d("StartClassScreen", "Location accepted with accuracy: ${result.accuracy.label()}")
                }
            }
            is LocationHelper.LocationResult.Error -> {
                locationError = result.message
                Log.e("StartClassScreen", "Location error: ${result.message} (type: ${result.type})")
            }
        }
        isFetchingLocation = false
    }

    LaunchedEffect(uiState) {
        if (uiState is TeacherUiState.Success) {
            kotlinx.coroutines.delay(1500)
            val sessionData = (uiState as TeacherUiState.Success).data as? StartSessionData
            // ✅ Use scheduleId from backend response, NOT sessionId
            val idForAttendance = sessionData?.scheduleId ?: scheduleId
            Log.d("StartClassScreen", "Session started: sessionId=${sessionData?.sessionId}, scheduleId=${sessionData?.scheduleId}, passing scheduleId=$idForAttendance to ClassAttendance")
            viewModel.resetSessionState()
            onSessionStarted(idForAttendance)  // ✅ Send scheduleId, not sessionId
        }
    }

    DisposableEffect(Unit) {
        onDispose { viewModel.resetSessionState() }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Text(
                        text = "Start Class",
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
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
        ) {
            LocationPermissionHandler(
                onPermissionGranted = { permissionGranted = true }
            ) {
                AnimatedContent(
                    targetState = when {
                        isFetchingLocation                -> "fetching"
                        locationError != null             -> "locationError"
                        uiState is TeacherUiState.Loading -> "starting"
                        uiState is TeacherUiState.Success -> "success"
                        uiState is TeacherUiState.Error   -> "apiError"
                        currentLocation != null           -> "ready"
                        else                              -> "fetching"
                    },
                    label = "startClassState",
                    transitionSpec = {
                        fadeIn(tween(300)) togetherWith fadeOut(tween(200))
                    }
                ) { screenState ->
                    // Centering wrapper for all states
                    Box(
                        modifier = Modifier
                            .fillMaxSize()
                            .verticalScroll(rememberScrollState())
                            .padding(24.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        when (screenState) {
                            "fetching" -> LoadingContent("Getting your location... (${locationRetryTrigger + 1} attempt)")
                            "starting" -> LoadingContent("Starting session...")

                            "locationError" -> StateScreen(
                                icon = Icons.Default.LocationOff,
                                iconTint = MaterialTheme.colorScheme.error,
                                iconBgColor = MaterialTheme.colorScheme.errorContainer,
                                iconFgColor = MaterialTheme.colorScheme.onErrorContainer,
                                title = "Location Error",
                                subtitle = locationError ?: "Failed to get location",
                                actionLabel = "Retry",
                                onAction = { locationRetryTrigger++ }
                            )

                            "success" -> SuccessContent()

                            "apiError" -> StateScreen(
                                icon = Icons.Default.ErrorOutline,
                                iconTint = MaterialTheme.colorScheme.error,
                                iconBgColor = MaterialTheme.colorScheme.errorContainer,
                                iconFgColor = MaterialTheme.colorScheme.onErrorContainer,
                                title = "Failed to Start",
                                subtitle = (uiState as? TeacherUiState.Error)?.message
                                    ?: "Something went wrong",
                                actionLabel = "Try Again",
                                onAction = { viewModel.resetSessionState() }
                            )

                            "ready" -> {
                                val (lat, lon) = currentLocation!!
                                StartSessionContent(
                                    latitude = lat,
                                    longitude = lon,
                                    accuracy = locationAccuracy,
                                    gpsRadius = gpsRadius,
                                    isDark = isDark,
                                    onStartSession = {
                                        viewModel.startSession(
                                            scheduleId = scheduleId,
                                            latitude = lat,
                                            longitude = lon
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
}

// ── Loading Content ───────────────────────────────────────────────────────────

@Composable
private fun LoadingContent(message: String) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        CircularProgressIndicator(
            color = MaterialTheme.colorScheme.primary,
            strokeWidth = 3.dp
        )
        Text(
            text = message,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

// ── Success Content ───────────────────────────────────────────────────────────

@Composable
private fun SuccessContent() {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Box(
            modifier = Modifier
                .size(100.dp)
                .clip(CircleShape)
                // ✅ Alpha-based — dark mode safe
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
        Text(
            text = "Session Started!",
            style = MaterialTheme.typography.headlineSmall.copy(
                fontWeight = FontWeight.ExtraBold
            ),
            color = PresentColor
        )
        Text(
            text = "Redirecting to attendance...",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

// ── State Screen (Error / Retry) ──────────────────────────────────────────────

@Composable
private fun StateScreen(
    icon: ImageVector,
    iconTint: Color,
    iconBgColor: Color,
    // ✅ Explicit text color param for correct contrast on iconBgColor
    iconFgColor: Color,
    title: String,
    subtitle: String,
    actionLabel: String,
    onAction: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Box(
            modifier = Modifier
                .size(96.dp)
                .clip(CircleShape)
                // ✅ Solid M3 container token — works in both light/dark
                .background(iconBgColor),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                modifier = Modifier.size(44.dp),
                // ✅ Use iconTint (error color) for recognizable visual signal
                tint = iconTint
            )
        }

        Text(
            text = title,
            style = MaterialTheme.typography.titleLarge.copy(
                fontWeight = FontWeight.ExtraBold
            ),
            color = MaterialTheme.colorScheme.onBackground
        )

        Text(
            text = subtitle,
            style = MaterialTheme.typography.bodyMedium,
            textAlign = TextAlign.Center,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )

        Spacer(Modifier.height(4.dp))

        Button(
            onClick = onAction,
            modifier = Modifier
                .fillMaxWidth()
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

// ── Start Session Content ─────────────────────────────────────────────────────

@Composable
private fun StartSessionContent(
    latitude: Double,
    longitude: Double,
    accuracy: LocationHelper.LocationAccuracy?,
    gpsRadius: Int,
    isDark: Boolean,
    onStartSession: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(16.dp),
        modifier = Modifier.fillMaxWidth()
    ) {

        // ── Gradient Play Icon ────────────────────────────
        Box(
            modifier = Modifier
                .size(100.dp)
                .clip(CircleShape)
                .background(
                    Brush.linearGradient(
                        listOf(
                            MaterialTheme.colorScheme.primary,
                            MaterialTheme.colorScheme.tertiary
                        )
                    )
                ),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                Icons.Default.PlayArrow,
                contentDescription = null,
                modifier = Modifier.size(52.dp),
                tint = Color.White
            )
        }

        Text(
            text = "Ready to Start",
            style = MaterialTheme.typography.headlineSmall.copy(
                fontWeight = FontWeight.ExtraBold
            ),
            color = MaterialTheme.colorScheme.onBackground
        )

        Text(
            text = "Your location is captured.\nStudents within ${gpsRadius}m can mark attendance.",
            style = MaterialTheme.typography.bodyMedium,
            textAlign = TextAlign.Center,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )

        // ── Location Card ─────────────────────────────────
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(
                // ✅ Solid surfaceVariant — visible in dark mode
                containerColor = MaterialTheme.colorScheme.surfaceVariant
            ),
            elevation = CardDefaults.cardElevation(
                defaultElevation = if (isDark) 2.dp else 0.dp
            )
        ) {
            Row(
                modifier = Modifier.padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(46.dp)
                        .clip(CircleShape)
                        .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.12f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        Icons.Default.LocationOn,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(22.dp)
                    )
                }

                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Your Location",
                        style = MaterialTheme.typography.titleSmall.copy(
                            fontWeight = FontWeight.Bold
                        ),
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Text(
                        text = "Lat: ${String.format("%.6f", latitude)}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Text(
                        text = "Lon: ${String.format("%.6f", longitude)}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )

                    // ── Accuracy Indicator ────────────────
                    accuracy?.let { acc ->
                        Spacer(Modifier.height(4.dp))
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(5.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(8.dp)
                                    .clip(CircleShape)
                                    .background(acc.color())
                            )
                            Text(
                                text = when (acc) {
                                    LocationHelper.LocationAccuracy.EXCELLENT  -> "Excellent (±10m)"
                                    LocationHelper.LocationAccuracy.GOOD       -> "Good (±25m)"
                                    LocationHelper.LocationAccuracy.ACCEPTABLE -> "Acceptable (±50m)"
                                    LocationHelper.LocationAccuracy.POOR       -> "Poor accuracy"
                                },
                                style = MaterialTheme.typography.labelSmall.copy(
                                    fontWeight = FontWeight.Medium
                                ),
                                color = acc.color()
                            )
                        }
                    }
                }
            }
        }

        // ── Info Card ─────────────────────────────────────
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(
                // ✅ Solid primaryContainer — always readable
                containerColor = MaterialTheme.colorScheme.primaryContainer
            ),
            elevation = CardDefaults.cardElevation(0.dp)
        ) {
            Row(
                modifier = Modifier.padding(14.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Icon(
                    Icons.Outlined.Info,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.size(20.dp)
                )
                Text(
                    text = "Face verification + geo-fencing ensures only physically present students can mark attendance.",
                    style = MaterialTheme.typography.bodySmall,
                    // ✅ onPrimaryContainer — correct contrast pair
                    color = MaterialTheme.colorScheme.onPrimaryContainer
                )
            }
        }

        // ── Start Button ──────────────────────────────────
        Button(
            onClick = onStartSession,
            modifier = Modifier
                .fillMaxWidth()
                .height(56.dp),
            shape = RoundedCornerShape(14.dp)
        ) {
            Icon(
                Icons.Default.PlayArrow,
                contentDescription = null,
                modifier = Modifier.size(20.dp)
            )
            Spacer(Modifier.width(8.dp))
            Text(
                text = "Start Session",
                style = MaterialTheme.typography.titleMedium.copy(
                    fontWeight = FontWeight.Bold
                )
            )
        }
    }
}
