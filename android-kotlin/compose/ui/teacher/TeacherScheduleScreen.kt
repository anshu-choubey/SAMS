package com.sams.app.ui.teacher

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.TeacherScheduleResponse
import com.sams.app.ui.theme.PresentColor

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TeacherScheduleScreen(
    viewModel: TeacherViewModel = hiltViewModel(),
    onBack: () -> Unit,
    onStartClass: (Int) -> Unit,
    onViewAttendance: (Int) -> Unit
) {
    val uiState by viewModel.scheduleState.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadSchedule()
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Weekly Schedule") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                }
            )
        }
    ) { padding ->
        when (val state = uiState) {
            is TeacherUiState.Loading -> {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator()
                }
            }
            is TeacherUiState.Success -> {
                val data = state.data as TeacherScheduleResponse
                LazyColumn(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(padding),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    data.schedules.forEach { (day, schedules) ->
                        item {
                            Text(
                                day,
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                                modifier = Modifier.padding(vertical = 8.dp)
                            )
                        }
                        
                        if (schedules.isEmpty()) {
                            item {
                                Card(
                                    modifier = Modifier.fillMaxWidth(),
                                    colors = CardDefaults.cardColors(
                                        containerColor = MaterialTheme.colorScheme.surfaceVariant
                                    )
                                ) {
                                    Text(
                                        "No classes",
                                        modifier = Modifier.padding(16.dp),
                                        color = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                }
                            }
                        } else {
                            items(schedules) { schedule ->
                                Card(modifier = Modifier.fillMaxWidth()) {
                                    Row(
                                        modifier = Modifier.padding(16.dp),
                                        verticalAlignment = Alignment.CenterVertically
                                    ) {
                                        Column(
                                            horizontalAlignment = Alignment.CenterHorizontally,
                                            modifier = Modifier.width(70.dp)
                                        ) {
                                            Text(
                                                schedule.startTime.take(5),
                                                style = MaterialTheme.typography.titleSmall,
                                                fontWeight = FontWeight.Bold
                                            )
                                            Text(
                                                "to",
                                                style = MaterialTheme.typography.bodySmall,
                                                color = MaterialTheme.colorScheme.onSurfaceVariant
                                            )
                                            Text(
                                                schedule.endTime.take(5),
                                                style = MaterialTheme.typography.titleSmall
                                            )
                                        }
                                        
                                        Spacer(modifier = Modifier.width(16.dp))
                                        
                                        HorizontalDivider(
                                            modifier = Modifier
                                                .width(1.dp)
                                                .height(50.dp)
                                        )
                                        
                                        Spacer(modifier = Modifier.width(16.dp))
                                        
                                        Column(modifier = Modifier.weight(1f)) {
                                            Text(
                                                schedule.subjectName,
                                                style = MaterialTheme.typography.titleSmall,
                                                fontWeight = FontWeight.Bold
                                            )
                                            Text(
                                                schedule.subjectCode,
                                                style = MaterialTheme.typography.bodySmall,
                                                color = MaterialTheme.colorScheme.primary
                                            )
                                            Text(
                                                "Sem ${schedule.semester} - Sec ${schedule.section}",
                                                style = MaterialTheme.typography.bodySmall
                                            )
                                            schedule.classroom?.let {
                                                Text(
                                                    "Room: $it",
                                                    style = MaterialTheme.typography.bodySmall,
                                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                                )
                                            }
                                        }
                                        
                                        if (schedule.sessionActive) {
                                            Icon(
                                                Icons.Default.CheckCircle,
                                                contentDescription = "Active",
                                                tint = PresentColor
                                            )
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            is TeacherUiState.Error -> {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(state.message, color = MaterialTheme.colorScheme.error)
                        Spacer(modifier = Modifier.height(16.dp))
                        Button(onClick = { viewModel.loadSchedule() }) {
                            Text("Retry")
                        }
                    }
                }
            }
            else -> {}
        }
    }
}
