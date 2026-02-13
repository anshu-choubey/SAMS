package com.sams.app.ui.teacher

import android.Manifest
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.accompanist.permissions.ExperimentalPermissionsApi
import com.google.accompanist.permissions.isGranted
import com.google.accompanist.permissions.rememberPermissionState
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import com.sams.app.data.models.StartSessionData
import com.sams.app.ui.theme.PresentColor
import kotlinx.coroutines.tasks.await

@OptIn(ExperimentalMaterial3Api::class, ExperimentalPermissionsApi::class)
@Composable
fun StartClassScreen(
    scheduleId: Int,
    viewModel: TeacherViewModel = hiltViewModel(),
    onBack: () -> Unit,
    onSessionStarted: (Int) -> Unit
) {
    val uiState by viewModel.sessionState.collectAsState()
    val context = LocalContext.current
    
    val locationPermissionState = rememberPermissionState(Manifest.permission.ACCESS_FINE_LOCATION)
    
    var currentLocation by remember { mutableStateOf<Pair<Double, Double>?>(null) }
    var locationError by remember { mutableStateOf<String?>(null) }
    var isFetchingLocation by remember { mutableStateOf(false) }
    
    LaunchedEffect(uiState) {
        if (uiState is TeacherUiState.Success) {
            val response = (uiState as TeacherUiState.Success).data as StartSessionData
            kotlinx.coroutines.delay(1000)
            viewModel.resetSessionState()
            onSessionStarted(scheduleId)
        }
    }
    
    // Fetch location when permission granted
    LaunchedEffect(locationPermissionState.status.isGranted) {
        if (locationPermissionState.status.isGranted) {
            isFetchingLocation = true
            try {
                val fusedLocationClient = LocationServices.getFusedLocationProviderClient(context)
                val location = fusedLocationClient.getCurrentLocation(
                    Priority.PRIORITY_HIGH_ACCURACY,
                    null
                ).await()
                location?.let {
                    currentLocation = Pair(it.latitude, it.longitude)
                } ?: run {
                    locationError = "Unable to get current location"
                }
            } catch (e: Exception) {
                locationError = e.message ?: "Location error"
            } finally {
                isFetchingLocation = false
            }
        }
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Start Class") },
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
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            when {
                !locationPermissionState.status.isGranted -> {
                    Icon(
                        Icons.Default.LocationOn,
                        contentDescription = null,
                        modifier = Modifier.size(72.dp),
                        tint = MaterialTheme.colorScheme.primary
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    Text(
                        "Location Required",
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        "We need your location so students can verify they're in the classroom to mark attendance.",
                        style = MaterialTheme.typography.bodyMedium,
                        textAlign = TextAlign.Center
                    )
                    Spacer(modifier = Modifier.height(24.dp))
                    Button(onClick = { locationPermissionState.launchPermissionRequest() }) {
                        Text("Grant Permission")
                    }
                }
                isFetchingLocation -> {
                    CircularProgressIndicator()
                    Spacer(modifier = Modifier.height(16.dp))
                    Text("Getting your location...")
                }
                locationError != null -> {
                    Icon(
                        Icons.Default.Error,
                        contentDescription = null,
                        modifier = Modifier.size(72.dp),
                        tint = MaterialTheme.colorScheme.error
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    Text(
                        "Location Error",
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(locationError!!)
                    Spacer(modifier = Modifier.height(24.dp))
                    Button(onClick = {
                        locationError = null
                        isFetchingLocation = true
                    }) {
                        Text("Retry")
                    }
                }
                uiState is TeacherUiState.Loading -> {
                    CircularProgressIndicator()
                    Spacer(modifier = Modifier.height(16.dp))
                    Text("Starting session...")
                }
                uiState is TeacherUiState.Success -> {
                    Icon(
                        Icons.Default.CheckCircle,
                        contentDescription = null,
                        modifier = Modifier.size(96.dp),
                        tint = PresentColor
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    Text(
                        "Session Started!",
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold
                    )
                }
                uiState is TeacherUiState.Error -> {
                    Icon(
                        Icons.Default.Error,
                        contentDescription = null,
                        modifier = Modifier.size(72.dp),
                        tint = MaterialTheme.colorScheme.error
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    Text(
                        (uiState as TeacherUiState.Error).message,
                        color = MaterialTheme.colorScheme.error
                    )
                    Spacer(modifier = Modifier.height(24.dp))
                    Button(onClick = { viewModel.resetSessionState() }) {
                        Text("Try Again")
                    }
                }
                currentLocation != null -> {
                    StartSessionContent(
                        latitude = currentLocation!!.first,
                        longitude = currentLocation!!.second,
                        onStartSession = {
                            viewModel.startSession(
                                scheduleId = scheduleId,
                                latitude = currentLocation!!.first,
                                longitude = currentLocation!!.second
                            )
                        }
                    )
                }
            }
        }
    }
}

@Composable
private fun StartSessionContent(
    latitude: Double,
    longitude: Double,
    onStartSession: () -> Unit
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Icon(
            Icons.Default.PlayCircle,
            contentDescription = null,
            modifier = Modifier.size(96.dp),
            tint = MaterialTheme.colorScheme.primary
        )
        
        Spacer(modifier = Modifier.height(24.dp))
        
        Text(
            "Ready to Start",
            style = MaterialTheme.typography.headlineSmall,
            fontWeight = FontWeight.Bold
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.surfaceVariant
            )
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        Icons.Default.LocationOn,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        "Your Location",
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.Bold
                    )
                }
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    "Lat: ${String.format("%.6f", latitude)}",
                    style = MaterialTheme.typography.bodySmall
                )
                Text(
                    "Lon: ${String.format("%.6f", longitude)}",
                    style = MaterialTheme.typography.bodySmall
                )
            }
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer.copy(alpha = 0.3f)
            )
        ) {
            Row(
                modifier = Modifier.padding(16.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    Icons.Default.Info,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary
                )
                Spacer(modifier = Modifier.width(12.dp))
                Text(
                    "Students within 50m of your location can mark their attendance using face verification.",
                    style = MaterialTheme.typography.bodySmall
                )
            }
        }
        
        Spacer(modifier = Modifier.height(32.dp))
        
        Button(
            onClick = onStartSession,
            modifier = Modifier
                .fillMaxWidth()
                .height(56.dp)
        ) {
            Icon(Icons.Default.PlayArrow, contentDescription = null)
            Spacer(modifier = Modifier.width(8.dp))
            Text("Start Session", style = MaterialTheme.typography.titleMedium)
        }
    }
}
