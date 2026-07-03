package com.sams.app.ui.teacher

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.AttendanceCheckPoint
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AttendanceCheckScreen(
    sessionId: Int,
    viewModel: TeacherViewModel = hiltViewModel(),
    onNavigateBack: () -> Unit
) {
    val checkTriggerState by viewModel.checkTriggerState.collectAsState()
    val finalizeState by viewModel.finalizeState.collectAsState()
    
    var showCheckDialog by remember { mutableStateOf(false) }
    var showFinalizeDialog by remember { mutableStateOf(false) }
    var windowMinutes by remember { mutableStateOf(5) }
    
    // Handle trigger success
    LaunchedEffect(checkTriggerState) {
        if (checkTriggerState is TeacherUiState.Success) {
            showCheckDialog = false
            viewModel.resetCheckTriggerState()
        }
    }
    
    // Handle finalize success
    LaunchedEffect(finalizeState) {
        if (finalizeState is TeacherUiState.Success) {
            showFinalizeDialog = false
            // Navigate back after successful finalization
            kotlinx.coroutines.delay(1500)
            onNavigateBack()
        }
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Multi-Check Attendance") },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Default.ArrowBack, "Back")
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
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Info Card
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer
                )
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp)
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Info, contentDescription = null)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            "Multi-Check Attendance",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(
                        "Trigger 2-3 random attendance checks during class to ensure fair attendance marking and avoid GPS issues.",
                        style = MaterialTheme.typography.bodyMedium
                    )
                }
            }
            
            // Trigger Check Button
            Card {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                "Trigger Attendance Check",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold
                            )
                            Text(
                                "Students will have a time window to respond",
                                style = MaterialTheme.typography.bodySmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    Button(
                        onClick = { showCheckDialog = true },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = checkTriggerState !is TeacherUiState.Loading
                    ) {
                        if (checkTriggerState is TeacherUiState.Loading) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(20.dp),
                                color = MaterialTheme.colorScheme.onPrimary
                            )
                        } else {
                            Icon(Icons.Default.Notifications, contentDescription = null)
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Trigger Check Now")
                        }
                    }
                    
                    // Show success message
                    if (checkTriggerState is TeacherUiState.Success) {
                        Spacer(modifier = Modifier.height(8.dp))
                        val data = (checkTriggerState as TeacherUiState.Success).data as AttendanceCheckPoint
                        AssistChip(
                            onClick = {},
                            label = { Text("Check #${data.checkNumber} triggered!") },
                            leadingIcon = {
                                Icon(Icons.Default.CheckCircle, contentDescription = null, modifier = Modifier.size(16.dp))
                            },
                            colors = AssistChipDefaults.assistChipColors(
                                containerColor = MaterialTheme.colorScheme.primaryContainer
                            )
                        )
                    }
                }
            }
            
            // Finalize Attendance Button
            Card {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp)
                ) {
                    Text(
                        "Finalize Attendance",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        "Calculate final attendance based on all checks",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    Button(
                        onClick = { showFinalizeDialog = true },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = finalizeState !is TeacherUiState.Loading,
                        colors = ButtonDefaults.buttonColors(
                            containerColor = MaterialTheme.colorScheme.tertiary
                        )
                    ) {
                        if (finalizeState is TeacherUiState.Loading) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(20.dp),
                                color = MaterialTheme.colorScheme.onTertiary
                            )
                        } else {
                            Icon(Icons.Default.Done, contentDescription = null)
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Finalize Attendance")
                        }
                    }
                }
            }
            
            // Error display
            if (checkTriggerState is TeacherUiState.Error) {
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer)) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.Warning, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            (checkTriggerState as TeacherUiState.Error).message,
                            color = MaterialTheme.colorScheme.error
                        )
                    }
                }
            }
            
            if (finalizeState is TeacherUiState.Error) {
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer)) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.Warning, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            (finalizeState as TeacherUiState.Error).message,
                            color = MaterialTheme.colorScheme.error
                        )
                    }
                }
            }
        }
    }
    
    // Trigger Check Dialog
    if (showCheckDialog) {
        AlertDialog(
            onDismissRequest = { showCheckDialog = false },
            title = { Text("Trigger Attendance Check") },
            text = {
                Column {
                    Text("Set the response window for students:")
                    Spacer(modifier = Modifier.height(16.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("Window: ")
                        Slider(
                            value = windowMinutes.toFloat(),
                            onValueChange = { windowMinutes = it.toInt() },
                            valueRange = 3f..10f,
                            steps = 6,
                            modifier = Modifier.weight(1f)
                        )
                        Text(" ${windowMinutes}min")
                    }
                }
            },
            confirmButton = {
                Button(onClick = {
                    viewModel.triggerAttendanceCheck(sessionId, windowMinutes)
                }) {
                    Text("Trigger")
                }
            },
            dismissButton = {
                TextButton(onClick = { showCheckDialog = false }) {
                    Text("Cancel")
                }
            }
        )
    }
    
    // Finalize Confirmation Dialog
    if (showFinalizeDialog) {
        AlertDialog(
            onDismissRequest = { showFinalizeDialog = false },
            icon = { Icon(Icons.Default.Done, contentDescription = null) },
            title = { Text("Finalize Attendance?") },
            text = {
                Text("This will calculate final attendance based on all triggered checks. Students who passed the required checks will be marked present.")
            },
            confirmButton = {
                Button(onClick = {
                    viewModel.finalizeAttendance(sessionId)
                }) {
                    Text("Finalize")
                }
            },
            dismissButton = {
                TextButton(onClick = { showFinalizeDialog = false }) {
                    Text("Cancel")
                }
            }
        )
    }
}
