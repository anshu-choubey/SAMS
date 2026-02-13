package com.sams.app.ui.teacher

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.material3.HorizontalDivider
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.sams.app.data.models.*
import com.sams.app.ui.components.*
import com.sams.app.ui.theme.*
import java.time.DayOfWeek
import java.time.LocalDate
import java.time.format.TextStyle
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TeacherScheduleScreen(
    viewModel: TeacherViewModel = hiltViewModel(),
    onNavigateBack: () -> Unit,
    onStartClass: (Int) -> Unit,
    onViewAttendance: (Int) -> Unit
) {
    val uiState by viewModel.scheduleState.collectAsState()
    var selectedDay by remember { mutableStateOf(LocalDate.now().dayOfWeek) }
    
    LaunchedEffect(Unit) {
        viewModel.loadSchedule()
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { 
                    Text(
                        "My Schedule",
                        fontWeight = FontWeight.Bold
                    )
                },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
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
        ) {
            // Week Day Selector
            WeekDaySelector(
                selectedDay = selectedDay,
                onDaySelected = { selectedDay = it }
            )
            
            Divider(modifier = Modifier.padding(vertical = 8.dp))
            
            when (val state = uiState) {
                is TeacherUiState.Loading -> LoadingScreen()
                is TeacherUiState.Error -> ErrorScreen(
                    message = state.message,
                    onRetry = { viewModel.loadSchedule() }
                )
                is TeacherUiState.ScheduleSuccess -> {
                    val dayName = selectedDay.getDisplayName(TextStyle.FULL, Locale.ENGLISH)
                    val daySchedule = state.schedule.filter { 
                        it.dayOfWeek.equals(dayName, ignoreCase = true)
                    }
                    ScheduleContent(
                        schedule = daySchedule,
                        dayName = dayName,
                        onStartClass = onStartClass,
                        onViewAttendance = onViewAttendance
                    )
                }
                else -> {}
            }
        }
    }
}

@Composable
private fun WeekDaySelector(
    selectedDay: DayOfWeek,
    onDaySelected: (DayOfWeek) -> Unit
) {
    val days = listOf(
        DayOfWeek.MONDAY,
        DayOfWeek.TUESDAY,
        DayOfWeek.WEDNESDAY,
        DayOfWeek.THURSDAY,
        DayOfWeek.FRIDAY,
        DayOfWeek.SATURDAY
    )
    val today = LocalDate.now().dayOfWeek
    
    LazyRow(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        items(days) { day ->
            DayChip(
                day = day,
                isSelected = day == selectedDay,
                isToday = day == today,
                onClick = { onDaySelected(day) }
            )
        }
    }
}

@Composable
private fun DayChip(
    day: DayOfWeek,
    isSelected: Boolean,
    isToday: Boolean,
    onClick: () -> Unit
) {
    val dayShort = day.getDisplayName(TextStyle.SHORT, Locale.ENGLISH)
    
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(12.dp),
        color = when {
            isSelected -> PrimaryBlue
            else -> MaterialTheme.colorScheme.surfaceVariant
        },
        modifier = Modifier.width(56.dp)
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(vertical = 12.dp, horizontal = 8.dp)
        ) {
            Text(
                text = dayShort,
                style = MaterialTheme.typography.labelLarge,
                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                color = when {
                    isSelected -> Color.White
                    else -> MaterialTheme.colorScheme.onSurfaceVariant
                }
            )
            if (isToday) {
                Spacer(modifier = Modifier.height(4.dp))
                Box(
                    modifier = Modifier
                        .size(6.dp)
                        .clip(CircleShape)
                        .background(
                            if (isSelected) Color.White else PrimaryBlue
                        )
                )
            }
        }
    }
}

@Composable
private fun ScheduleContent(
    schedule: List<TeacherScheduleItem>,
    dayName: String,
    onStartClass: (Int) -> Unit,
    onViewAttendance: (Int) -> Unit
) {
    if (schedule.isEmpty()) {
        EmptyState(
            icon = Icons.Outlined.EventBusy,
            title = "No Classes on $dayName",
            description = "You don't have any scheduled classes for this day"
        )
    } else {
        LazyColumn(
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
                Text(
                    text = "${schedule.size} Classes",
                    style = MaterialTheme.typography.titleSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant

                )
            }
            
            items(schedule) { item ->
                ScheduleDetailCard(
                    schedule = item,
                    onStartClass = { onStartClass(item.scheduleId) },
                    onViewAttendance = { onViewAttendance(item.scheduleId) }
                )
            }
            
            item { Spacer(modifier = Modifier.height(16.dp)) }
        }
    }
}

@Composable
private fun ScheduleDetailCard(
    schedule: TeacherScheduleItem,
    onStartClass: () -> Unit,
    onViewAttendance: () -> Unit
) {
    SAMSCard(modifier = Modifier.fillMaxWidth()) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = schedule.subjectCode,
                        style = MaterialTheme.typography.labelMedium,
                        color = MaterialTheme.colorScheme.primary,
                        fontWeight = FontWeight.SemiBold
                    )
                    Text(
                        text = schedule.subjectName,
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold
                    )
                }
                
                when {
                    schedule.sessionActive -> StatusBadge(status = "Ongoing")
                    schedule.isCompleted -> StatusBadge(status = "Completed")
                    schedule.isStartable -> StatusBadge(status = "Ready")
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Details Row
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                DetailItem(
                    icon = Icons.Outlined.AccessTime,
                    text = "${schedule.startTime.take(5)} - ${schedule.endTime.take(5)}"
                )
                DetailItem(
                    icon = Icons.Outlined.School,
                    text = "Sem ${schedule.semester} • ${schedule.section}"
                )
            }
            
            schedule.classroom?.let { room ->
                Spacer(modifier = Modifier.height(8.dp))
                DetailItem(
                    icon = Icons.Outlined.Room,
                    text = room
                )
            }
            
            // Actions
            if (schedule.sessionActive || schedule.isStartable || schedule.isCompleted) {
                Spacer(modifier = Modifier.height(16.dp))
                
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.End,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    when {
                        schedule.sessionActive -> {
                            OutlinedButton(
                                onClick = onViewAttendance,
                                shape = RoundedCornerShape(10.dp)
                            ) {
                                Icon(
                                    Icons.Outlined.People,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp)
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Text("View Attendance")
                            }
                        }
                        schedule.isStartable -> {
                            Button(
                                onClick = onStartClass,
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = PrimaryBlue
                                ),
                                shape = RoundedCornerShape(10.dp)
                            ) {
                                Icon(
                                    Icons.Default.PlayArrow,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp)
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Text("Start Class")
                            }
                        }
                        schedule.isCompleted -> {
                            TextButton(onClick = onViewAttendance) {
                                Icon(
                                    Icons.Outlined.Assessment,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp)
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Text("View Report")
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun DetailItem(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    text: String
) {
    Row(
        verticalAlignment = Alignment.CenterVertically
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            modifier = Modifier.size(16.dp),
            tint = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Spacer(modifier = Modifier.width(6.dp))
        Text(
            text = text,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}
