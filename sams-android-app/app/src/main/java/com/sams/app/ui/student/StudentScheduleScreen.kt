package com.sams.app.ui.student

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.*
import com.sams.app.ui.components.*
import com.sams.app.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StudentScheduleScreen(
    viewModel: StudentViewModel = hiltViewModel(),
    onBack: () -> Unit,
    onMarkAttendance: (Int, Double, Double, String) -> Unit
) {
    val uiState by viewModel.scheduleState.collectAsState()
    var selectedDay by remember { mutableStateOf("Monday") }
    
    val days = listOf("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday")
    
    LaunchedEffect(Unit) {
        viewModel.loadSchedule()
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Weekly Schedule", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background
                )
            )
        }
    ) { padding ->
        Column(modifier = Modifier.padding(padding)) {
            // Day Tabs
            ScrollableTabRow(
                selectedTabIndex = days.indexOf(selectedDay),
                containerColor = MaterialTheme.colorScheme.background,
                edgePadding = 16.dp,
                divider = {}
            ) {
                days.forEach { day ->
                    Tab(
                        selected = selectedDay == day,
                        onClick = { selectedDay = day },
                        text = {
                            Text(
                                text = day.take(3),
                                fontWeight = if (selectedDay == day) FontWeight.Bold else FontWeight.Normal
                            )
                        }
                    )
                }
            }
            
            HorizontalDivider()
            
            when (val state = uiState) {
                is StudentUiState.Loading -> LoadingScreen()
                is StudentUiState.Error -> ErrorScreen(
                    message = state.message,
                    onRetry = { viewModel.loadSchedule() }
                )
                is StudentUiState.Success -> {
                    val response = state.data as StudentScheduleResponse
                    val allSchedules = response.data?.schedules ?: emptyList()
                    val daySchedule = allSchedules.filter { it.dayOfWeek == selectedDay }
                    
                    if (daySchedule.isEmpty()) {
                        Box(
                            modifier = Modifier.fillMaxSize(),
                            contentAlignment = Alignment.Center
                        ) {
                            EmptyState(
                                icon = Icons.Outlined.EventBusy,
                                title = "No Classes",
                                description = "You don't have any classes on $selectedDay"
                            )
                        }
                    } else {
                        LazyColumn(
                            modifier = Modifier.fillMaxSize(),
                            contentPadding = PaddingValues(16.dp),
                            verticalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            items(daySchedule) { schedule ->
                                ScheduleItemCard(
                                    schedule = schedule,
                                    onMarkAttendance = {
                                        onMarkAttendance(
                                            schedule.scheduleId, 
                                            schedule.teacherLatitude, 
                                            schedule.teacherLongitude, 
                                            schedule.subjectName
                                        )
                                    }
                                )
                            }
                        }
                    }
                }
                else -> {}
            }
        }
    }
}

@Composable
private fun ScheduleItemCard(
    schedule: ScheduleItem,
    onMarkAttendance: () -> Unit
) {
    SAMSCard(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Time Column
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.width(70.dp)
            ) {
                Text(
                    text = schedule.startTime.take(5),
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
                Icon(
                    imageVector = Icons.Default.ArrowDownward,
                    contentDescription = null,
                    modifier = Modifier.size(16.dp),
                    tint = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Text(
                    text = schedule.endTime.take(5),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            
            // Divider
            Box(
                modifier = Modifier
                    .width(3.dp)
                    .height(60.dp)
                    .clip(RoundedCornerShape(1.5.dp))
                    .background(
                        when {
                            schedule.attendanceMarked -> PrimaryBlue
                            schedule.sessionActive -> SuccessGreen
                            schedule.isActive -> WarningOrange
                            else -> MaterialTheme.colorScheme.outline
                        }
                    )
            )
            
            Spacer(modifier = Modifier.width(16.dp))
            
            // Content
            Column(modifier = Modifier.weight(1f)) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Text(
                        text = schedule.subjectName,
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.SemiBold
                    )
                    when {
                        schedule.attendanceMarked -> StatusBadge(status = "Present")
                        schedule.sessionActive -> StatusBadge(status = "Ongoing")
                        schedule.isActive -> StatusBadge(status = "Not Started", isWarning = true)
                    }
                }
                
                Spacer(modifier = Modifier.height(4.dp))
                
                Text(
                    text = schedule.subjectCode,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.primary
                )
                
                Text(
                    text = schedule.teacherName,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                
                schedule.classroom?.let {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.padding(top = 4.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.Room,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = it,
                            style = MaterialTheme.typography.labelSmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
                
                // Show message when session not started
                if (schedule.isActive && !schedule.sessionActive && !schedule.attendanceMarked) {
                    Spacer(modifier = Modifier.height(8.dp))
                    Row(
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.Info,
                            contentDescription = null,
                            modifier = Modifier.size(14.dp),
                            tint = WarningOrange
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = "Waiting for teacher to start class",
                            style = MaterialTheme.typography.labelSmall,
                            color = WarningOrange
                        )
                    }
                }
            }
            
            // Mark Attendance Button - Only show when session is active
            if (schedule.sessionActive && !schedule.attendanceMarked) {
                FilledIconButton(
                    onClick = onMarkAttendance,
                    colors = IconButtonDefaults.filledIconButtonColors(
                        containerColor = SuccessGreen
                    )
                ) {
                    Icon(
                        imageVector = Icons.Default.Check,
                        contentDescription = "Mark Attendance"
                    )
                }
            }
        }
    }
}
