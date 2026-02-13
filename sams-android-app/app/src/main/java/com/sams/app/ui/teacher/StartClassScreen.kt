package com.sams.app.ui.teacher

import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
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
import androidx.core.content.ContextCompat
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.android.gms.location.LocationServices
import com.sams.app.ui.components.*
import com.sams.app.ui.theme.*
import kotlinx.coroutines.launch

@Suppress("OPT_IN_USAGE")

private enum class StartClassStep {
    INFO,
    LOCATION,
    CONFIRM,
    SUCCESS
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StartClassScreen(
    scheduleId: Int,
    viewModel: TeacherViewModel = hiltViewModel(),
    onNavigateBack: () -> Unit,
    onViewAttendance: () -> Unit
) {
    val sessionState by viewModel.sessionState.collectAsState()
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    
    var currentStep by remember { mutableStateOf(StartClassStep.INFO) }
    var locationPermissionGranted by remember { mutableStateOf(false) }
    var currentLocation by remember { mutableStateOf<Pair<Double, Double>?>(null) }
    var locationError by remember { mutableStateOf<String?>(null) }
    var isLoadingLocation by remember { mutableStateOf(false) }
    
    // Check permission on launch
    LaunchedEffect(Unit) {
        locationPermissionGranted = ContextCompat.checkSelfPermission(
            context,
            Manifest.permission.ACCESS_FINE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED
    }
    
    val locationPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        locationPermissionGranted = permissions[Manifest.permission.ACCESS_FINE_LOCATION] == true ||
                permissions[Manifest.permission.ACCESS_COARSE_LOCATION] == true
        if (!locationPermissionGranted) {
            locationError = "Location permission is required to start class"
        }
    }
    
    // Get location
    fun fetchLocation() {
        isLoadingLocation = true
        locationError = null
        
        val fusedLocationClient = LocationServices.getFusedLocationProviderClient(context)
        try {
            fusedLocationClient.lastLocation.addOnSuccessListener { location ->
                isLoadingLocation = false
                if (location != null) {
                    currentLocation = Pair(location.latitude, location.longitude)
                    currentStep = StartClassStep.CONFIRM
                } else {
                    locationError = "Unable to get location. Please try again."
                }
            }.addOnFailureListener {
                isLoadingLocation = false
                locationError = "Location error: ${it.message}"
            }
        } catch (e: SecurityException) {
            isLoadingLocation = false
            locationError = "Location permission denied"
        }
    }
    
    // Handle session state changes
    LaunchedEffect(sessionState) {
        when (sessionState) {
            is TeacherUiState.SessionSuccess -> {
                currentStep = StartClassStep.SUCCESS
            }
            is TeacherUiState.Error -> {
                // Show error but stay on confirm screen
            }
            else -> {}
        }
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Start Class", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = {
                        viewModel.resetSessionState()
                        onNavigateBack()
                    }) {
                        Icon(Icons.Default.Close, contentDescription = "Close")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background
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
            // Step Indicator
            StepIndicator(currentStep = currentStep)
            
            Spacer(modifier = Modifier.height(32.dp))
            
            // Content based on step
            AnimatedContent(
                targetState = currentStep,
                transitionSpec = {
                    slideInHorizontally { it } + fadeIn() togetherWith
                            slideOutHorizontally { -it } + fadeOut()
                },
                modifier = Modifier.weight(1f)
            ) { step ->
                when (step) {
                    StartClassStep.INFO -> InfoStep(
                        scheduleId = scheduleId,
                        onContinue = {
                            if (locationPermissionGranted) {
                                currentStep = StartClassStep.LOCATION
                            } else {
                                locationPermissionLauncher.launch(
                                    arrayOf(
                                        Manifest.permission.ACCESS_FINE_LOCATION,
                                        Manifest.permission.ACCESS_COARSE_LOCATION
                                    )
                                )
                            }
                        }
                    )
                    StartClassStep.LOCATION -> LocationStep(
                        isLoading = isLoadingLocation,
                        error = locationError,
                        onFetchLocation = { fetchLocation() }
                    )
                    StartClassStep.CONFIRM -> ConfirmStep(
                        scheduleId = scheduleId,
                        location = currentLocation,
                        isLoading = sessionState is TeacherUiState.Loading,
                        error = (sessionState as? TeacherUiState.Error)?.message,
                        onConfirm = {
                            currentLocation?.let { (lat, lng) ->
                                viewModel.startSession(scheduleId, lat, lng)
                            }
                        },
                        onBack = { currentStep = StartClassStep.LOCATION }
                    )
                    StartClassStep.SUCCESS -> SuccessStep(
                        onViewAttendance = {
                            viewModel.resetSessionState()
                            onViewAttendance()
                        },
                        onDone = {
                            viewModel.resetSessionState()
                            onNavigateBack()
                        }
                    )
                }
            }
        }
    }
}

@Composable
private fun StepIndicator(currentStep: StartClassStep) {
    val steps = listOf("Info", "Location", "Confirm", "Done")
    val currentIndex = currentStep.ordinal
    
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        steps.forEachIndexed { index, label ->
            StepDot(
                label = label,
                stepNumber = index + 1,
                isCompleted = index < currentIndex,
                isActive = index == currentIndex
            )
            if (index < steps.lastIndex) {
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .height(2.dp)
                        .padding(horizontal = 4.dp)
                        .background(
                            if (index < currentIndex) SuccessGreen
                            else MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
                        )
                )
            }
        }
    }
}

@Composable
private fun StepDot(
    label: String,
    stepNumber: Int,
    isCompleted: Boolean,
    isActive: Boolean
) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Box(
            modifier = Modifier
                .size(32.dp)
                .clip(CircleShape)
                .background(
                    when {
                        isCompleted -> SuccessGreen
                        isActive -> PrimaryBlue
                        else -> MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
                    }
                ),
            contentAlignment = Alignment.Center
        ) {
            if (isCompleted) {
                Icon(
                    imageVector = Icons.Default.Check,
                    contentDescription = null,
                    tint = Color.White,
                    modifier = Modifier.size(18.dp)
                )
            } else {
                Text(
                    text = stepNumber.toString(),
                    style = MaterialTheme.typography.labelMedium,
                    fontWeight = FontWeight.Bold,
                    color = if (isActive) Color.White else MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
        Spacer(modifier = Modifier.height(4.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            color = if (isActive || isCompleted)
                MaterialTheme.colorScheme.onSurface
            else
                MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

@Composable
private fun InfoStep(
    scheduleId: Int,
    onContinue: () -> Unit
) {
    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Box(
            modifier = Modifier
                .size(100.dp)
                .clip(CircleShape)
                .background(PrimaryBlueContainer),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Default.PlayCircle,
                contentDescription = null,
                modifier = Modifier.size(56.dp),
                tint = PrimaryBlue
            )
        }
        
        Spacer(modifier = Modifier.height(24.dp))
        
        Text(
            text = "Ready to Start Class?",
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            textAlign = TextAlign.Center
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Text(
            text = "We'll verify your location to enable\nstudent attendance marking",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center
        )
        
        Spacer(modifier = Modifier.height(32.dp))
        
        SAMSCard(modifier = Modifier.fillMaxWidth()) {
            Column(modifier = Modifier.padding(16.dp)) {
                InfoRow(
                    icon = Icons.Outlined.LocationOn,
                    title = "Location Verification",
                    description = "Your location will be set as the class location"
                )
                Spacer(modifier = Modifier.height(12.dp))
                InfoRow(
                    icon = Icons.Outlined.People,
                    title = "Student Attendance",
                    description = "Students can mark attendance after class starts"
                )
                Spacer(modifier = Modifier.height(12.dp))
                InfoRow(
                    icon = Icons.Outlined.Timer,
                    title = "Session Duration",
                    description = "Session remains active until you end it"
                )
            }
        }
        
        Spacer(modifier = Modifier.weight(1f))
        
        PrimaryButton(
            text = "Continue",
            onClick = onContinue,
            modifier = Modifier.fillMaxWidth()
        )
    }
}

@Composable
private fun InfoRow(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    title: String,
    description: String
) {
    Row(verticalAlignment = Alignment.Top) {
        Box(
            modifier = Modifier
                .size(36.dp)
                .clip(RoundedCornerShape(8.dp))
                .background(PrimaryBlueContainer),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                modifier = Modifier.size(20.dp),
                tint = PrimaryBlue
            )
        }
        Spacer(modifier = Modifier.width(12.dp))
        Column {
            Text(
                text = title,
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.Medium
            )
            Text(
                text = description,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}

@Composable
private fun LocationStep(
    isLoading: Boolean,
    error: String?,
    onFetchLocation: () -> Unit
) {
    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        if (isLoading) {
            CircularProgressIndicator(
                modifier = Modifier.size(64.dp),
                strokeWidth = 4.dp
            )
            Spacer(modifier = Modifier.height(24.dp))
            Text(
                text = "Getting your location...",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Medium
            )
        } else {
            Box(
                modifier = Modifier
                    .size(100.dp)
                    .clip(CircleShape)
                    .background(SecondaryTealContainer),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.MyLocation,
                    contentDescription = null,
                    modifier = Modifier.size(56.dp),
                    tint = SecondaryTeal
                )
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Text(
                text = "Location Verification",
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = "Tap below to fetch your current location",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center
            )
            
            error?.let {
                Spacer(modifier = Modifier.height(16.dp))
                SAMSCard(
                    modifier = Modifier.fillMaxWidth()) {
                    Row(
                        modifier = Modifier.padding(12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            Icons.Default.ErrorOutline,
                            contentDescription = null,
                            tint = ErrorRed
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = it,
                            style = MaterialTheme.typography.bodySmall,
                            color = ErrorRed
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.weight(1f))
            
            PrimaryButton(
                text = "Get Location",
                onClick = onFetchLocation,
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}

@Composable
private fun ConfirmStep(
    scheduleId: Int,
    location: Pair<Double, Double>?,
    isLoading: Boolean,
    error: String?,
    onConfirm: () -> Unit,
    onBack: () -> Unit
) {
    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Box(
            modifier = Modifier
                .size(80.dp)
                .clip(CircleShape)
                .background(SuccessGreenContainer),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Default.CheckCircle,
                contentDescription = null,
                modifier = Modifier.size(48.dp),
                tint = SuccessGreen
            )
        }
        
        Spacer(modifier = Modifier.height(24.dp))
        
        Text(
            text = "Location Verified!",
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold,
            textAlign = TextAlign.Center
        )
        
        Spacer(modifier = Modifier.height(24.dp))
        
        SAMSCard(modifier = Modifier.fillMaxWidth()) {
            Column(
                modifier = Modifier.padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Icon(
                    imageVector = Icons.Outlined.LocationOn,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary
                )
                Spacer(modifier = Modifier.height(8.dp))
                location?.let { (lat, lng) ->
                    Text(
                        text = "Lat: ${String.format("%.6f", lat)}",
                        style = MaterialTheme.typography.bodyMedium
                    )
                    Text(
                        text = "Lng: ${String.format("%.6f", lng)}",
                        style = MaterialTheme.typography.bodyMedium
                    )
                }
            }
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        
        SAMSCard(modifier = Modifier.fillMaxWidth()) {
            Row(
                modifier = Modifier.padding(16.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    Icons.Outlined.Info,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary
                )
                Spacer(modifier = Modifier.width(12.dp))
                Text(
                    text = "Students within 100m of this location can mark their attendance",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
        }
        
        error?.let {
            Spacer(modifier = Modifier.height(16.dp))
            SAMSCard(
                modifier = Modifier.fillMaxWidth()) {
                Row(
                    modifier = Modifier.padding(12.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        Icons.Default.ErrorOutline,
                        contentDescription = null,
                        tint = ErrorRed
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = it,
                        style = MaterialTheme.typography.bodySmall,
                        color = ErrorRed
                    )
                }
            }
        }
        
        Spacer(modifier = Modifier.weight(1f))
        
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            SecondaryButton(
                text = "Back",
                onClick = onBack,
                modifier = Modifier.weight(1f),
                enabled = !isLoading
            )
            PrimaryButton(
                text = if (isLoading) "Starting..." else "Start Class",
                onClick = onConfirm,
                modifier = Modifier.weight(1f),
                enabled = !isLoading
            )
        }
    }
}

@Composable
private fun SuccessStep(
    onViewAttendance: () -> Unit,
    onDone: () -> Unit
) {
    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
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
                modifier = Modifier.size(72.dp),
                tint = SuccessGreen
            )
        }
        
        Spacer(modifier = Modifier.height(32.dp))
        
        Text(
            text = "Class Started!",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            textAlign = TextAlign.Center
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Text(
            text = "Students can now mark their attendance",
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center
        )
        
        Spacer(modifier = Modifier.weight(1f))
        
        PrimaryButton(
            text = "View Attendance",
            onClick = onViewAttendance,
            modifier = Modifier.fillMaxWidth()
        )
        
        Spacer(modifier = Modifier.height(12.dp))
        
        TextButton(
            onClick = onDone,
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Back to Dashboard")
        }
    }
}
